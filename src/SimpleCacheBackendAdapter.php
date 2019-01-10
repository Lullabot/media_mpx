<?php

namespace Drupal\media_mpx;

use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Adapter to use PSR-16 cache backends as a Drupal backend cache.
 *
 * While it is not part of a PSR, it is recommended that the adapted cache
 * implements \Cache\TagInterop\TaggableCacheItemPoolInterface. Otherwise,
 * each cache read will cause a database query to check to see if any tags have
 * been invalidated.
 *
 * @todo This class should be split out into it's own project.
 *
 * @see \Drupal\Core\Cache\ApcuBackend::prepareItem
 */
class SimpleCacheBackendAdapter implements CacheBackendInterface, CacheTagsInvalidatorInterface {

  /**
   * The adapted cache.
   *
   * @var \Psr\SimpleCache\CacheInterface
   */
  protected $cache;

  /**
   * The service used to get the time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The checksum provider to implement tags support.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * SimpleCacheBackendAdapter constructor.
   *
   * @param \Psr\SimpleCache\CacheInterface $cache
   *   The adapted cache.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The service used to get the time.
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
   *   The checksum provider to implement tags support.
   */
  public function __construct(CacheInterface $cache, TimeInterface $time, CacheTagsChecksumInterface $checksumProvider) {
    $this->cache = $cache;
    $this->time = $time;
    $this->checksumProvider = $checksumProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $key = $this->keyFromCid($cid);
    return $this->prepareItem($this->cache->get($key), $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $keys = $this->keysFromCids($cids);
    $items = $this->cache->getMultiple($keys);

    // $items can be an iterable and not just an array, so we can't use array_*
    // functions here.
    $ret = [];
    foreach ($items as $item) {
      // $item is null if it could not be found, but it's an interable so we
      // can't use array_filter().
      if (!$item) {
        continue;
      }

      $item = $this->prepareItem($item, $allow_invalid);
      if ($item) {
        $ret[$item->cid] = $item;
      }
    }

    $cids = array_diff($cids, array_keys($ret));

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    $cache = $this->createCacheItem($cid, $data, $expire, $tags);

    $key = $this->keyFromCid($cid);
    $expire = $this->expireAsRelative($expire);
    $this->cache->set($key, $cache, $expire);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    foreach ($items as $cid => $item) {
      // setMultiple() only supports a single TTL for all items so we have to do
      // individual sets.
      $tags = isset($item['tags']) ? $item['tags'] : [];
      $this->set($cid, $item['data'], $item['expire'], $tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $key = $this->keyFromCid($cid);
    $this->cache->delete($key);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $keys = $this->keysFromCids($cids);
    $this->cache->deleteMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    $this->cache->clear();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->invalidateMultiple([$cid]);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    foreach ($this->getMultiple($cids) as $cache) {
      $this->set($cache->cid, $cache, $this->time->getRequestTime() - 1);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    // Handle invalidateAll() including backends that don't support listing all
    // keys.
    // @see \Drupal\memcache\MemcacheBackend::invalidateAll
    $this->invalidateTags(["simple_cache_adapter"]);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    // PSR-16 does not have an interface for garbage collection.
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    // PSR-16 does not have an interface to delete a bin, but we can at least
    // clear the cache.
    $this->cache->clear();
  }

  /**
   * Marks cache items with any of the specified tags as invalid.
   *
   * @param string[] $tags
   *   The list of tags for which to invalidate cache items.
   */
  public function invalidateTags(array $tags) {
    if ($this->cache instanceof TaggableCacheItemPoolInterface) {
      $this->cache->invalidateTags($tags);
    }
  }

  /**
   * Prepares a cached item.
   *
   * Checks that the item is either permanent or did not expire.
   *
   * @param \stdClass $cache
   *   An item loaded from cache_get() or cache_get_multiple().
   * @param bool $allow_invalid
   *   If TRUE, a cache item may be returned even if it is expired or has been
   *   invalidated. See ::get().
   *
   * @return mixed
   *   The cache item or FALSE if the item expired.
   */
  private function prepareItem(\stdClass $cache, bool $allow_invalid) {
    if (!isset($cache->data)) {
      return FALSE;
    }

    $cache->tags = $cache->tags ? explode(' ', $cache->tags) : [];
    $cache->valid = TRUE;

    // Check if invalidateTags() has been called with any of the entry's tags.
    if (!$this->cache instanceof TaggableCacheItemPoolInterface) {
      if (!$this->checksumProvider->isValid($cache->checksum, $cache->tags)) {
        $cache->valid = FALSE;
      }
    }

    if (!$allow_invalid && !$cache->valid) {
      return FALSE;
    }

    return $cache;
  }

  /**
   * Create a Drupal \stdClass cache item.
   *
   * @param string $cid
   *   The Drupal cache ID.
   * @param mixed $data
   *   The data to cache.
   * @param int $expire
   *   The absolute expiry of the cache item.
   * @param string[] $tags
   *   An array of cache tags.
   *
   * @return \stdClass
   *   The cache item.
   */
  private function createCacheItem($cid, $data, $expire, array $tags): \stdClass {
    // Additional tag to support invalidateAll().
    $tags[] = 'simple_cache_adapter';
    $tags = array_unique($tags);
    // Sort the cache tags so that they are stored consistently in the database.
    sort($tags);

    $cache = new \stdClass();
    $cache->cid = $cid;
    $cache->created = round(microtime(TRUE), 3);
    $cache->expire = $expire;
    $cache->tags = implode(' ', $tags);

    // By default, the database provider is used by Drupal, so save a query if
    // the adapted cache supports tags directly.
    if (!$this->cache instanceof TaggableCacheItemPoolInterface) {
      $cache->checksum = $this->checksumProvider->getCurrentChecksum($tags);
    }

    $cache->data = $data;
    return $cache;
  }

  /**
   * Convert an absolute expiry to a relative time from now.
   *
   * @param int $expire
   *   The absolute timestamp.
   *
   * @return int|null
   *   A relative time if $expire is a timestamp, or NULL if it is
   *   Cache::PERMANENT.
   */
  private function expireAsRelative(int $expire): ?int {
    if ($expire == Cache::PERMANENT) {
      $expire = NULL;
    }
    else {
      $expire = $expire - $this->time->getCurrentTime();
    }

    return $expire;
  }

  /**
   * Hash a cache ID so it is a valid PSR-6 / PSR-16 key.
   *
   * @param string $cid
   *   The Drupal cache ID.
   *
   * @see http://www.php-cache.com/en/latest/introduction/#cache-keys
   *
   * @return string
   *   A safe cache ID.
   */
  private function keyFromCid(string $cid): string {
    return sha1($cid);
  }

  /**
   * Hash multiple cache IDs.
   *
   * @param string[] $cids
   *   An array of Drupal cache IDs.
   *
   * @return array
   *   An array of safe cache IDS.
   */
  private function keysFromCids(array $cids): array {
    $keys = [];
    foreach ($cids as $cid) {
      $keys[] = $this->keyFromCid($cid);
    }
    return $keys;
  }

}
