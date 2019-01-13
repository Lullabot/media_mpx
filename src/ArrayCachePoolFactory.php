<?php

namespace Drupal\media_mpx;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;

/**
 * Factory that returns an adapted ArrayCachePool.
 */
class ArrayCachePoolFactory implements CacheFactoryInterface {

  /**
   * The system time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The tag checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * The maximum number of items to cache at once.
   *
   * @var int
   */
  protected $limit;

  /**
   * An array of instantiated cache pools.
   *
   * @var \Drupal\media_mpx\SimpleCacheBackendAdapter
   */
  protected $bins = [];

  /**
   * ArrayCachePoolFactory constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The system time service.
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksumProvider
   *   The tag checksum provider.
   * @param int|null $limit
   *   (optional) The maximum number of items to cache at once.
   */
  public function __construct(TimeInterface $time, CacheTagsChecksumInterface $checksumProvider, int $limit = NULL) {
    $this->time = $time;
    $this->checksumProvider = $checksumProvider;
    $this->limit = $limit;
  }

  /**
   * Gets a cache backend class for a given cache bin.
   *
   * @param string $bin
   *   The cache bin for which a cache backend object should be returned.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache backend object associated with the specified bin.
   */
  public function get($bin) {
    if (!isset($this->bins[$bin])) {
      $cache = new ArrayCachePool($this->limit);
      $adapted = new SimpleCacheBackendAdapter($cache, $this->time, $this->checksumProvider);
      $this->bins[$bin] = $adapted;
    }

    return $this->bins[$bin];
  }

}
