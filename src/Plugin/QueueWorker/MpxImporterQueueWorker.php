<?php

namespace Drupal\media_mpx\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\media_mpx\MpxImportTask;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process mpx imports.
 *
 * This queue has been made to mitigate the process intensive nature
 * of importing mpx objects.
 *
 * @QueueWorker(
 *   id="media_mpx_importer",
 *   title="mpx import queue worker",
 *   cron={
 *     "time"=15
 *   }
 * )
 */
class MpxImporterQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The logger used to record import actions.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The update video service.
   *
   * @var \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem
   */
  private $updateService;

  /**
   * MpxImporterQueueWorker constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem $updateService
   *   The update video service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger used to record import actions.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, UpdateVideoItem $updateService, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->updateService = $updateService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('media_mpx.service.update_video_item'),
      $container->get('logger.channel.media_mpx')
    );
  }

  /**
   * Imports the mpx object designated by the task.
   *
   * @param mixed $data
   *   The details of the mpx object to import.
   */
  public function processItem($data) {
    if (!$data instanceof MpxImportTask) {
      return;
    }

    $update_request = UpdateVideoItemRequest::createFromMpxImportTask($data);
    try {
      $update_response = $this->updateService->execute($update_request);
      $saved = $update_response->getUpdatedEntities();
    }
    catch (\Exception $e) {
      $this->logger->error($this->t('mpx queued import failed. Mpx id: @id. Details: @details', [
        '@id' => $data->getMediaId(),
        '@details' => $e->getMessage(),
      ]));
      return;
    }

    $ids = array_map(function (MediaInterface $media) {
      return $media->id();
    }, $saved);

    $link = NULL;
    if (count($saved) == 1) {
      $media = reset($saved);
      $link = $this->t('<a href="@url">view</a>', [
        '@url' => $media->toUrl('canonical')->toString(),
      ]);
    }

    $this->logger->info('@id has been imported to media @saved.', [
      '@id' => $update_response->getMpxItem()->getId(),
      '@saved' => $this->formatPlural(
        count($saved),
        'ID @ids', 'IDs @ids',
        ['@ids' => implode(', ', $ids)]),
      'link' => $link,
    ]);
  }

}
