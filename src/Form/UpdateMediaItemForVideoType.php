<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Lullabot\Mpx\DataService\ByFields;
use Lullabot\Mpx\DataService\ObjectListQuery;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class to create / update a single mpx item for a video type (bundle).
 *
 * @package Drupal\media_mpx\Form
 */
class UpdateMediaItemForVideoType extends FormBase {

  /**
   * The media storage.
   *
   * @var \Drupal\media\MediaStorage
   */
  private $mediaStorage;

  /**
   * The update video service.
   *
   * @var \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem
   */
  private $updateVideoItemService;

  /**
   * The mpx Media Type Repository.
   *
   * @var \Drupal\media_mpx\Repository\MpxMediaType
   */
  private $mpxTypeRepository;

  /**
   * The custom media mpx logger.
   *
   * @var \Drupal\media_mpx\MpxLogger
   */
  private $logger;

  /**
   * The Data Object Factory Creator.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  private $dataObjectFactoryCreator;

  /**
   * UpdateMediaItemForAccount constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, UpdateVideoItem $updateVideoItem, MpxMediaType $mpxTypeRepository, MpxLogger $logger, DataObjectFactoryCreator $dataObjectFactoryCreator) {
    $this->mediaStorage = $entityTypeManager->getStorage('media');
    $this->updateVideoItemService = $updateVideoItem;
    $this->mpxTypeRepository = $mpxTypeRepository;
    $this->logger = $logger;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_mpx.service.update_video_item'),
      $container->get('media_mpx.repository.mpx_media_types'),
      $container->get('media_mpx.exception_logger'),
      $container->get('media_mpx.data_object_factory_creator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$video_opts = $this->loadVideoTypeOptions()) {
      $this->messenger()->addError($this->t('There has been an unexpected problem loading the form. Reload the page.'));
      return [];
    }

    $form['video_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Video Type'),
      '#description' => $this->t('Choose the video type to import the video into.'),
      '#options' => $video_opts,
      '#required' => TRUE,
    ];
    $form['guid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('guid'),
      '#placeholder' => 'Type the GUID of the mpx item you want to import.',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update item'),
    ];

    return $form;
  }

  /**
   * Submit handler for the 'media_mpx_asset_sync_single_by_account_guid' form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $video_type = $form_state->getValue('video_type');
    $guid = $form_state->getValue('guid');
    $video_item = NULL;
    if (!$video_item = $this->loadVideoMatchingGuidAndType($guid, $video_type)) {
      $videoMpxData = $this->loadVideoMatchingGuidAndTypeFromMpx($guid, $video_type);
      $mpx_id = (int) end(explode('/', (string) $videoMpxData->getId()));
      $request = new UpdateVideoItemRequest($mpx_id, $video_type);
      try {
        $this->updateVideoItemService->execute($request);
        $this->messenger()->addMessage($this->t('The selected video has been imported.'));
      }
      catch (\Exception $e) {
        // Up until here, all necessary checks have been made. No custom 
        // exception handling needed other than for the db possibly
        // exploding at this point.
        $this->messenger()->addError($this->t('There has been an unexpected problem getting the video. Check the logs for details.'));
        $this->logger->watchdogException($e);
      }
    }
    else {
      $updateRequest = UpdateVideoItemRequest::createFromMediaEntity($video_item);
      try {
        $this->updateVideoItemService->execute($updateRequest);
        $this->messenger()->addMessage($this->t('The selected video has been updated.'));
      }
      catch (\Exception $e) {
        // Up until here, all necessary checks have been made. No custom
        // exception handling needed other than for the db possibly
        // exploding at this point.
        $this->messenger()->addError($this->t('There has been an unexpected problem updating the video. Check the logs for details.'));
        $this->logger->watchdogException($e, 'mpx video with guid @guid could not be updated', ['@guid' => $guid]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_single_by_account_guid';
  }

  /**
   * Loads the media entity with a given guid and bundle.
   *
   * @param string $guid
   *   The guid to filter by.
   * @param string $type
   *   The video type.
   *
   * @return \Drupal\media\Entity\Media|null
   *   The mpx Media entity matching the given guid
   */
  private function loadVideoMatchingGuidAndType(string $guid, string $type):? Media {
    $guid_field = NULL;
    if ($media_type = $this->mpxTypeRepository->findByTypeId($type)) {
      $field_map = $media_type->getFieldMap();
      $guid_field = $field_map['Media:guid'] ?: NULL;
    }

    if (!$guid_field) {
      return NULL;
    }

    $id = $this->mediaStorage->getQuery()
      ->condition($guid_field, $guid)
      ->condition('bundle', $type)
      ->execute();

    $video = NULL;
    if (!empty($id)) {
      $video = $this->mediaStorage->load(reset($id));
    }

    return $video;
  }

  /**
   * Loads the media entity with a given guid and bundle from MPX.
   *
   * @param string $guid
   *   The guid to filter by.
   * @param string $type
   *   The video type.
   *
   * @return \Drupal\media\Entity\Media|null
   *   The mpx Media entity matching the given guid
   */
  private function loadVideoMatchingGuidAndTypeFromMpx(string $guid, string $type):?MpxMedia {
    $media_type = $this->mpxTypeRepository->findByTypeId($type);
    if (!$media_type) {
      return NULL;
    }
    $mediaData = $this->fetchMediaTypeItemGuidFromMpx($media_type, $guid);
    return $mediaData;
  }

  /**
   * Fetches the item data from MPX for a given media type and GUID.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The Media Type object.
   * @param string $guid
   *   The GUID of the media that want to retrieve.
   *
   * @return \Lullabot\Mpx\DataService\Media\Media
   *   The Media entity returned by the mpx.
   */
  private function fetchMediaTypeItemGuidFromMpx(MediaTypeInterface $media_type, string $guid):?MpxMedia {
    $field_query = new ByFields();
    $field_query->addField('guid', $guid);
    $query = new ObjectListQuery();
    $query->add($field_query);

    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);

    $results = $factory->select($query);
    $queues = [];
    foreach ($results as $index => $mpx_media) {
      $queues[] = $mpx_media;
    }
    return reset($queues);
  }

  /**
   * Returns the mpx Video Type options of the dropdown (prepared for form api).
   *
   * @return array
   *   An array with options to show in the dropdown. The keys are the video
   *   types, and the values are the video type label.
   */
  private function loadVideoTypeOptions(): array {
    $video_opts = [];

    try {
      $video_types = $this->mpxTypeRepository->findAllTypes();

      foreach ($video_types as $type) {
        $video_opts[$type->id()] = $type->label();
      }
    }
    catch (\Exception $e) {
      $this->logger->watchdogException($e, 'Could not load mpx video type options.');
    }

    return $video_opts;
  }

}
