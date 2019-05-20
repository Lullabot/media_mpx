<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\FormAlter;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Alters the media form for mpx items to add "Reimport" button.
 *
 * @package Drupal\media_mpx\FormAlter
 */
class MediaFormAlter implements ContainerInjectionInterface {

  use StringTranslationTrait;

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
   * MediaFormAlter constructor.
   */
  private function __construct(MpxMediaType $mpxMediaTypeRepository, UpdateVideoItem $updateService) {
    $this->mpxMediaTypeRepository = $mpxMediaTypeRepository;
    $this->updateService = $updateService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_mpx.repository.mpx_media_types'),
      $container->get('media_mpx.service.update_video_item')
    );
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  public function alter(&$form, FormStateInterface $form_state, $form_id): void {
    if ($this->alterAppliesToForm($form_state) === FALSE) {
      return;
    }

    $form['actions']['reimport'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update with mpx data'),
      '#submit' => [[$this, 'reimportCallback']],
    ];
  }

  /**
   * Callback for the 'reimport' button.
   */
  public function reimportCallback(array $form, FormStateInterface $formState) {
    /* @var \Drupal\Core\Entity\ContentEntityForm $form_object */
    $form_object = $formState->getFormObject();
    $video = $form_object->getEntity();

    $id_field = NULL;
    if ($media_type = $this->mpxMediaTypeRepository->findByTypeId($video->bundle())) {
      $field_map = $media_type->getFieldMap();
      $id_field = $field_map['Media:id'] ?: NULL;
    }

    $mpx_id = $video->{$id_field}->value;
    $this->updateVideoData((int) $mpx_id, $video->bundle());
  }

  /**
   * Update video data and show success / error messages as relevant.
   */
  public function updateVideoData(int $mpxId, string $mediaTypeId) {
    $request = new UpdateVideoItemRequest($mpxId, $mediaTypeId);
    try {
      $this->updateService->execute($request);
      // @todo: success message.
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError('@todo: Add error message');
      // @todo: logging.
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

    try {
      $mpx_types = $this->mpxMediaTypeRepository->findAllTypes();
      $type_ids = [];
      foreach ($mpx_types as $key => $type) {
        $type_ids[] = $type->id();
        return in_array($form_object->getEntity()->bundle(), $type_ids);
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }

    return FALSE;
  }

}
