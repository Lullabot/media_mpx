<?php

namespace Drupal\media_mpx\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Event\ImportSelectEvent;
use Drupal\media_mpx\MpxImportTask;
use Drush\Commands\DrushCommands;
use function GuzzleHttp\Promise\each_limit;
use Lullabot\Mpx\DataService\Fields;
use Lullabot\Mpx\DataService\ObjectList;
use Lullabot\Mpx\DataService\ObjectListIterator;
use Lullabot\Mpx\DataService\ObjectListQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Drush commands for mpx.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MpxImporter extends DrushCommands {

  /**
   * The name of the queue for notifications.
   */
  const MEDIA_MPX_IMPORT_QUEUE = 'media_mpx_importer';

  /**
   * The entity type manager used to load entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The factory used to load media data from mpx.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  private $dataObjectFactoryCreator;

  /**
   * The factory to load the mpx notification queue.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  private $queueFactory;

  /**
   * The class to import mpx objects.
   *
   * @var \Drupal\media_mpx\DataObjectImporter
   */
  private $dataObjectImporter;

  /**
   * The system event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * MpxImporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The manager used to load config and media entities.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactoryCreator
   *   The creator used to configure a factory for loading mpx objects.
   * @param \Drupal\media_mpx\DataObjectImporter $dataObjectImporter
   *   The class used to import mpx objects.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The system event dispatcher.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The factory to load the mpx notification queue.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DataObjectFactoryCreator $dataObjectFactoryCreator, DataObjectImporter $dataObjectImporter, EventDispatcherInterface $eventDispatcher, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->dataObjectImporter = $dataObjectImporter;
    $this->eventDispatcher = $eventDispatcher;
    $this->queueFactory = $queue_factory;
  }

  /**
   * Import mpx objects for a media type.
   *
   * @param string $media_type_id
   *   The media type ID to import for.
   *
   * @usage media_mpx-import mpx_video
   *   Import all mpx data for the mpx_video media type.
   *
   * @command media_mpx:import
   * @aliases mpxi
   */
  public function import(string $media_type_id) {
    $media_type = $this->loadMediaType($media_type_id);

    // Only return the media ids to add to the queue.
    $field_query = new Fields();
    $field_query->addField('id');
    $query = new ObjectListQuery();
    $query->add($field_query);

    $results = $this->select($media_type, $query);

    // @todo Support fetching the total results via ObjectList.
    $this->io()->title(dt('Importing @type media', ['@type' => $media_type_id]));
    $this->io()->progressStart();

    $queue = $this->queueFactory->get(self::MEDIA_MPX_IMPORT_QUEUE);

    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    foreach ($results as $index => $mpx_media) {
      $queue->createItem(new MpxImportTask($mpx_media->getId(), $media_type_id));

      $this->io()->progressAdvance();
      $this->logger()->info(dt('Imported @type @uri.', ['@type' => $media_type_id, '@uri' => $mpx_media->getId()]));
    }

    $this->io()->progressFinish();

  }

  /**
   * Warm the mpx response cache.
   *
   * @param string $media_type_id
   *   The media type ID to warm.
   *
   * @command media_mpx:warm
   * @aliases mpxw
   */
  public function warmCache(string $media_type_id) {
    $media_type = $this->loadMediaType($media_type_id);

    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $account = $media_source->getAccount();

    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
    $query = new ObjectListQuery();
    $request = $factory->selectRequest($query, $account);
    $list = $request->wait();

    // @todo Support fetching the total results via ObjectList.
    $this->io()->title(dt('Warming cache for @type media', ['@type' => $media_type_id]));
    $this->io()->progressStart();

    $service_info = $media_source->getPluginDefinition()['media_mpx'];

    each_limit($list->yieldLists(), 4, function (ObjectList $list) use ($media_type, $service_info) {
      foreach ($list as $index => $item) {
        // For each item, we need to inject it into the response cache as if
        // a single request for the item was made.
        $this->dataObjectImporter->cacheItem($item, $service_info);
        $this->io()->progressAdvance();
        $this->logger()->info(dt('Fetched @type @uri.', ['@type' => $media_type->id(), '@uri' => $item->getId()]));
      }

    })->wait();
  }

  /**
   * Fetch all mpx items for a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to load items for.
   * @param \Lullabot\Mpx\DataService\ObjectListQuery $query
   *   An optional query to limit what is returned.
   *
   * @return \Lullabot\Mpx\DataService\ObjectListIterator
   *   An iterator over all retrieved media items.
   */
  private function select(MediaTypeInterface $media_type, ObjectListQuery $query = NULL): ObjectListIterator {
    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
    if (!$query) {
      $query = new ObjectListQuery();
    }
    $event = new ImportSelectEvent($query, $media_source);
    $this->eventDispatcher->dispatch(ImportSelectEvent::IMPORT_SELECT, $event);
    $results = $factory->select($query, $media_source->getAccount());
    return $results;
  }

  /**
   * Load the media type object for a given media type ID.
   *
   * @param string $media_type_id
   *   The media type ID.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   The loaded media type object.
   */
  private function loadMediaType(string $media_type_id): MediaTypeInterface {
    $bundle_type = $this->entityTypeManager->getDefinition('media')->getBundleEntityType();
    /* @var $media_type \Drupal\media\MediaTypeInterface */
    if (!$media_type = $this->entityTypeManager->getStorage($bundle_type)
      ->load($media_type_id)) {
      // Normally you wouldn't translate exception text, but Drush does it in
      // it's own commands.
      // @see https://github.com/drush-ops/drush/blob/76a28373e7d3bdd708ab54d54f0e686370b46506/examples/Commands/PolicyCommands.php#L37
      throw new \InvalidArgumentException(dt('The media type @type does not exist.', ['@type' => $media_type_id]));
    }
    return $media_type;
  }

}
