<?php

namespace Drupal\media_mpx;

use Drupal\media_mpx\Entity\UserInterface;
use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use Lullabot\Mpx\DataService\DataObjectFactory as MpxDataObjectFactory;
use Lullabot\Mpx\DataService\DataServiceManager;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Create factories used to load objects from mpx.
 */
class DataObjectFactoryCreator {

  /**
   * The manager used to discover what mpx objects are available.
   *
   * @var \Lullabot\Mpx\DataService\DataServiceManager
   */
  private $manager;

  /**
   * A factory used to generate new authenticated clients.
   *
   * @var \Drupal\media_mpx\AuthenticatedClientFactory
   */
  private $authenticatedClientFactory;

  /**
   * The metadata cache backend.
   *
   * @var \Psr\Cache\CacheItemPoolInterface
   */
  private $metadataCache;

  /**
   * Construct a new DataObjectFactoryCreator.
   *
   * @param \Lullabot\Mpx\DataService\DataServiceManager $manager
   *   The manager used to discover what mpx objects are available.
   * @param \Drupal\media_mpx\AuthenticatedClientFactory $authenticatedClientFactory
   *   A factory used to generate new authenticated clients.
   * @param \Psr\Cache\CacheItemPoolInterface $metadataCache
   *   The pool for cached class metadata.
   */
  public function __construct(DataServiceManager $manager, AuthenticatedClientFactory $authenticatedClientFactory, CacheItemPoolInterface $metadataCache) {
    $this->manager = $manager;
    $this->authenticatedClientFactory = $authenticatedClientFactory;
    $this->metadataCache = $metadataCache;
  }

  /**
   * Create a new DataObjectFactoryCreator for an mpx object.
   *
   * @param \Drupal\media_mpx\Entity\UserInterface $user
   *   The user to authenticate the connection with.
   * @param string $serviceName
   *   The mpx service name, such as 'Media Data Service'.
   * @param string $objectType
   *   The object type to load, such as 'Media'.
   * @param string $schema
   *   The schema version to use, such as '1.10'.
   *
   * @return \Lullabot\Mpx\DataService\DataObjectFactory
   *   A factory to load and query objects with.
   */
  public function forObjectType(UserInterface $user, string $serviceName, string $objectType, string $schema): MpxDataObjectFactory {
    $service = $this->manager->getDataService($serviceName, $objectType, $schema);
    $client = $this->authenticatedClientFactory->fromUser($user);
    return new MpxDataObjectFactory($service, $client, $this->metadataCache);
  }

  /**
   * Create a \Lullabot\Mpx\DataService\DataObjectFactory from a media source.
   *
   * @param \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface $media_source
   *   The media source to create the factory from.
   *
   * @return \Lullabot\Mpx\DataService\DataObjectFactory
   *   A factory to load and query objects with.
   */
  public function fromMediaSource(MpxMediaSourceInterface $media_source) {
    if (!$service_info = $media_source->getPluginDefinition()['media_mpx']) {
      throw new \InvalidArgumentException('The media source annotation must have a media_mpx key');
    }

    $user = $media_source->getAccount()->getUserEntity();
    return $this->forObjectType($user, $service_info['service_name'], $service_info['object_type'], $service_info['schema_version']);
  }

  /**
   * Return the data service manager used to discover services.
   *
   * @return \Lullabot\Mpx\DataService\DataServiceManager
   *   The data service manager.
   */
  public function getDataServiceManager(): DataServiceManager {
    return $this->manager;
  }

}
