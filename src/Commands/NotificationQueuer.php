<?php

namespace Drupal\media_mpx\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Notification;
use Drupal\media_mpx\NotificationListener;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerAwareTrait;
use Lullabot\Mpx\DataService\Notification as MpxNotification;

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
   * The factory to load the mpx notification queue.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  private $queueFactory;

  /**
   * The entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The Drupal mpx notification listener.
   *
   * @var \Drupal\media_mpx\NotificationListener
   */
  private $listener;

  /**
   * NotificationQueuer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager interface.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The Drupal mpx notification listener.
   *   The factory to load the mpx notification queue.
   * @param \Drupal\media_mpx\NotificationListener $listener
   *   The notification listener.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory, NotificationListener $listener) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->listener = $listener;
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
    // First, we find the last notification ID.
    $media_type = $this->loadMediaType($media_type_id);
    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $notification_id = $this->listener->getNotificationId($media_type_id);

    // Next, we fetch notifications, removing duplicates (such as multiple saves
    // of an mpx object in a row).
    $this->io()->note(dt('Waiting for a notification from mpx after notification ID @id...', ['@id' => $notification_id]));
    $notifications = $this->listener->listen($media_source, $notification_id);

    // Keep track of the initial count of notifications so we can know if we
    // filtered down to an empty array.
    $initial_count = count($notifications);

    // We need a reference to the last notification so we can set the last
    // notification ID even if all notifications are filtered out.
    $last_notification = end($notifications);

    // Check to see if there were no notifications and we got a sync response.
    // @see https://docs.theplatform.com/help/wsf-subscribing-to-change-notifications#tp-toc10
    if ($initial_count === 1 && $last_notification->isSyncResponse()) {
      $this->logger()->info(dt('All notifications have been processed.'));
      $this->listener->setNotificationId($media_type_id, $last_notification);
      return;
    }

    $notifications = $this->filterDuplicateNotifications($notifications);
    $notifications = $this->filterByDate($notifications, $media_type_id);

    if (empty($notifications) && $initial_count) {
      $this->io()->note(dt('All notifications were skipped as newer data has already been imported.'));
    }
    else {
      // Take the notifications and store them in the queue for processing
      // later.
      $this->queueNotifications($media_type, $notifications);
    }

    // Let the next listen call start from where we left off.
    $this->listener->setNotificationId($media_type_id, $last_notification);
  }

  /**
   * Removes notifications that are older than the entities that reference them.
   *
   * @param \Lullabot\Mpx\DataService\Notification[] $notifications
   *   An array of notifications.
   * @param string $media_type_id
   *   The media type being imported.
   *
   * @return \Lullabot\Mpx\DataService\Notification[]
   *   The filtered array.
   */
  private function filterByDate(array $notifications, string $media_type_id): array {
    /* @todo I borrowed this search code from the DataObjectImporter
     * maybe there is a better place to publicly store this functionality. */

    /** @var \Drupal\Media\MediaStorage $media_storage */
    $media_storage = \Drupal::entityManager()->getStorage('media');
    /** @var \Drupal\Media\Entity\MediaType $media_type */
    $media_type = MediaType::load($media_type_id);
    $source = $media_type->getSource();
    $source_field = $source->getSourceFieldDefinition($media_type)->getName();

    $notifications = array_filter($notifications, function (MpxNotification $notification) use ($media_storage, $source_field) {
      // Always keep delete notifications so we can log them.
      if ($notification->getMethod() == 'delete') {
        return TRUE;
      }

      $notificationDate = $notification->getEntry()->getUpdated()->format("U");
      $notificationId = (string) $notification->getEntry()->getId();
      $entities = $media_storage->loadByProperties([$source_field => $notificationId]);

      // The updates must have to do with something new so keep them included.
      if (empty($entities)) {
        return TRUE;
      }

      /* If there exists an entity that hasn't been updated since the
      notification was updated keep the notification. */
      // @todo check for the use case where an entity was changed in drupal by a user
      foreach ($entities as $entity) {
        /** @var \Drupal\Media\Entity\Media $entity */
        if ($entity->getChangedTime() < $notificationDate) {
          return TRUE;
        };
      }

      return FALSE;
    });

    return $notifications;
  }

  /**
   * Remove multiple notifications for the same ID.
   *
   * This method keeps the first notification for a given object, discarding
   * the rest. Since we have to reload objects anyways, we would still have to
   * deal with the race condition where an object has been deleted but we have
   * not been notified about it yet. So, we really don't concern ourselves with
   * what the notification type is (POST, PUT, DELETE), and when processing the
   * queue we use whatever is the current state from mpx.
   *
   * @param \Lullabot\Mpx\DataService\Notification[] $notifications
   *   An array of notifications.
   *
   * @return \Lullabot\Mpx\DataService\Notification[]
   *   The filtered array.
   */
  private function filterDuplicateNotifications(array $notifications): array {
    $seen_ids = [];
    $notifications = array_filter($notifications, function (MpxNotification $notification) use (&$seen_ids) {
      // Always keep delete notifications so we can log them.
      if ($notification->getMethod() == 'delete') {
        return TRUE;
      }

      $id = (string) $notification->getEntry()->getId();
      if (isset($seen_ids[$id])) {
        return FALSE;
      }

      $seen_ids[$id] = TRUE;
      return TRUE;
    });
    return $notifications;
  }

  /**
   * Save notifications to the queue, in batches of 10.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type the notifications are for.
   * @param \Lullabot\Mpx\DataService\Notification[] $notifications
   *   An array of notifications.
   */
  private function queueNotifications(MediaTypeInterface $media_type, array $notifications) {
    // @todo format_plural().
    $this->io()->note(dt('Processing @count notifications', ['@count' => count($notifications)]));
    $this->io()->progressStart(count($notifications));

    $chunks = array_chunk($notifications, 10);
    $queue = $this->queueFactory->get(self::MEDIA_MPX_NOTIFICATION_QUEUE);

    /** @var \Lullabot\Mpx\DataService\Notification[] $chunk */
    foreach ($chunks as $chunk) {
      $items = [];
      foreach ($chunk as $notification) {
        $this->logger()->debug(dt('Queuing @method notification at @time for object @id', [
          '@method' => strtoupper($notification->getMethod()),
          '@time' => $notification->getEntry()->getUpdated()->format('c'),
          '@id' => $notification->getEntry()->getId(),
        ]));

        $items[] = new Notification($notification, $media_type);
      }
      $queue->createItem($items);
      $this->io()->progressAdvance();
    }

    $this->io()->progressFinish();
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
