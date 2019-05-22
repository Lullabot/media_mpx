<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class to import a single mpx item based on its id.
 *
 * @package Drupal\media_mpx\Form
 */
class ImportVideoItemByMpxId extends FormBase {

  /**
   * The Update Video Item service.
   *
   * @var \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem
   */
  private $updateVideoItem;

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The media type repository.
   *
   * @var \Drupal\media_mpx\Repository\MpxMediaType
   */
  private $mpxTypeRepository;

  /**
   * UpdateMediaItemForAccount constructor.
   */
  public function __construct(UpdateVideoItem $updateVideoItem, MpxMediaType $mpxTypeRepository, LoggerInterface $logger) {
    $this->updateVideoItem = $updateVideoItem;
    $this->mpxTypeRepository = $mpxTypeRepository;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_mpx.service.update_video_item'),
      $container->get('media_mpx.repository.mpx_media_types'),
      $container->get('logger.channel.media_mpx')
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
    $form['mpx_id'] = [
      '#type' => 'textfield',
      '#title' => t('mpx item ID'),
      '#required' => FALSE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Item'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mpx_id = (int) $form_state->getValue('mpx_id');
    $video_type = $form_state->getValue('video_type');

    $request = new UpdateVideoItemRequest($mpx_id, $video_type);
    try {
      $this->updateVideoItem->execute($request);
      $this->messenger()->addMessage($this->t('The selected video has been imported.'));
    }
    catch (\Exception $e) {
      // Up until here, all necessary checks have been made. No custom exception
      // handling needed other than for the db possibly exploding at this point.
      $this->messenger()->addError($this->t('There has been an unexpected problem updating the video. Check the logs for details.'));
      $this->watchdogException($e);
    }
  }

  /**
   * Logs an exception.
   *
   * @param \Exception $exception
   *   The exception that is going to be logged.
   * @param string $message
   *   The message to store in the log.
   *
   * @see \Drupal\Core\Utility\Error::decodeException()
   */
  private function watchdogException(\Exception $exception, $message = NULL) {
    // Use a default value if $message is not set.
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    $variables = Error::decodeException($exception);
    $this->logger->log(RfcLogLevel::ERROR, $message, $variables);
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
      $this->watchdogException($e);
    }

    return $video_opts;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_single_by_mpx_id';
  }

}
