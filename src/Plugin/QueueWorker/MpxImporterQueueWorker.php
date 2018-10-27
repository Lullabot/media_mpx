<?php

namespace Drupal\media_mpx\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\MpxImportTask;
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
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, DataObjectImporter $data_importer, DataObjectFactoryCreator $dataObjectFactoryCreator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->dataObjectImporter = $data_importer;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
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
      $container->get('media_mpx.data_object_factory_creator')
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
    $media_type = $this->entityTypeManager->getStorage($bundle_type)->load($media_type_id);
    if (!$media_type) {
      return;
    }

    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
    $results = $factory->load($media_id);
    $mpx_media = $results->wait();
    $this->dataObjectImporter->importItem($mpx_media, $media_type);
  }

}
