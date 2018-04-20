<?php

namespace Drupal\media_mpx;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\Plugin\media\Source\Media;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;

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
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx media object.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to import to.
   */
  public function importItem(MpxMedia $mpx_media, MediaTypeInterface $media_type) {
    // @todo Handle POST, PUT, Delete, etc.
    // Store an array of media items we touched, so we can clear out their
    // static cache.
    $reset_ids = [];
    // @todo start a transaction.

    // Find any existing media items, or return a new one.
    $results = $this->loadMediaEntities($media_type, $mpx_media);

    // Save the mpx Media item so it's available in getMetadata() in the
    // source plugin.
    $this->keyValueFactory->get('media_mpx_media')
      ->set($mpx_media->getId(), $mpx_media);

    foreach ($results as $media) {
      $media->save();
      $reset_ids[] = $media->id();
    }

    $this->entityTypeManager->getStorage('media')->resetCache($reset_ids);
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
   * Return the media source plugin for a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type object to load the source plugin for.
   *
   * @return \Drupal\media_mpx\Plugin\media\Source\Media
   *   The source plugin.
   */
  public static function loadMediaSource(MediaTypeInterface $media_type): Media {
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $media_source */
    $media_source = $media_type->getSource();
    if (!($media_source instanceof Media)) {
      throw new \RuntimeException(dt('@type is not configured as a mpx Media source.', ['@type' => $media_type->id()]));
    }
    return $media_source;
  }

}
