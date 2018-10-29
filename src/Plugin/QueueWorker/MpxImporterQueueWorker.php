<?php

namespace Drupal\media_mpx\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\MpxImportTask;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;

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
   * The factory used to load a complete mpx object.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  protected $dataObjectFactoryCreator;

  /**
   * A class to import mpx objects.
   *
   * @var \Drupal\media_mpx\DataObjectImporter
   */
  private $dataObjectImporter;

  /**
   * The entity type manager used to load entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The logger used to record import actions.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * MpxImporterQueueWorker constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   An entity type manager.
   * @param \Drupal\media_mpx\DataObjectImporter $data_importer
   *   The data importer.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactoryCreator
   *   Data factory creator.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger used to record import actions.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, DataObjectImporter $data_importer, DataObjectFactoryCreator $dataObjectFactoryCreator, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->dataObjectImporter = $data_importer;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('media_mpx.data_object_importer'),
      $container->get('media_mpx.data_object_factory_creator'),
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
    /** @var \Drupal\media_mpx\MpxImportTask $data */
    $media_type_id = $data->getMediaTypeId();
    $media_id = $data->getMediaId();

    $bundle_type = $this->entityTypeManager->getDefinition('media')->getBundleEntityType();
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage($bundle_type)->load($media_type_id);
    if (!$media_type) {
      return;
    }

    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
    $results = $factory->load($media_id);
    $mpx_media = $results->wait();
    $saved = $this->dataObjectImporter->importItem($mpx_media, $media_type);

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
      '@id' => $mpx_media->getId(),
      '@saved' => $this->formatPlural(count($saved), 'ID @ids', 'IDs @ids', ['@ids' => implode(', ', $ids)]),
      'link' => $link,
    ]);
  }

}
