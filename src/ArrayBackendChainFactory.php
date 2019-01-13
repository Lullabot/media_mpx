<?php

namespace Drupal\media_mpx;

use Drupal\Core\Cache\BackendChain;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Factory that returns an inconsistent array-backed cache.
 */
class ArrayBackendChainFactory implements CacheFactoryInterface {

  use ContainerAwareTrait;

  /**
   * The name of the consistent backend cache, typically the database.
   *
   * @var string
   */
  protected $consistentServiceName;

  /**
   * ArrayBackendChainFactory constructor.
   *
   * @param \Drupal\Core\Site\Settings|null $settings
   *   (optional) The system settings containing cache configuration.
   * @param string|null $consistent_service_name
   *   The name of the consistent backend cache service.
   */
  public function __construct(Settings $settings = NULL, string $consistent_service_name = NULL) {
    // Default the consistent backend to the site's default backend.
    if (!isset($consistent_service_name)) {
      $cache_settings = isset($settings) ? $settings->get('cache') : [];
      $consistent_service_name = isset($cache_settings['default']) ? $cache_settings['default'] : 'cache.backend.database';
    }

    $this->consistentServiceName = $consistent_service_name;
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
    $chain = new BackendChain($bin);
    $chain->appendBackend($this->container->get('cache.backend.memory')->get($bin));
    $chain->appendBackend($this->container->get($this->consistentServiceName)->get($bin));

    return $chain;
  }

}
