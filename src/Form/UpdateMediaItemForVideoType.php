<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Plugin\media\Source\Media as MediaEntity;
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
   *
   * @param \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem $updateVideoItem
   *   The update video service.
   * @param \Drupal\media_mpx\Repository\MpxMediaType $mpxTypeRepository
   *   The mpx Media Types repository.
   * @param \Drupal\media_mpx\MpxLogger $logger
   *   The mpx error specific logger.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactoryCreator
   *   The factory used to load a complete mpx object.
   */
  public function __construct(UpdateVideoItem $updateVideoItem, MpxMediaType $mpxTypeRepository, MpxLogger $logger, DataObjectFactoryCreator $dataObjectFactoryCreator) {
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
      '#title' => $this->t('Video type'),
      '#description' => $this->t('Choose the video type to import the video into.'),
      '#options' => $video_opts,
      '#required' => TRUE,
    ];
    $form['guid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Guid'),
      '#placeholder' => 'Type the GUID of the mpx video you want to import.',
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import video'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Submit handler for the 'media_mpx_asset_sync_single_by_account_guid' form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $video_type = $form_state->getValue('video_type');
    $guid = $form_state->getValue('guid');
    $video_mpx_data = $this->loadVideoMatchingGuidAndTypeFromMpx($guid, $video_type);
    if ($video_mpx_data) {
      $mpx_id = MediaEntity::getMpxObjectIdFromUri((string) $video_mpx_data->getId());
      $request = new UpdateVideoItemRequest($mpx_id, $video_type);
      $success_text = (string) $this->t('The selected video has been imported.');
      $error_text = (string) $this->t('There has been an unexpected problem getting the video. Check the logs for details.');
      $this->submitFormProcessRequest($request, $guid, $success_text, $error_text);
      return;
    }
    $guid_not_found_exception = new \Exception("Given GUID doesn't exist, please check and try again.");
    $error_text = (string) $this->t("Given GUID doesn't exist, please check and try again.");
    $this->submitFormReportError($guid, $guid_not_found_exception, $error_text);
  }

  /**
   * Given request execute it with the video item service.
   *
   * @param \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest $request
   *   Update/creation video request to be executed.
   * @param string $guid
   *   GUID of the required MPX object.
   * @param string $success_text
   *   Success text to be used on the notification.
   * @param string $error_text
   *   Error text to be used on the logger.
   * */
  private function submitFormProcessRequest(UpdateVideoItemRequest $request, string $guid, string $success_text, string $error_text) {
    try {
      $this->submitFormExecuteRequest($request, $success_text);
    }
    catch (\Exception $e) {
      // Up until here, all necessary checks have been made. No custom exception
      // handling needed other than for the db possibly exploding at this point.
      $this->submitFormReportError($guid, $e, $error_text);
    }
  }

  /**
   * Given request execute it with the video item service.
   *
   * @param \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest $request
   *   Update/creation video request to be executed.
   * @param string $success_text
   *   Success text to be used on the notification.
   */
  private function submitFormExecuteRequest(UpdateVideoItemRequest $request, string $success_text) {
    $response = $this->updateVideoItemService->execute($request);
    if (empty($response->getUpdatedEntities())) {
      $mpx_media = $response->getMpxItem();
      $this->messenger()->addWarning($this->t("The selected video: @video_title (@guid) did not import. The video was filtered out by one or more custom import filters. Adjust the video metadata in mpx to ensure it's available to be imported and try again.", [
        '@video_title' => $mpx_media->getTitle(),
        '@guid' => $mpx_media->getGuid(),
      ]));
    }
    else {
      $this->messenger()->addMessage($success_text);
    }
  }

  /**
   * Report an error given guid and exception.
   *
   * @param string $guid
   *   GUID of the required MPX object.
   * @param \Exception $e
   *   Exception that should be notified.
   * @param string $error_text
   *   Error text to be used on the logger.
   */
  private function submitFormReportError(string $guid, \Exception $e, string $error_text) {
    $this->messenger()->addError($error_text);
    $this->logger->watchdogException($e, 'mpx video with guid @Guid could not be created/updated.', ['@Guid' => $guid]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_single_by_account_guid';
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
  private function loadVideoMatchingGuidAndTypeFromMpx(string $guid, string $type): ?MpxMedia {
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
   * @return \Lullabot\Mpx\DataService\Media\Media|null
   *   The Media entity returned by the mpx.
   */
  private function fetchMediaTypeItemGuidFromMpx(MediaTypeInterface $media_type, string $guid): ?MpxMedia {
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
    return reset($queues) ?: NULL;
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
