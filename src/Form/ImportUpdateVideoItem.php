<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Repository\MpxMediaType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;

/**
 * Form class to import a single mpx item based on its id.
 *
 * @package Drupal\media_mpx\Form
 */
abstract class ImportUpdateVideoItem extends FormBase {

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
      '#default_value' => count($video_opts) === 1 ? array_keys($video_opts) : [],
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
