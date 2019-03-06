<?php

namespace Drupal\media_mpx;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\guzzle_cache\DrupalGuzzleCache;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\Event\ImportEvent;
use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use function GuzzleHttp\Psr7\build_query;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Lullabot\Mpx\DataService\ObjectInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Import an mpx item into a media entity.
 */
class DataObjectImporter {

  /**
   * The request headers to use for our injected cache responses.
   */
  const REQUEST_HEADERS = [
    'Accept' =>
      [
        'application/json',
      ],
    'Content-Type' =>
      [
        'application/json',
      ],
    'Host' =>
      [
        'read.data.media.theplatform.com',
      ],
  ];

  /**
   * The entity type manager used to load existing media entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The mpx cache strategy for injecting cache items.
   *
   * @var \Drupal\media_mpx\MpxCacheStrategy
   */
  private $cache;

  /**
   * The system event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * DataObjectImporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager used to load existing media entities.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The system event dispatcher.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend to store HTTP responses in.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher, CacheBackendInterface $cacheBackend) {
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->cache = new MpxCacheStrategy(new DrupalGuzzleCache($cacheBackend), 3600 * 24 * 30);
  }

  /**
   * Import an mpx object into a media entity of the given type.
   *
   * @param \Lullabot\Mpx\DataService\ObjectInterface $mpx_object
   *   The mpx object.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to import to.
   *
   * @return \Drupal\media\MediaInterface[]
   *   The array of media entities that were imported.
   */
  public function importItem(ObjectInterface $mpx_object, MediaTypeInterface $media_type): array {
    // @todo Handle POST, PUT, Delete, etc.
    // Store an array of media items we touched, so we can clear out their
    // static cache.
    $reset_ids = [];
    // @todo start a transaction.

    // Find any existing media items, or return a new one.
    $results = $this->loadMediaEntities($media_type, $mpx_object);

    // Allow other modules to alter the media entities as they are imported.
    $event = new ImportEvent($mpx_object, $results);
    $this->eventDispatcher->dispatch(ImportEvent::IMPORT, $event);
    $results = $event->getEntities();

    foreach ($results as $media) {
      // @todo This can be replaced by calling $media->updateMetadata() when
      // https://www.drupal.org/project/drupal/issues/2878119 is merged.
      $this->updateMetadata($media);
      $media->save();
      $reset_ids[] = $media->id();
    }

    return $results;
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

  /**
   * Update the media entity overwriting fields with remote data.
   *
   * This code and all code called from it is adapted from an upstream patch.
   * Since the upstream patch marks updateMetadata as internal, we copy the code
   * here.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to update.
   *
   * @see https://www.drupal.org/project/drupal/issues/2878119
   */
  protected function updateMetadata(MediaInterface $media): void {
    foreach (array_keys($media->getTranslationLanguages()) as $langcode) {
      $translation = $media->getTranslation($langcode);
      $field_map = $media->bundle->entity->getFieldMap();
      $source = $media->getSource();
      foreach ($field_map as $attribute_name => $field_name) {
        $media->set($field_name, $source->getMetadata($media, $attribute_name));
      }
      $this->updateThumbnail($media);

      // If the media item does not have a title yet, get a default name from
      // the metadata.
      if ($translation->get('name')->isEmpty()) {
        $media_source = $media->getSource();
        $translation->setName($media_source->getMetadata($media, $media_source->getPluginDefinition()['default_name_metadata_attribute']));
      }
    }
  }

  /**
   * Update the thumbnail for a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to update.
   *
   * @see https://www.drupal.org/project/drupal/issues/2878119
   */
  protected function updateThumbnail(MediaInterface $media): void {
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $source */
    $source = $media->getSource();
    $plugin_definition = $source->getPluginDefinition();

    $thumbnail_uri = $source->getMetadata($media, $plugin_definition['thumbnail_uri_metadata_attribute']);

    if (!$thumbnail_uri) {
      return;
    }

    $file = $this->createFileForThumbnail($media, $thumbnail_uri);

    if ($source->doSaveThumbnailAsMedia()) {
      $this->referenceThumbnailAsMedia($media, $file);
    }
    else {
      $this->referenceThumbnailAsFile($media, $file);
    }
  }

  /**
   * Return a file entity for the given URI.
   *
   * URI is assumed to be a public URI for an image already on disk. If there is
   * already a file entity for the URI it's returned, otherwise one is created
   * and returned.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being updated.
   * @param string $thumbnail_uri
   *   URI to the thumbnail. Must be a public URI.
   *
   * @return \Drupal\file\FileInterface
   *   File entity that was either created or found for the given thumbnail URI.
   */
  protected function createFileForThumbnail(MediaInterface $media, $thumbnail_uri) {
    $values = [
      'uri' => $thumbnail_uri,
    ];

    $file_storage = $this->entityTypeManager->getStorage('file');

    /** @var \Drupal\file\FileInterface[] $existing */
    $existing = $file_storage->loadByProperties($values);
    if ($existing) {
      $file = reset($existing);
    }
    else {
      /** @var \Drupal\file\FileInterface $file */
      $file = $file_storage->create($values);
      if ($owner = $media->getOwner()) {
        $file->setOwner($owner);
      }
      $file->setPermanent();
      $file->save();
    }

    return $file;
  }

  /**
   * Reference the thumbnail using as an image media entity.
   *
   * As defined by the source plugin for the given media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Mpx video media entity.
   * @param \Drupal\file\FileInterface $file
   *   Thumbnail file entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function referenceThumbnailAsMedia(MediaInterface $media, FileInterface $file) {
    $source = $media->getSource();
    $source_configuration = $source->getConfiguration();

    // Look up whether we already have a media entity corresponding to the given
    // file.
    $media_storage = $this->entityTypeManager->getStorage('media');
    $media_query = $media_storage->getQuery();
    $existing = $media_query->condition('bundle', $source_configuration['media_image_bundle'])
      ->condition("{$source_configuration['media_image_field']}.target_id", $file->id())
      ->execute();
    if ($existing) {
      $media_image = $media_storage->load(reset($existing));
    }
    else {
      // Save a media entity for the thumbnail image.
      $media_image = Media::create([
        'bundle' => $source_configuration['media_image_bundle'],
        'name' => $file->label(),
        $source_configuration['media_image_field'] => [
          [
            'target_id' => $file->id(),
            'alt' => $this->getThumbnailAltForMedia($media),
            'title' => $this->getThumbnailTitleForMedia($media),
          ],
        ],
      ]);
      $media_image->save();
    }
    // Set a reference to the newly saved thumbnail media entity on the video
    // media entity.
    $media->{$source_configuration['media_image_entity_reference_field']} = [
      ['target_id' => $media_image->id()],
    ];
  }

  /**
   * Reference the thumbnail using the default thumbnail file entity reference.
   *
   * Using the thumbnail field defined for all media types by core. This just
   * sets the thumbnail field on the given mpx media and assumes that the caller
   * will save the changes.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Mpx video media entity.
   * @param \Drupal\file\FileInterface $file
   *   Thumbnail file entity.
   */
  protected function referenceThumbnailAsFile(MediaInterface $media, FileInterface $file) {
    $media->thumbnail = [
      [
        'target_id' => $file->id(),
        'alt' => $this->getThumbnailAltForMedia($media),
        'title' => $this->getThumbnailTitleForMedia($media),
      ],
    ];
  }

  /**
   * Get the alt text for the given mpx video's thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Mpx video media entity.
   *
   * @return string
   *   Thumbnail alt text.
   */
  protected function getThumbnailAltForMedia(MediaInterface $media) {
    $source = $media->getSource();
    $plugin_definition = $source->getPluginDefinition();

    if (!empty($plugin_definition['thumbnail_alt_metadata_attribute'])) {
      return $source->getMetadata($media, $plugin_definition['thumbnail_alt_metadata_attribute']);
    }
    else {
      return $media->t('Thumbnail', [], ['langcode' => $media->langcode->value]);
    }
  }

  /**
   * Get the title text for the given mpx video's thumbnail.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Mpx video media entity.
   *
   * @return string
   *   Thumbnail title text.
   */
  protected function getThumbnailTitleForMedia(MediaInterface $media) {
    $source = $media->getSource();
    $plugin_definition = $source->getPluginDefinition();

    if (!empty($plugin_definition['thumbnail_title_metadata_attribute'])) {
      return $source->getMetadata($media, $plugin_definition['thumbnail_title_metadata_attribute']);
    }
    else {
      return $media->label();
    }
  }

  /**
   * Inject a single mpx item into the response cache.
   *
   * @param \Lullabot\Mpx\DataService\ObjectInterface $item
   *   The object being injected.
   * @param array $service_info
   *   The service definition from the media source plugin, containing a
   *   'schema_version' key.
   */
  public function cacheItem(ObjectInterface $item, array $service_info) {
    $query = [
      'form' => 'cjson',
      'schema' => $service_info['schema_version'],
    ];
    $encoded = \GuzzleHttp\json_encode($item->getJson());

    $uri = $item->getId()->withScheme('https')->withQuery(build_query($query));
    $request = new Request('GET', $uri, static::REQUEST_HEADERS);
    $response_headers = $this->getResponseHeaders($encoded);
    $response = new Response(200, $response_headers, $encoded);
    $this->cache->cache($request, $response);
  }

  /**
   * Return response headers for a single encoded entry item.
   *
   * @param string $encoded
   *   The encoded item.
   *
   * @return array
   *   An array of response headers.
   */
  private function getResponseHeaders($encoded): array {
    $response_headers = [
      'Access-Control-Allow-Origin' =>
        [
          '*',
        ],
      'Cache-Control' =>
        [
          'max-age=0',
        ],
      'Date' =>
        [
          gmdate('D, d M Y H:i:s \G\M\T', time()),
        ],
      'Content-Type' =>
        [
          'application/json; charset=UTF-8',
        ],
      'Content-Length' =>
        [
          strlen($encoded),
        ],
    ];
    return $response_headers;
  }

}
