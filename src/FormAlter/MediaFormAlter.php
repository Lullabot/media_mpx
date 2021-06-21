<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\FormAlter;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\Entity\Media;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;

/**
 * Alters the media form for mpx items to add "Reimport" button.
 *
 * @package Drupal\media_mpx\FormAlter
 */
class MediaFormAlter {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * The mpx media types repository.
   *
   * @var \Drupal\media_mpx\Repository\MpxMediaType
   */
  private $mpxMediaTypeRepository;

  /**
   * Update Video Item service.
   *
   * @var \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem
   */
  private $updateService;

  /**
   * The custom media mpx logger service.
   *
   * @var \Drupal\media_mpx\MpxLogger
   */
  private $logger;

  /**
   * MediaFormAlter constructor.
   */
  public function __construct(UpdateVideoItem $updateService, MpxLogger $logger) {
    $this->updateService = $updateService;
    $this->logger = $logger;
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  public function alter(&$form, FormStateInterface $form_state, $form_id): void {
    if ($this->alterAppliesToForm($form_state) === FALSE) {
      return;
    }

    $form['actions']['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update mpx data'),
      '#submit' => [[$this, 'reimportCallback']],
    ];
  }

  /**
   * Callback for the 'Update mpx data' button.
   */
  public function reimportCallback(array $form, FormStateInterface $formState) {
    /** @var \Drupal\Core\Entity\ContentEntityForm $form_object */
    $form_object = $formState->getFormObject();

    try {
      $this->updateVideoData($form_object->getEntity());
    }
    catch (\InvalidArgumentException $e) {
    }
  }

  /**
   * Returns the name of the entity field holding the mpx ID.
   *
   * @param \Drupal\media\Entity\Media $video
   *   The media entity for which to fetch the mpx ID field.
   *
   * @return string|null
   *   The name of the field holding the mpx ID, or NULL if not configured.
   */
  private function resolveIdFieldName(Media $video):? string {
    $media_source = $video->getSource();
    $source_field = $media_source->getSourceFieldDefinition($video->bundle->entity);
    return $source_field->getName();
  }

  /**
   * Update video data and show success / error messages as relevant.
   */
  public function updateVideoData(Media $video) {
    $request = UpdateVideoItemRequest::createFromMediaEntity($video);
    try {
      $this->updateService->execute($request);
      $this->messenger()->addMessage($this->t('The video has been reimported.'));
    }
    catch (\Exception $e) {
      $id_field = $this->resolveIdFieldName($video);
      $mpx_id = $video->{$id_field}->value;

      $this->messenger()->addError("The video data could not be reimported. Try again in a few minutes.");
      $this->logger->watchdogException($e, 'Video Reimport failed for item @item', ['@item' => $mpx_id]);
    }
  }

  /**
   * Checks if the current form needs to be altered or used in this alter class.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object.
   *
   * @return bool
   *   TRUE if this class needs to alter the form, FALSE otherwise.
   */
  private function alterAppliesToForm(FormStateInterface $formState): bool {
    $form_object = $formState->getFormObject();

    if (!$form_object instanceof ContentEntityForm) {
      return FALSE;
    }

    $entity = $form_object->getEntity();
    $is_media_entity = $entity instanceof Media;

    if (!$is_media_entity || !$entity->getSource() instanceof MpxMediaSourceInterface) {
      return FALSE;
    }

    return !is_null($this->resolveIdFieldName($entity));
  }

}
