<?php

namespace Drupal\media_mpx\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\media\MediaSourceInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\AuthenticatedClientFactory;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Notification;
use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Exception\ConnectException;
use Lullabot\Mpx\DataService\DataServiceManager;
use Psr\Log\LoggerAwareTrait;

/**
 * Processes mpx notifications.
 */
class NotificationQueuer extends DrushCommands {
  use LoggerAwareTrait;

  /**
   * The name of the queue for notifications.
   */
  const MEDIA_MPX_NOTIFICATION_QUEUE = 'media_mpx_notification';

  /**
   * @var \Drupal\media_mpx\AuthenticatedClientFactory
   */
  private $authenticatedClientFactory;

  /**
   * @var \Lullabot\Mpx\DataService\DataServiceManager
   */
  private $dataServiceManager;

  /**
   * @var \Drupal\Core\Queue\QueueFactory
   */
  private $queueFactory;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * NotificationQueuer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\media_mpx\AuthenticatedClientFactory $authenticated_client_factory
   * @param \Lullabot\Mpx\DataService\DataServiceManager $data_service_manager
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   * @param \Drupal\Core\State\StateInterface $state
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AuthenticatedClientFactory $authenticated_client_factory, DataServiceManager $data_service_manager, QueueFactory $queue_factory, StateInterface $state)  {
    $this->entityTypeManager = $entity_type_manager;
    $this->authenticatedClientFactory = $authenticated_client_factory;
    $this->dataServiceManager = $data_service_manager;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
  }

  /**
   * Listen for mpx notifications for a given media type.
   *
   * @param string $media_type_id
   *   The media type ID to import for.
   *
   * @usage media_mpx-listen mpx_video
   *   Listen for notifications for the mpx_video media type.
   *
   * @command media_mpx:listen
   * @aliases mpxl
   */
  public function listen($media_type_id) {
    $media_type = $this->loadMediaType($media_type_id);
    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $notification_id = $this->getNotificationId($media_source);
    $notifications = $this->fetchNotifications($media_source, $notification_id);
    $notifications = $this->filterDuplicateNotifications($notifications);
    $this->queueNotifications($media_type, $notifications);
    $this->state->set($notification_id, end($notifications)->getId());
    $this->io()->progressFinish();
  }

  /**
   * @param \Drupal\media\MediaSourceInterface $media_source
   *
   * @return int|mixed
   */
  protected function getNotificationId(MediaSourceInterface $media_source) {
    // @todo should this really be state?
    $state = \Drupal::state();
    $notification_key = $media_source->getPluginId() . '_notification_id';
    if (!$notification_id = $state->get($notification_key)) {
      // @todo Should we throw a warning?
      $notification_id = -1;
    }

    return $notification_id;
  }

  /**
   * @param $media_source
   * @param $notification_id
   * @param \Symfony\Component\Console\Style\OutputStyle $this->io()
   *
   * @return \Lullabot\Mpx\DataService\Notification[]
   */
  private function fetchNotifications(MpxMediaSourceInterface $media_source, $notification_id): \Lullabot\Mpx\DataService\Notification {
    $account = $media_source->getAccount();

    $client = $this->authenticatedClientFactory->fromUser($account->getUserEntity());
    $definition = $media_source->getPluginDefinition()['media_mpx'];
    $service = $this->dataServiceManager->getDataService($definition['service_name'], $definition['object_type'], $definition['schema_version']);
    // @todo Fixup clientid
    $listener = new \Lullabot\Mpx\DataService\NotificationListener($client, $service, 'drush-drupal8-mpx');

    $promise = $listener->listen($notification_id);
    $this->io()->note(dt('Waiting for a notification from mpx after notification ID @id...', ['@id' => $notification_id]));
    try {
      /** @var \Lullabot\Mpx\DataService\Notification[] $notifications */
      $notifications = $promise->wait();
    } catch (ConnectException $e) {
      // This may be a timeout if no notifications are available. However, there
      // is no good method from the exception to determine if a timeout
      // occurred.
      if (strpos($e->getMessage(), 'cURL error 28') !== FALSE) {
        $this->logger()->info('A timeout occurred while waiting for notifications. This is expected when no content is changing in mpx. No action is required.');
      }

      // Some other connection exception occurred, so throw that up.
      throw $e;
    }
    return $notifications;
}

  /**
   * @param $notifications
   *
   * @return array
   */
  private function filterDuplicateNotifications($notifications): array {
    $seen_ids = [];
    $notifications = array_filter($notifications, function ($notification) use (&$seen_ids) {
      /** @var \Lullabot\Mpx\DataService\Notification $notification */
      $id = (string) $notification->getEntry()->getId();
      if (isset($seen_ids[$id])) {
        return FALSE;
      }

      $this->logger()->info(dt('Queuing @method notification for object @id', [
        '@method' => $notification->getMethod(),
        '@id' => $id
      ]));
      $seen_ids[$id] = TRUE;
      return TRUE;
    });
    return $notifications;
}

  /**
   * @param \Drupal\media\MediaTypeInterface $media_type
   * @param \Symfony\Component\Console\Style\OutputStyle $this->io()
   * @param $notifications
   */
  private function queueNotifications(MediaTypeInterface $media_type, $notifications) {
    // @todo format_plural().
    $this->io()->note(dt('Processing @count notifications', ['@count' => count($notifications)]));
    $this->io()->progressStart(count($notifications));

    $chunks = array_chunk($notifications, 10);
    $queue = $this->queueFactory->get(self::MEDIA_MPX_NOTIFICATION_QUEUE);
    foreach ($chunks as $chunk) {
      $items = [];
      foreach ($chunk as $notification) {
        $items[] = new Notification($notification, $media_type);
      }
      $queue->createItem($items);
      $this->io()->progressAdvance();
    }
  }

  /**
   * Load the media type object for a given media type ID.
   *
   * @param string $media_type_id
   *   The media type ID.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   The loaded media type object.
   */
  private function loadMediaType(string $media_type_id): MediaTypeInterface {
    $bundle_type = $this->entityTypeManager->getDefinition('media')->getBundleEntityType();
    /* @var $media_type \Drupal\media\MediaTypeInterface */
    if (!$media_type = $this->entityTypeManager->getStorage($bundle_type)
      ->load($media_type_id)) {
      // Normally you wouldn't translate exception text, but Drush does it in
      // it's own commands.
      // @see https://github.com/drush-ops/drush/blob/76a28373e7d3bdd708ab54d54f0e686370b46506/examples/Commands/PolicyCommands.php#L37
      throw new \InvalidArgumentException(dt('The media type @type does not exist.', ['@type' => $media_type_id]));
    }
    return $media_type;
  }

}
