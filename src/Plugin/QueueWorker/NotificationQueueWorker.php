<?php

namespace Drupal\media_mpx\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\MpxLogger;
use GuzzleHttp\Exception\TransferException;
use function GuzzleHttp\Promise\each_limit;
use Psr\Log\LoggerInterface;
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
   * A specialized logger for mpx errors.
   *
   * @var \Drupal\media_mpx\MpxLogger
   */
  protected $mpxLogger;

  /**
   * The system logger to log failed imports.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
   * @param \Drupal\media_mpx\MpxLogger $mpx_logger
   *   The mpx error specific logger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The system logger.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, DataObjectFactoryCreator $dataObjectFactoryCreator, DataObjectImporter $importer, MpxLogger $mpx_logger, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->importer = $importer;
    $this->mpxLogger = $mpx_logger;
    $this->logger = $logger;
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
      $container->get('media_mpx.data_object_importer'),
      $container->get('media_mpx.exception_logger'),
      $container->get('logger.channel.media_mpx')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /* @var $data \Drupal\media_mpx\Notification[] */

    // All notifications in the same queue item have the same media type.
    // @see \Drupal\media_mpx\Commands\NotificationQueuer::queueNotifications.
    $media_type = $data[0]->getMediaType();

    // Process each request concurrently.
    // @todo Handle individual request rejections by requeuing them to the
    // bottom of the queue.
    each_limit($this->yieldLoads($data), 10, function ($mpx_media) use ($media_type) {
      $this->importer->importItem($mpx_media, $media_type);
    }, function ($reason) {
      if ($reason instanceof TransferException) {
        $this->mpxLogger->logException($reason);
      }
      elseif ($reason instanceof \Exception) {
        $this->mpxLogger->watchdogException($reason);
      }
      else {
        $this->logger->error('An error occurred processing an mpx notification: %reason', ['%reason' => (string) $reason]);
      }
    })->wait();
  }

  /**
   * Yield requests to load a media item.
   *
   * This is primarily for compatibility with an upstream Guzzle patch that
   * fixes Curl's multi handler.
   *
   * @param \Drupal\media_mpx\Notification[] $notifications
   *   The notifications to yield requests from.
   *
   * @see https://github.com/guzzle/guzzle/pull/2001
   *
   * @codingStandardsIgnoreStart
   * https://www.drupal.org/project/coder/issues/2906931
   *
   * @return \Generator
   *   A generator that yields promises to a loaded mpx media object.
   * @codingStandardsIgnoreEnd
   */
  private function yieldLoads(array $notifications): \Generator {
    foreach ($notifications as $notification) {
      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
      $mpx_media = $notification->getNotification()->getEntry();
      $method = $notification->getNotification()->getMethod()?:"get";
      switch ($method) {
        case "delete":
          $this->importer->unpublishItem($mpx_media, $notification->getMediaType());
          break;

        default:
          $media_source = $this->importer::loadMediaSource($notification->getMediaType());
          $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
          yield $factory->load($mpx_media->getId(), ['headers' => ['Cache-Control' => 'no-cache']]);
      }

    }
  }

}
