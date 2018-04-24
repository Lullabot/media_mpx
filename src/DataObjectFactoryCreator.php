<?php

namespace Drupal\media_mpx;

use Drupal\media_mpx\Entity\UserInterface;
use Lullabot\Mpx\DataService\DataObjectFactory as MpxDataObjectFactory;
use Lullabot\Mpx\DataService\DataServiceManager;

/**
 * Create factories used to load object from mpx.
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
   * Construct a new DataObjectFactoryCreator.
   *
   * @param \Lullabot\Mpx\DataService\DataServiceManager $manager
   *   The manager used to discover what mpx objects are available.
   * @param \Drupal\media_mpx\AuthenticatedClientFactory $authenticatedClientFactory
   *   A factory used to generate new authenticated clients.
   */
  public function __construct(DataServiceManager $manager, AuthenticatedClientFactory $authenticatedClientFactory) {
    $this->manager = $manager;
    $this->authenticatedClientFactory = $authenticatedClientFactory;
  }

  /**
   * Create a new \Lullabot\Mpx\DataService\DataObjectFactoryCreator for an mpx object.
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
    return new MpxDataObjectFactory($service, $client);
  }

}
