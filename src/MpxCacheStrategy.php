<?php

namespace Drupal\media_mpx;

use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\parse_query;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Psr\Http\Message\RequestInterface;

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
    $query = parse_query($uri->getQuery());

    // Mpx tokens are only allowed in the URL and not as a header. Since tokens
    // will vary over time, we need to remove that from the cache key.
    unset($query['token']);

    // Normalize the cache key by sorting.
    ksort($query);

    $withoutToken = $request->withUri($uri->withQuery(build_query($query)));
    return parent::getCacheKey($withoutToken, $varyHeaders);
  }

}
