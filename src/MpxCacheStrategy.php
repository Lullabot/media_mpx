<?php

namespace Drupal\media_mpx;

use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Psr\Http\Message\RequestInterface;
use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\parse_query;

/**
 * A cache strategy for mpx objects.
 *
 * This class extends from the greedy cache strategy as mpx sends a max-age of
 * zero. Unfortunately, GET requests on media are at least 500ms, which isn't
 * fast enough to rely on a no-cache strategy.
 */
class MpxCacheStrategy extends GreedyCacheStrategy {

  /**
   * {@inheritdoc}
   */
  protected function getCacheKey(RequestInterface $request, KeyValueHttpHeader $varyHeaders = NULL) {
    $uri = $request->getUri();
    // @phpstan-ignore-next-line
    $query = parse_query($uri->getQuery());

    // Mpx tokens are only allowed in the URL and not as a header. Since tokens
    // will vary over time, we need to remove that from the cache key.
    unset($query['token']);

    // Normalize the cache key by sorting.
    ksort($query);

    // @phpstan-ignore-next-line
    $withoutToken = $request->withUri($uri->withQuery(build_query($query)));
    return parent::getCacheKey($withoutToken, $varyHeaders);
  }

  /**
   * Return a CacheEntry or null if no cache.
   *
   * GreedyCacheStrategy does not call the parent method in
   * PrivateCacheStrategy, which is where the no-cache request header is
   * processed. This copy of the method is nearly identical, except it also
   * removes a URL check (which is required because mpx forces tokens to be
   * in the URL and not in a header).
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request being processed.
   *
   * @see \Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy::fetch
   *
   * @return \Kevinrob\GuzzleCache\CacheEntry|null
   *   The cache entry, or NULL if one does not exist.
   */
  public function fetch(RequestInterface $request) {
    /** @var int|null $maxAge */
    $maxAge = NULL;

    if ($request->hasHeader('Cache-Control')) {
      $reqCacheControl = new KeyValueHttpHeader($request->getHeader('Cache-Control'));
      if ($reqCacheControl->has('no-cache')) {
        // Can't return cache.
        return NULL;
      }

      $maxAge = $reqCacheControl->get('max-age', NULL);
    }
    elseif ($request->hasHeader('Pragma')) {
      $pragma = new KeyValueHttpHeader($request->getHeader('Pragma'));
      if ($pragma->has('no-cache')) {
        // Can't return cache.
        return NULL;
      }
    }

    $cache = $this->storage->fetch($this->getCacheKey($request));
    if ($cache !== NULL) {
      $varyHeaders = $cache->getVaryHeaders();

      // Vary headers exist from a previous response, check if we have a cache
      // that matches those headers.
      if (!$varyHeaders->isEmpty()) {
        $cache = $this->storage->fetch($this->getCacheKey($request, $varyHeaders));

        if (!$cache) {
          return NULL;
        }
      }

      // This is where the original method checks the original request URL.
      if ($maxAge !== NULL) {
        if ($cache->getAge() > $maxAge) {
          // Cache entry is too old for the request requirements!
          return NULL;
        }
      }

      if (!$cache->isVaryEquals($request)) {
        return NULL;
      }
    }

    return $cache;
  }

}
