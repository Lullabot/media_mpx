<?php

namespace Drupal\media_mpx;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\media_mpx\Commands\MpxImporter;
use Drupal\media_mpx\Commands\NotificationQueuer;
use Drupal\migrate\Event\MigrateEvents;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Drush command service provider.
 *
 * Drush bootstraps command services even in core commands like updatedb. If a
 * command depends on a new module, Drush never gets bootstrapped far enough
 * to enable the module. This file replaces a typical drush.services.yml file.
 */
class MediaMpxServiceProvider implements ServiceProviderInterface, ServiceModifierInterface {

  /**
   * Classes that must be available to add our drush commands.
   */
  const CLASS_DEPENDENCIES = [
    '\Drush\Drush',
    '\Drupal\guzzle_cache\DrupalGuzzleCache',
  ];

  /**
   * Registers services to the container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder to register services to.
   */
  public function register(ContainerBuilder $container) {
    // We need to use the string here because otherwise the Drush class will
    // not be available to dereference with ::class.
    foreach (self::CLASS_DEPENDENCIES as $class) {
      if (!class_exists($class)) {
        return;
      }
    }

    $definitions = [];
    $definitions['media_mpx.importer'] = $this->importerCommand();
    $definitions['media_mpx.notification_queuer'] = $this->listenerCommand();

    $container->addDefinitions($definitions);
  }

  /**
   * Alters registered services in the container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder to register services to.
   */
  public function alter(ContainerBuilder $container) {
    // Remove this service if the migrate module is not enabled.
    // Checks by the existance of a class.
    if (!class_exists(MigrateEvents::class)) {
      $container->removeDefinition('media_mpx.event_subscriber');
    }
  }

  /**
   * Return the importer command definition.
   *
   * @return \Symfony\Component\DependencyInjection\Definition
   *   The command definition.
   */
  private function importerCommand(): Definition {
    $arguments = [
      'media_mpx.repository.mpx_media_types',
      'media_mpx.data_object_factory_creator',
      'media_mpx.data_object_importer',
      'media_mpx.service.queue_video_imports',
    ];
    $definition = new Definition(MpxImporter::class, $this->reference($arguments));
    $definition->addTag('drush.command');
    return $definition;
  }

  /**
   * Convert all strings to service references.
   *
   * @param string[] $arguments
   *   An array of service IDs.
   *
   * @return \Symfony\Component\DependencyInjection\Reference[]
   *   An array of service references.
   */
  private function reference(array $arguments): array {
    $references = [];
    foreach ($arguments as $id) {
      $references[] = new Reference($id);
    }
    return $references;
  }

  /**
   * Return the Drush notification listener command definition.
   *
   * @return \Symfony\Component\DependencyInjection\Definition
   *   The service definition.
   */
  private function listenerCommand(): Definition {
    $arguments = [
      'entity_type.manager',
      'queue',
      'media_mpx.notification_listener',
    ];
    $definition = new Definition(NotificationQueuer::class, $this->reference($arguments));
    $definition->addTag('drush.command');
    return $definition;
  }

}
