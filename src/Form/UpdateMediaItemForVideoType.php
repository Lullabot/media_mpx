<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
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
   * UpdateMediaItemForAccount constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, UpdateVideoItem $updateVideoItem, MpxMediaType $mpxTypeRepository, MpxLogger $logger) {
    $this->mediaStorage = $entityTypeManager->getStorage('media');
    $this->updateVideoItemService = $updateVideoItem;
    $this->mpxTypeRepository = $mpxTypeRepository;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_mpx.service.update_video_item'),
      $container->get('media_mpx.repository.mpx_media_types'),
      $container->get('media_mpx.exception_logger')
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

    if (!$video_item = $this->loadVideoMatchingGuidAndType($guid, $video_type)) {
      $this->messenger()->addError($this->t('There are no video items with the selected GUID and Type.'));
      return;
    }

    $updateRequest = UpdateVideoItemRequest::createFromMediaEntity($video_item);
    try {
      $this->updateVideoItemService->execute($updateRequest);
      $this->messenger()->addMessage($this->t('The selected video has been updated.'));
    }
    catch (\Exception $e) {
      // Up until here, all necessary checks have been made. No custom exception
      // handling needed other than for the db possibly exploding at this point.
      $this->messenger()->addError($this->t('There has been an unexpected problem updating the video. Check the logs for details.'));
      $this->logger->watchdogException($e, sprintf('mpx video with guid %s could not be updated', $guid));
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
