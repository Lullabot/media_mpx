<?php

namespace Drupal\media_mpx;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Http\ClientFactory as HttpClientFactory;
use GuzzleHttp\HandlerStack;
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
   */
  public function __construct(HandlerStack $handlerStack, HttpClientFactory $httpClientFactory) {
    $this->httpClientFactory = $httpClientFactory;
    $this->handlerStack = $handlerStack;

    // This must be in the constructor, otherwise this will be added every time
    // a new mpx client is created during a page request.
    $this->handlerStack->push(Middleware::mpxErrors(), 'mpx_errors');
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

    // Merge that configuration with any overrides passed to this method.
    $config = NestedArray::mergeDeep($default_config, $options);

    // Finally, merge that configuration with Drupal's HTTP client settings.
    $http_client = $this->httpClientFactory->fromOptions($config);
    return new Client($http_client);
  }

}
