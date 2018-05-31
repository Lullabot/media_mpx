<?php

namespace Drupal\media_mpx;

use Kevinrob\GuzzleCache\Strategy\Delegate\RequestMatcherInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Request matcher to match on individual object loads.
 *
 * We intentionally do not match on object-list requests as their results can
 * vary significantly over time, such as when mpx content is created or
 * modified.
 */
class MpxObjectRequestMatcher implements RequestMatcherInterface {

  /**
   * {@inheritdoc}
   */
  public function matches(RequestInterface $request) {
    // Match on all data services loading a single object.
    $parts = explode('/', $request->getUri()->getPath());
    return isset($parts[4]) && $parts[2] == 'data' && is_numeric($parts[4]);
  }

}
