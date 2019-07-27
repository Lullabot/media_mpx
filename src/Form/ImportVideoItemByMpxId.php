<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Plugin\media\Source\Media;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class to import a single mpx item based on its id.
 *
 * @package Drupal\media_mpx\Form
 */
class ImportVideoItemByMpxId extends ImportUpdateVideoItem {

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
    $form = parent::buildForm($form, $form_state);

    $form['mpx_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#placeholder' => $this->t('Type the ID of the mpx video you want to import.'),
      '#required' => FALSE,
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
        $mpx_media = $response->getMpxItem();
        $this->messenger()->addWarning($this->t("The selected video: @video_title (@id) did not import. There may be one or more custom business rules in place which filtered it out. Consult the site administrator, adjust the video metadata in mpx to ensure it's available to be imported, and try again.", [
          '@video_title' => $mpx_media->getTitle(),
          '@id' => Media::getMpxObjectIdFromUri((string) $mpx_media->getId()),
        ]));
      }
      else {
        $this->messenger()->addMessage($this->t('The selected video has been imported.'));
      }
    }
    catch (\Exception $e) {
      // Up until here, all necessary checks have been made. No custom exception
      // handling needed other than for the db possibly exploding at this point.
      $this->messenger()->addError($this->t('There has been an unexpected problem importing the video. Check the logs for details.'));
      $this->logger->watchdogException($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_single_by_mpx_id';
  }

}
