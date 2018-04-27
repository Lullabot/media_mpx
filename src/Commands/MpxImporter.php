<?php

namespace Drupal\media_mpx\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drush\Commands\DrushCommands;
use Lullabot\Mpx\DataService\ByFields;
use Lullabot\Mpx\DataService\DataServiceManager;
use Lullabot\Mpx\DataService\ObjectListIterator;

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
   * The factory used to store full mpx Media objects in the key-value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  private $keyValueFactory;

  /**
   * The manager to discover data service classes.
   *
   * @var \Lullabot\Mpx\DataService\DataServiceManager
   */
  private $dataServiceManager;

  /**
   * MpxImporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The manager used to load config and media entities.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactoryCreator
   *   The creator used to configure a factory for loading mpx objects.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The key-value factory for storing complete objects.
   * @param \Lullabot\Mpx\DataService\DataServiceManager $dataServiceManager
   *   The manager to discover data service classes.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DataObjectFactoryCreator $dataObjectFactoryCreator, KeyValueFactoryInterface $keyValueFactory, DataServiceManager $dataServiceManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->keyValueFactory = $keyValueFactory;
    $this->dataServiceManager = $dataServiceManager;
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

    $results = $this->selectAll($media_type);

    // @todo Support fetching the total results via ObjectList.
    $this->io()->title(dt('Importing @type media', ['@type' => $media_type_id]));
    $this->io()->progressStart();

    $importer = new DataObjectImporter($this->keyValueFactory, $this->entityTypeManager);
    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    foreach ($results as $index => $mpx_media) {
      $importer->importItem($mpx_media, $media_type);

      $this->io()->progressAdvance();
      $this->logger()->info(dt('Imported @type @uri.', ['@type' => $media_type_id, '@uri' => $mpx_media->getId()]));
    }

    $this->io()->progressFinish();

  }

  /**
   * Fetch all mpx items for a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to load items for.
   *
   * @return \Lullabot\Mpx\DataService\ObjectListIterator
   *   An iterator over all retrieved media items.
   */
  private function selectAll(MediaTypeInterface $media_type): ObjectListIterator {
    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $account = $media_source->getAccount();

    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
    // @todo Remove this when it's made optional upstream.
    // @see https://github.com/Lullabot/mpx-php/issues/78
    $fields = new ByFields();
    $results = $factory->select($fields, $account);
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
