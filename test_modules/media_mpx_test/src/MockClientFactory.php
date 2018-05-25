<?php

namespace Drupal\media_mpx_test;

use Drupal\Core\Http\ClientFactory as DrupalClientFactory;
use Drupal\Core\State\StateInterface;
use Drupal\media_mpx\ClientFactory;
use GuzzleHttp\HandlerStack;

/**
 * Factory to return a mock client.
 */
class MockClientFactory extends ClientFactory {

  /**
   * The mock handler that stores the response queue in state.
   *
   * @var \Drupal\media_mpx_test\MockStateHandler
   */
  private $mockHandler;

  /**
   * Construct a new MockClientFactory.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system to store the request queue in.
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   The Drupal HTTP client factory.
   */
  public function __construct(StateInterface $state, DrupalClientFactory $httpClientFactory) {
    $mockHandler = new MockStateHandler($state);
    $stack = HandlerStack::create($mockHandler);
    $this->mockHandler = $mockHandler;
    parent::__construct($stack, $httpClientFactory, \Drupal::cache('media_mpx_http'));
  }

  /**
   * Return the mock handler for all requests.
   *
   * @return \Drupal\media_mpx_test\MockStateHandler
   *   The mock state handler.
   */
  public function getMockHandler(): MockStateHandler {
    return $this->mockHandler;
  }

}
