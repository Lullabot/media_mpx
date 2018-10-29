<?php

namespace Drupal\media_mpx;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\guzzle_cache\DrupalGuzzleCache;
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
      $media->save();
      $reset_ids[] = $media->id();
    }

    return $results;
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
