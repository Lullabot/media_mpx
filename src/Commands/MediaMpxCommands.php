<?php

namespace Drupal\media_mpx\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\Plugin\media\Source\Media;
use Drush\Commands\DrushCommands;
use Lullabot\Mpx\DataService\Access\Account;
use Lullabot\Mpx\DataService\ByFields;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;
use Lullabot\Mpx\DataService\ObjectListIterator;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MediaMpxCommands extends DrushCommands {

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
   * MediaMpxCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The manager used to load config and media entities.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactoryCreator
   *   The creator used to configure a factory for loading mpx objects.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The key-value factory for storing complete objects.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DataObjectFactoryCreator $dataObjectFactoryCreator, KeyValueFactoryInterface $keyValueFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->keyValueFactory = $keyValueFactory;
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

    // Store an array of media items we touched, so we can clear out their
    // static cache.
    $reset_ids = [];

    // @todo Support fetching the total results via ObjectList.
    $this->io()->title(dt('Importing @type media', ['@type' => $media_type_id]));
    $this->io()->progressStart();

    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    foreach ($results as $index => $mpx_media) {
      // @todo start a transaction.

      // Find any existing media items, or return a new one.
      $results = $this->loadMediaEntities($media_type, $mpx_media);

      // Save the mpx Media item so it's available in getMetadata() in the
      // source plugin.
      $this->keyValueFactory->get('media_mpx_media')->set($mpx_media->getId(), $mpx_media);

      foreach ($results as $media) {
        $media->save();
        $reset_ids[] = $media->id();
      }

      $this->entityTypeManager->getStorage('media')->resetCache($reset_ids);

      $this->io()->progressAdvance();
      $this->logger()->info('Imported @type @uri.', ['@type' => $media_type_id, '@uri' => $mpx_media->getId()]);
    }

    $this->io()->progressFinish();

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

  /**
   * Return the media source plugin for a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type object to load the source plugin for.
   *
   * @return \Drupal\media_mpx\Plugin\media\Source\Media
   *   The source plugin.
   */
  private function loadMediaSource(MediaTypeInterface $media_type): Media {
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $media_source */
    $media_source = $media_type->getSource();
    if (!($media_source instanceof Media)) {
      throw new \RuntimeException(dt('@type is not configured as a mpx Media source.', ['@type' => $media_type->id()]));
    }
    return $media_source;
  }

  /**
   * Load all media entities for a given mpx Media item, or return a new stub.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to load all entities for.
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx Media item to load the associated entities for.
   *
   * @return \Drupal\media\Entity\Media[]
   *   An array of existing media entities or a new media entity.
   */
  private function loadMediaEntities(MediaTypeInterface $media_type, MpxMedia $mpx_media): array {
    $media_source = $this->loadMediaSource($media_type);
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media_storage = $this->entityTypeManager->getStorage('media');
    $results = $media_storage->loadByProperties([$source_field->getName() => (string) $mpx_media->getId()]);

    if (empty($results)) {
      /** @var \Drupal\media\Entity\Media $new_media_entity */
      $new_media_entity = $media_storage->create([
        $this->entityTypeManager->getDefinition('media')
          ->getKey('bundle') => $media_type->id(),
      ]);
      $new_media_entity->set($source_field->getName(), $mpx_media->getId());
      $results = [$new_media_entity];
    }

    return $results;
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
    $media_source = $this->loadMediaSource($media_type);
    $account = $media_source->getAccount();

    $mpx_account = new Account();
    $mpx_account->setId($account->get('account'));

    $factory = $this->dataObjectFactoryCreator->forObjectType($account->getUserEntity(), $media_source::SERVICE_NAME, $media_source::OBJECT_TYPE, $media_source::SCHEMA_VERSION);
    // @todo Remove this when it's made optional upstream.
    // @see https://github.com/Lullabot/mpx-php/issues/78
    $fields = new ByFields();
    $results = $factory->select($fields, $mpx_account);
    return $results;
  }

}
