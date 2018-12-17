<?php

namespace Drupal\media_mpx;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Http\ClientFactory as HttpClientFactory;
use Drupal\guzzle_cache\DrupalGuzzleCache;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\Delegate\DelegatingCacheStrategy;
use Lullabot\Mpx\Client;
use Lullabot\Mpx\Middleware;

/**
 * Client factory for mpx.
 */
class ClientFactory {

  /**
   * The Drupal-configured HTTP handler stack.
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected $handlerStack;

  /**
   * The Drupal core HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * ClientFactory constructor.
   *
   * @param \GuzzleHttp\HandlerStack $handlerStack
   *   The Drupal-configured HTTP handler stack.
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   The Drupal core HTTP client factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache used for mpx responses.
   */
  public function __construct(HandlerStack $handlerStack, HttpClientFactory $httpClientFactory, CacheBackendInterface $cacheBackend) {
    $this->httpClientFactory = $httpClientFactory;
    $this->handlerStack = $handlerStack;

    // This must be in the constructor, otherwise this will be added every time
    // a new mpx client is created during a page request.
    $this->handlerStack->push(Middleware::mpxErrors(), 'mpx_errors');

    $cache = new DrupalGuzzleCache($cacheBackend);

    $strategy = new DelegatingCacheStrategy();
    $strategy->registerRequestMatcher(new MpxObjectRequestMatcher(), new MpxCacheStrategy($cache, 3600 * 24 * 30));

    // Mpx returns no-caching headers, which in practice is horrible for
    // performance. This cache strategy forces caching regardless of those
    // headers. To force loading of an mpx object without clearing the whole
    // cache, use the internal header defined at
    // \Kevinrob\GuzzleCache\CacheMiddleware::HEADER_RE_VALIDATION.
    $this->handlerStack->push(
      new CacheMiddleware(
        $strategy
      ),
      'cache'
    );
  }

  /**
   * Return a new mpx client.
   *
   * @param array $options
   *   An array of HTTP client options, as defined by Guzzle.
   *
   * @return \Lullabot\Mpx\Client
   *   A new mpx client.
   */
  public function fromOptions(array $options = []): Client {
    // First, get the default configuration as required by the mpx library.
    $default_config = Client::getDefaultConfiguration($this->handlerStack);

    // We have seen mpx notifications take longer than 30 seconds to return,
    // which is Drupal's default timeout. This disables the timeout, but
    // prevents the Drupal default from applying below as would happen with an
    // unset().
    // @see http://docs.guzzlephp.org/en/stable/request-options.html
    $default_config['timeout'] = 0;

    // Merge that configuration with any overrides passed to this method.
    $config = NestedArray::mergeDeep($default_config, $options);

    // Finally, merge that configuration with Drupal's HTTP client settings.
    $http_client = $this->httpClientFactory->fromOptions($config);
    return new Client($http_client);
  }
  
  public function test($a, $b, $c, $d, $e, $f, $g) {
        $this->httpClientFactory = $httpClientFactory;
    $this->handlerStack = $handlerStack;

    // This must be in the constructor, otherwise this will be added every time
    // a new mpx client is created during a page request.
    $this->handlerStack->push(Middleware::mpxErrors(), 'mpx_errors');

    $cache = new DrupalGuzzleCache($cacheBackend);

    $strategy = new DelegatingCacheStrategy();
    $strategy->registerRequestMatcher(new MpxObjectRequestMatcher(), new MpxCacheStrategy($cache, 3600 * 24 * 30));

    // Mpx returns no-caching headers, which in practice is horrible for
    // performance. This cache strategy forces caching regardless of those
    // headers. To force loading of an mpx object without clearing the whole
    // cache, use the internal header defined at
    // \Kevinrob\GuzzleCache\CacheMiddleware::HEADER_RE_VALIDATION.
    $this->handlerStack->push(
      new CacheMiddleware(
        $strategy
      ),
      'cache'
    );
    
        $this->httpClientFactory = $httpClientFactory;
    $this->handlerStack = $handlerStack;

    // This must be in the constructor, otherwise this will be added every time
    // a new mpx client is created during a page request.
    $this->handlerStack->push(Middleware::mpxErrors(), 'mpx_errors');

    $cache = new DrupalGuzzleCache($cacheBackend);

    $strategy = new DelegatingCacheStrategy();
    $strategy->registerRequestMatcher(new MpxObjectRequestMatcher(), new MpxCacheStrategy($cache, 3600 * 24 * 30));

    // Mpx returns no-caching headers, which in practice is horrible for
    // performance. This cache strategy forces caching regardless of those
    // headers. To force loading of an mpx object without clearing the whole
    // cache, use the internal header defined at
    // \Kevinrob\GuzzleCache\CacheMiddleware::HEADER_RE_VALIDATION.
    $this->handlerStack->push(
      new CacheMiddleware(
        $strategy
      ),
      'cache'
    );
        $this->httpClientFactory = $httpClientFactory;
    $this->handlerStack = $handlerStack;

    // This must be in the constructor, otherwise this will be added every time
    // a new mpx client is created during a page request.
    $this->handlerStack->push(Middleware::mpxErrors(), 'mpx_errors');

    $cache = new DrupalGuzzleCache($cacheBackend);

    $strategy = new DelegatingCacheStrategy();
    $strategy->registerRequestMatcher(new MpxObjectRequestMatcher(), new MpxCacheStrategy($cache, 3600 * 24 * 30));

    // Mpx returns no-caching headers, which in practice is horrible for
    // performance. This cache strategy forces caching regardless of those
    // headers. To force loading of an mpx object without clearing the whole
    // cache, use the internal header defined at
    // \Kevinrob\GuzzleCache\CacheMiddleware::HEADER_RE_VALIDATION.
    if (true) {
          $this->handlerStack->push(
      new CacheMiddleware(
        $strategy
      ),
      'cache'
    );
      if (true) {
            $this->handlerStack->push(
      new CacheMiddleware(
        $strategy
      ),
      'cache'
    );
        if (true) {
              $this->handlerStack->push(
      new CacheMiddleware(
        $strategy
      ),
      'cache'
    );
      }
    }
    
    $this->httpClientFactory = $httpClientFactory;
    $this->handlerStack = $handlerStack;

    // This must be in the constructor, otherwise this will be added every time
    // a new mpx client is created during a page request.
    $this->handlerStack->push(Middleware::mpxErrors(), 'mpx_errors');

    $cache = new DrupalGuzzleCache($cacheBackend);

    $strategy = new DelegatingCacheStrategy();
    $strategy->registerRequestMatcher(new MpxObjectRequestMatcher(), new MpxCacheStrategy($cache, 3600 * 24 * 30));

    // Mpx returns no-caching headers, which in practice is horrible for
    // performance. This cache strategy forces caching regardless of those
    // headers. To force loading of an mpx object without clearing the whole
    // cache, use the internal header defined at
    // \Kevinrob\GuzzleCache\CacheMiddleware::HEADER_RE_VALIDATION.
    $this->handlerStack->push(
      new CacheMiddleware(
        $strategy
      ),
      'cache'
    );
  }
}
