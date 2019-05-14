<?php

namespace Drupal\media_mpx\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\QueueVideoImportsRequest;
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $mpx_media_types = $this->mpxMediaTypeRepository->findAllTypes();
    $options = [];
    foreach ($mpx_media_types as $mpx_media_type) {
      $options[$mpx_media_type->id()] = $mpx_media_type->label();
    }

    $form['mpx_video_types'] = [
      '#type' => 'checkboxes',
      '#title' => t('mpx Video Types'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['queue'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue selected Video Types'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $types = array_filter(array_values($form_state->getValue('mpx_video_types')));

    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle(t('MPX Video Imports Queueing'))
      ->setFinishCallback([$this, 'batchFinished'])
      ->setInitMessage(t('Starting Queueing Process.'));

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
    /* @var \Drupal\media_mpx\Service\QueueVideoImports $service */
    $service = \Drupal::getContainer()->get('media_mpx.service.queue_video_imports');
    $batch_size = 200;

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['last_index'] = 0;
      $context['results'] = [
        'success' => 0,
        'errors' => 0,
      ];
    }

    $request = new QueueVideoImportsRequest($media_type_id,
      $batch_size,
      $context['sandbox']['last_index'] + 1);

    $response = $service->execute($request);

    if (!isset($context['sandbox']['total_results'])) {
      $context['sandbox']['total_results'] = $response->getIterator()->getList()->getTotalResults();
    }
    $total_items = $context['sandbox']['total_results'];

    $context['results']['success'] += $response->getVideosQueued();
    $context['results']['errors'] += $response->getErrors();
    $context['sandbox']['progress'] += $response->getVideosQueued();
    $context['sandbox']['last_index'] = $context['sandbox']['last_index'] + $batch_size;

    $context['message'] = 'Processed a total of ' . $context['sandbox']['progress'] . ' items.';
    $context['message'] = t('Queued @progress %type items out of @total.', [
      '@progress' => $context['sandbox']['progress'],
      '@total' => $total_items,
      '%type' => $media_type_id,
    ]);

    if ($context['sandbox']['progress'] != $total_items) {
      $context['finished'] = $context['sandbox']['progress'] / $total_items;
    }
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
    $finished_message = t('Finished with errors.');
    $messenger = \Drupal::messenger();

    if ($success) {
      $finished_message = \Drupal::translation()->formatPlural(
        $results['success'],
        'One mpx item queue.',
        '@count mpx items queued for updates.');
    }

    $messenger->addMessage($finished_message);

    if ($results['errors'] > 0) {
      $errors_message = \Drupal::translation()->formatPlural(
        $results['errors'],
        'One mpx item could not be queued (check logs for details).',
        '@count mpx items could not be queued for updates (check logs for details).');
      $messenger->addError($errors_message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_queue';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_mpx.repository.mpx_media_types')
    );
  }

}
