<?php

namespace Drupal\media_mpx\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use function GuzzleHttp\Promise\each_limit;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process mpx notifications.
 *
 * Notifications from mpx do not contain the complete data of the object. Since
 * we have to reload the full object from mpx, we batch each notification into
 * groups of ten. For example, if mpx returns an array of 500 unique
 * notifications, the Drush command will create 50 queue items with 10
 * notifications each. Then, each queue worker will process those ten in
 * parallel.
 *
 * We don't know yet how many parallel connections mpx can handle until they
 * rate limit us.
 *
 * @QueueWorker(
 *   id="media_mpx_notification",
 *   title="mpx Notification queue worker",
 *   cron={
 *     "time"=15
 *   }
 * )
 */
class NotificationQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The factory used to load a complete mpx object.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  protected $dataObjectFactoryCreator;

  /**
   * The class used to import the mpx data into Drupal.
   *
   * @var \Drupal\media_mpx\DataObjectImporter
   */
  protected $importer;

  /**
   * NotificationQueueWorker constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactoryCreator
   *   The factory used to load a complete mpx object.
   * @param \Drupal\media_mpx\DataObjectImporter $importer
   *   The class used to import the mpx data into Drupal.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, DataObjectFactoryCreator $dataObjectFactoryCreator, DataObjectImporter $importer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('media_mpx.data_object_factory_creator'),
      $container->get('media_mpx.data_object_importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /* @var $data \Drupal\media_mpx\Notification[] */

    $promises = [];
    // While all notifications should have the same media type, we don't want to
    // assume that.
    $media_types = [];

    // @todo convert this to a yield.
    foreach ($data as $notification) {
      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
      $mpx_media = $notification->getNotification()->getEntry();

      $media_source = $this->importer::loadMediaSource($notification->getMediaType());
      $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
      $promises[] = $factory->load($mpx_media->getId(), ['headers' => ['Cache-Control' => 'no-cache']]);
      $media_types[] = $notification->getMediaType();
    }

    // Process each request concurrently.
    // @todo Handle individual request rejections by requeuing them to the
    // bottom of the queue.
    each_limit($promises, 10, function ($mpx_media, $index) use ($media_types) {
      $this->importer->importItem($mpx_media, $media_types[$index]);
    })->wait();
  }

}
