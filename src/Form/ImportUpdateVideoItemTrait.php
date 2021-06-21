<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

/**
 * Form trait for base form and utility methods.
 *
 * @package Drupal\media_mpx\Form
 */
trait ImportUpdateVideoItemTrait {

  /**
   * {@inheritdoc}
   */
  public function buildBaseForm(array $form) {
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
  public function loadVideoTypeOptions(): array {
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
