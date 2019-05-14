<?php

namespace Drupal\media_mpx;

use Drupal\Core\State\StateInterface;
use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use GuzzleHttp\Exception\ConnectException;
use Lullabot\Mpx\DataService\DataServiceManager;
use Lullabot\Mpx\DataService\DiscoveredDataService;
use Lullabot\Mpx\DataService\Notification;
use Lullabot\Mpx\DataService\NotificationListener as MpxNotificationListener;
use Lullabot\Mpx\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * Listen for mpx notifications.
 *
 * This class is like \Lullabot\Mpx\DataService\NotificationListener, but
 * contains Drupal-specific dependencies like Media Sources.
 */
class NotificationListener {

  /**
   * The factory for authenticated mpx clients.
   *
   * @var \Drupal\media_mpx\AuthenticatedClientFactory
   */
  private $authenticatedClientFactory;

  /**
   * The manager to retrieve the service to listen for notifications on.
   *
   * @var \Lullabot\Mpx\DataService\DataServiceManager
   */
  private $dataServiceManager;

  /**
   * The state system to store the last notification id.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * NotificationListener constructor.
   *
   * @param \Drupal\media_mpx\AuthenticatedClientFactory $authenticatedClientFactory
   *   The factory for authenticated mpx clients.
   * @param \Lullabot\Mpx\DataService\DataServiceManager $dataServiceManager
   *   The manager to retrieve the service to listen for notifications on.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state system to store the last notification id.
   * @param \Psr\Log\LoggerInterface $logger
   *   The system logger.
   */
  public function __construct(AuthenticatedClientFactory $authenticatedClientFactory, DataServiceManager $dataServiceManager, StateInterface $state, LoggerInterface $logger) {
    $this->authenticatedClientFactory = $authenticatedClientFactory;
    $this->dataServiceManager = $dataServiceManager;
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * Fetch notifications from mpx.
   *
   * @param \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface $media_source
   *   The media source notifications are being listened for.
   * @param int $notification_id
   *   The last notification ID that was processed.
   *
   * @return \Lullabot\Mpx\DataService\Notification[]
   *   An array of notifications.
   */
  public function listen(MpxMediaSourceInterface $media_source, int $notification_id): array {
    $account = $media_source->getAccount();

    $client = $this->authenticatedClientFactory->fromAccount($account);
    $definition = $media_source->getPluginDefinition()['media_mpx'];
    $service = $this->dataServiceManager->getDataService($definition['service_name'], $definition['object_type'], $definition['schema_version']);
    // @todo Client ID needs to be configured somehow.
    // @todo Inject caches
    $listener = new MpxNotificationListener($client, $service, 'drush-drupal8-mpx');

    $promise = $listener->listen($notification_id);
    try {
      return $promise->wait();
    }
    catch (ConnectException | ClientException $e) {
      // This may be a timeout if no notifications are available. However, there
      // is no good method from the exception to determine if a timeout
      // occurred.
      if (strpos($e->getMessage(), 'cURL error 28') !== FALSE) {
        $this->logger->info('A timeout occurred while waiting for notifications. This is expected when no content is changing in mpx. No action is required.');
        return [];
      }

      if ($e->getCode() == 404) {
        return $this->resetStaleNotificationId($media_source, $notification_id, $service);
      }

      // Some other connection exception occurred, so throw that up.
      throw $e;
    }
  }

  /**
   * Return the current notification ID, or -1 if one is not set.
   *
   * @param string $media_type_id
   *   The media type ID to import for.
   *
   * @return int
   *   The notification ID.
   */
  public function getNotificationId(string $media_type_id): int {
    $notification_key = $this->getNotificationKey($media_type_id);
    if (!$notification_id = $this->state->get($notification_key)) {
      // @todo Should we throw a warning?
      $notification_id = -1;
    }

    return $notification_id;
  }

  /**
   * Set the last processed notification id.
   *
   * @param string $media_type_id
   *   The media type ID to import for.
   * @param \Lullabot\Mpx\DataService\Notification $notification
   *   The last notification that has been processed.
   */
  public function setNotificationId(string $media_type_id, Notification $notification) {
    $notification_key = $this->getNotificationKey($media_type_id);
    // @todo should this really be state?
    $this->state->set($notification_key, $notification->getId());
  }

  /**
   * Reset the notification ID to restart from the earliest notification.
   *
   * @param string $media_type_id
   *   The media type ID to reset notifications for.
   */
  public function resetNotificationId(string $media_type_id): void {
    $notification_key = $this->getNotificationKey($media_type_id);
    $this->state->delete($notification_key);
  }

  /**
   * Return the notification key in the state system.
   *
   * @param string $media_type_id
   *   The media type ID to import for.
   *
   * @return string
   *   The notification key.
   */
  private function getNotificationKey(string $media_type_id): string {
    return $media_type_id . '_notification_id';
  }

  /**
   * Reset notifications when the current ID is stale.
   *
   * @param \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface $media_source
   *   The media source to reset notifications for.
   * @param int $notification_id
   *   The stale notification ID.
   * @param \Lullabot\Mpx\DataService\DiscoveredDataService $service
   *   The data service notifications are being listened for.
   *
   * @return \Lullabot\Mpx\DataService\Notification[]
   *   An array of notifications.
   */
  private function resetStaleNotificationId(MpxMediaSourceInterface $media_source, int $notification_id, DiscoveredDataService $service): array {
    $this->logger->warning(
      'The %object_type last notification ID %id for %account is older than 7 days and is too old to fetch notifications. The last notification ID has been reset to re-start ingestion.',
      [
        '%object_type' => $service->getAnnotation()->getObjectType(),
        '%id' => $notification_id,
        '%account' => $media_source->getAccount()->label(),
      ]);

    return $this->listen($media_source, -1);
  }

}
