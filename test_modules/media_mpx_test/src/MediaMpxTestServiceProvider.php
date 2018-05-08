<?php

namespace Drupal\media_mpx_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider to replace the mpx client with a mock client.
 */
class MediaMpxTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);
    $definition = $container->getDefinition('media_mpx.client_factory');
    $definition->setClass(MockClientFactory::class);
    $definition->setArguments([
      new Reference('state'),
      new Reference('http_client_factory'),
    ]);
  }

}
