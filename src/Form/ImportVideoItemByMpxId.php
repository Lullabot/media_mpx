<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
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
   * The media type repository.
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
  public function __construct(UpdateVideoItem $updateVideoItem, MpxMediaType $mpxTypeRepository, MpxLogger $logger) {
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
      '#title' => $this->t('Video type'),
      '#description' => $this->t('Choose the video type to import the video into.'),
      '#options' => $video_opts,
      '#required' => TRUE,
    ];
    $form['mpx_id'] = [
      '#type' => 'textfield',
      '#title' => t('ID'),
      '#placeholder' => 'Type the ID of the mpx video you want to import.',
      '#required' => FALSE,
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mpx_id = (int) $form_state->getValue('mpx_id');
    $video_type = $form_state->getValue('video_type');

    $request = new UpdateVideoItemRequest($mpx_id, $video_type);
    try {
      $response = $this->updateVideoItem->execute($request);
      if (empty($response->getUpdatedEntities())) {
        $this->messenger()->addWarning($this->t('The selected video: @video_title (@guid) did not import. The video was filtered out by one or more custom import filters. Adjust the video metadata in mpx to ensure it\'s available to be imported and try again.', [
          '@video_title' => $mpx_media->getTitle(),
          '@guid' => $mpx_media->getGuid(),
        ]));
      }
      else {
        $this->messenger()->addMessage($this->t('The selected video has been imported.'));
      }
    }
    catch (\Exception $e) {
      // Up until here, all necessary checks have been made. No custom exception
      // handling needed other than for the db possibly exploding at this point.
      $this->messenger()->addError($this->t('There has been an unexpected problem updating the video. Check the logs for details.'));
      $this->logger->watchdogException($e);
    }
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

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_single_by_mpx_id';
  }

}
