<?php

namespace Drupal\media_mpx;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use Lullabot\Mpx\DataService\ObjectInterface;

/**
 * Import an mpx item into a media entity.
 */
class DataObjectImporter {

  /**
   * The factory used to store complete mpx objects.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  private $keyValueFactory;

  /**
   * The entity type manager used to load existing media entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * DataObjectImporter constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The factory used to store complete mpx objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager used to load existing media entities.
   */
  public function __construct(KeyValueFactoryInterface $keyValueFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->keyValueFactory = $keyValueFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Import an mpx object into a media entity of the given type.
   *
   * @param \Lullabot\Mpx\DataService\ObjectInterface $mpx_object
   *   The mpx object.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to import to.
   */
  public function importItem(ObjectInterface $mpx_object, MediaTypeInterface $media_type) {
    // @todo Handle POST, PUT, Delete, etc.
    // Store an array of media items we touched, so we can clear out their
    // static cache.
    $reset_ids = [];
    // @todo start a transaction.

    // Find any existing media items, or return a new one.
    $results = $this->loadMediaEntities($media_type, $mpx_object);

    // Save the mpx Media item so it's available in getMetadata() in the
    // source plugin.
    $this->setKeyValue($mpx_object, $media_type);

    foreach ($results as $media) {
      $media->save();
      $reset_ids[] = $media->id();
    }

    $this->entityTypeManager->getStorage('media')->resetCache($reset_ids);
  }

  /**
   * Set an mpx object into the key/value store.
   *
   * @param \Lullabot\Mpx\DataService\ObjectInterface $mpx_object
   *   The object to set in the key-value store.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type the object is associated with.
   */
  public function setKeyValue(ObjectInterface $mpx_object, MediaTypeInterface $media_type) {
    $keyValueStore = $this->keyValueFactory->get($media_type->getSource()
      ->getPluginId());
    $keyValueStore->set($mpx_object->getId(), $mpx_object);
  }

  /**
   * Load all media entities for a given mpx object, or return a new stub.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to load all entities for.
   * @param \Lullabot\Mpx\DataService\ObjectInterface $mpx_object
   *   The mpx object to load the associated entities for.
   *
   * @return \Drupal\media\Entity\Media[]
   *   An array of existing media entities or a new media entity.
   */
  private function loadMediaEntities(MediaTypeInterface $media_type, ObjectInterface $mpx_object): array {
    $media_source = $this->loadMediaSource($media_type);
    $source_field = $media_source->getSourceFieldDefinition($media_type);
    $media_storage = $this->entityTypeManager->getStorage('media');
    $results = $media_storage->loadByProperties([$source_field->getName() => (string) $mpx_object->getId()]);

    // Create a new entity owned by the admin user.
    if (empty($results)) {
      /** @var \Drupal\media\Entity\Media $new_media_entity */
      $new_media_entity = $media_storage->create([
        $this->entityTypeManager->getDefinition('media')
          ->getKey('bundle') => $media_type->id(),
        'uid' => 1,
      ]);
      $new_media_entity->set($source_field->getName(), $mpx_object->getId());
      $results = [$new_media_entity];
    }

    return $results;
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
  public static function loadMediaSource(MediaTypeInterface $media_type): MpxMediaSourceInterface {
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $media_source */
    $media_source = $media_type->getSource();
    if (!($media_source instanceof MpxMediaSourceInterface)) {
      throw new \RuntimeException(dt('@type is not configured as a mpx Media source.', ['@type' => $media_type->id()]));
    }
    return $media_source;
  }

}
