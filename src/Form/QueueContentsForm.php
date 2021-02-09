<?php

namespace Drupal\media_mpx\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\QueueVideoImportsRequest;
use Drupal\media_mpx\Service\QueueVideoImportsResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QueueContentsForm.
 */
class QueueContentsForm extends FormBase {

  /**
   * The mpx Media Types repository.
   *
   * @var \Drupal\media_mpx\Repository\MpxMediaType
   */
  private $mpxMediaTypeRepository;

  /**
   * QueueContentsForm constructor.
   */
  public function __construct(MpxMediaType $mpxMediaTypeRepository) {
    $this->mpxMediaTypeRepository = $mpxMediaTypeRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_mpx.repository.mpx_media_types')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $mpx_media_types = $this->mpxMediaTypeRepository->findAllTypes();
    $options = [];
    foreach ($mpx_media_types as $mpx_media_type) {
      $options[$mpx_media_type->id()] = $mpx_media_type->label();
    }

    $form['mpx_video_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('mpx Video types'),
      '#options' => $options,
      '#default_value' => count($options) === 1 ? array_keys($options) : [],
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['queue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import videos'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_filter(array_values($form_state->getValue('mpx_video_types')));

    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Finding mpx videos to import'))
      ->setFinishCallback([$this, 'batchFinished'])
      ->setInitMessage($this->t('Queueing mpx videos for background imports.'));

    foreach ($types as $type) {
      $batch_builder->addOperation([$this, 'batchedQueueItemsForMediaType'], ['media_type_id' => $type]);
    }
    batch_set($batch_builder->toArray());
  }

  /**
   * Operation callback to queue each mpx media type for imports.
   *
   * @param string $media_type_id
   *   The media type id to process in this callback.
   * @param array $context
   *   Context data passed between batches.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\media_mpx\Exception\MediaTypeDoesNotExistException
   * @throws \Drupal\media_mpx\Exception\MediaTypeNotAssociatedWithMpxException
   */
  public function batchedQueueItemsForMediaType(string $media_type_id, array &$context) {
    /** @var \Drupal\media_mpx\Service\QueueVideoImports $queue_service */
    $queue_service = \Drupal::getContainer()->get('media_mpx.service.queue_video_imports');

    // Mpx responses might be a bit heavy, so limit batches size to 200. Don't
    // want php running out of memory.
    // @see vendor/lullabot/mpx-php/src/DataService/ObjectListQuery.php
    $batch_size = 200;

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['last_index'] = 0;
      $context['results'] = [
        'success' => [],
        'errors' => [],
      ];
    }

    $request = new QueueVideoImportsRequest(
      $media_type_id,
      $batch_size,
      $context['sandbox']['last_index'] + 1
    );

    $response = $queue_service->execute($request);
    $this->updateContextArrayFromResponse($media_type_id, $context, $response, $batch_size);
  }

  /**
   * Updates the context array after getting a response from the queue service.
   *
   * @param string $media_type_id
   *   The media type id being processed.
   * @param array $context
   *   The context array.
   * @param \Drupal\media_mpx\Service\QueueVideoImportsResponse $response
   *   The response from the queue service.
   * @param int $batch_size
   *   The batch size being used for the batch operation.
   */
  private function updateContextArrayFromResponse(string $media_type_id, array &$context, QueueVideoImportsResponse $response, int $batch_size): void {
    $context['sandbox']['total_results'] = $context['sandbox']['total_results'] ?: $response->getIterator()->getTotalResults();
    $total_items = $context['sandbox']['total_results'];

    $context['results']['success'] = array_merge($context['results']['success'], $response->getQueuedVideos());
    $context['results']['errors'] = array_merge($context['results']['errors'], $response->getNotQueuedVideos());
    $context['sandbox']['progress'] += count($response->getQueuedVideos());
    $context['sandbox']['last_index'] = $context['sandbox']['last_index'] + $batch_size;
    $context['message'] = $this->t('Queued @progress %type items out of @total.', [
      '@progress' => $context['sandbox']['progress'],
      '@total'    => $total_items,
      '%type'     => $media_type_id,
    ]);

    $context['finished'] = $context['sandbox']['progress'] / $total_items;
  }

  /**
   * Finished callback for the batch queuing operations to update mpx videos.
   *
   * @param bool $success
   *   Result of the queuing operations.
   * @param array $results
   *   An array with the count of successful and errored queue operations.
   */
  public function batchFinished(bool $success, array $results) {
    $finished_message = $this->t('Finished with errors.');

    if ($success) {
      $finished_message = $this->formatPlural(count($results['success']),
        'One mpx item queued.',
        '@count mpx items queued for updates. Imports will continue in the background.');
    }

    $this->messenger()->addMessage($finished_message);

    if (count($results['errors']) > 0) {
      $errors_message = $this->formatPlural(
        count($results['errors']),
        'The following mpx item could not be queued. Check logs for details.',
        'The following @count mpx items could not be queued for updates. Check logs for details.');
      $this->messenger()->addError($errors_message);

      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_item */
      foreach ($results['errors'] as $mpx_item) {
        $this->messenger()->addError($mpx_item->getId());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_queue';
  }

}
