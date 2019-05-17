<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use Drupal\media\Entity\Media;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Psr\Log\LoggerInterface;
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
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * UpdateMediaItemForAccount constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, UpdateVideoItem $updateVideoItem, MpxMediaType $mpxTypeRepository, LoggerInterface $logger) {
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
      $container->get('logger.channel.media_mpx')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    try {
      $video_types = $this->mpxTypeRepository->findAllTypes();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('There has been an unexpected problem finding the local video information. Check the logs for details.'));
      $this->watchdogException($e);
    }
    $video_opts = [];

    foreach ($video_types as $type) {
      $video_opts[$type->id()] = $type->label();
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
    }
    catch (\Exception $e) {
      // Up until here, all necessary checks have been made. No custom exception
      // handling needed other than for the db possibly exploding at this point.
      $this->messenger()->addError($this->t('There has been an unexpected problem updating the video. Check the logs for details.'));
      $this->watchdogException($e);
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
    $id = $this->mediaStorage->getQuery()
      ->condition('field_mpx_guid', $guid)
      ->condition('bundle', $type)
      ->execute();

    $video = NULL;
    if (!empty($id)) {
      $video = $this->mediaStorage->load(reset($id));
    }

    return $video;
  }

  /**
   * Logs an exception.
   *
   * @param \Exception $exception
   *   The exception that is going to be logged.
   * @param string $message
   *   The message to store in the log.
   * @param array $variables
   *   Array of variables to replace in the message on display or
   *   NULL if message is already translated or not possible to
   *   translate.
   * @param int $severity
   *   The severity of the message, as per RFC 3164.
   * @param string $link
   *   A link to associate with the message.
   *
   * @see \Drupal\Core\Utility\Error::decodeException()
   */
  private function watchdogException(\Exception $exception, $message = NULL, array $variables = [], $severity = RfcLogLevel::ERROR, $link = NULL) {
    // Use a default value if $message is not set.
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
      $variables['link'] = $link;
    }

    $variables += Error::decodeException($exception);
    $this->logger->log($severity, $message, $variables);
  }

}
