<?php

namespace Drupal\media_mpx\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\AuthenticatedClientFactory;
use Drupal\media_mpx\DataObjectFactory;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Notification;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Exception\ConnectException;
use Lullabot\Mpx\DataService\Access\Account;
use Lullabot\Mpx\DataService\ByFields;
use Lullabot\Mpx\DataService\DataServiceManager;
use Lullabot\Mpx\DataService\NotificationListener;
use Lullabot\Mpx\DataService\ObjectListIterator;

/**
 * Drush commands for mpx.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MediaMpxCommands extends DrushCommands {

  /**
   * The entity type manager used to load entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The factory used to load media data from mpx.
   *
   * @var \Drupal\media_mpx\DataObjectFactory
   */
  private $dataObjectFactory;

  /**
   * The factory used to store full mpx Media objects in the key-value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  private $keyValueFactory;

  /**
   * @var \Drupal\media_mpx\AuthenticatedClientFactory
   */
  protected $authenticatedClientFactory;

  /**
   * @var \Lullabot\Mpx\DataService\DataServiceManager
   */
  private $dataServiceManager;

  /**
   * MediaMpxCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\media_mpx\DataObjectFactory $dataObjectFactory
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   * @param \Drupal\media_mpx\AuthenticatedClientFactory $authenticatedClientFactory
   * @param \Lullabot\Mpx\DataService\DataServiceManager $dataServiceManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, DataObjectFactory $dataObjectFactory, KeyValueFactoryInterface $keyValueFactory, AuthenticatedClientFactory $authenticatedClientFactory, DataServiceManager $dataServiceManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->dataObjectFactory = $dataObjectFactory;
    $this->keyValueFactory = $keyValueFactory;
    $this->authenticatedClientFactory = $authenticatedClientFactory;
    $this->dataServiceManager = $dataServiceManager;
  }

  /**
   * Import mpx objects for a media type.
   *
   * @param string $media_type_id
   *   The media type ID to import for.
   *
   * @usage media_mpx-import mpx_video
   *   Import all mpx data for the mpx_video media type.
   *
   * @command media_mpx:import
   * @aliases mpxi
   */
  public function import(string $media_type_id) {
    $media_type = $this->loadMediaType($media_type_id);

    $results = $this->selectAll($media_type);

    // @todo Support fetching the total results via ObjectList.
    $this->io()->title(dt('Importing @type media', ['@type' => $media_type_id]));
    $this->io()->progressStart();

    $importer = new DataObjectImporter($this->keyValueFactory, $this->entityTypeManager);
    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    foreach ($results as $index => $mpx_media) {
      $importer->importItem($mpx_media, $media_type);

      $this->io()->progressAdvance();
      $this->logger()->info(dt('Imported @type @uri.', ['@type' => $media_type_id, '@uri' => $mpx_media->getId()]));
    }

    $this->io()->progressFinish();

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
  public function listen(string $media_type_id) {
    $media_type = $this->loadMediaType($media_type_id);
    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $account = $media_source->getAccount();

    $client = $this->authenticatedClientFactory->fromUser($account->getUserEntity());
    $definition = $media_source->getPluginDefinition()['media_mpx'];
    $service = $this->dataServiceManager->getDataService($definition['service_name'], $definition['object_type'], $definition['schema_version']);
    // @todo Fixup clientid
    $listener = new NotificationListener($client, $service, 'drush-drupal8-mpx');

    // @todo should this really be state?
    $state = \Drupal::state();
    $notification_key = $media_source->getPluginId() . '_notification_id';
    if (!$notification_id = $state->get($notification_key)) {
      // @todo Should we throw a warning?
      $notification_id = -1;
    }

    // @todo Support overriding the default 30 second timeout.
    $promise = $listener->listen($notification_id);
    /** @var \Lullabot\Mpx\DataService\Notification[] $notifications */
    $this->io()->note(dt('Waiting for a notification from mpx after notification ID @id...', ['@id' => $notification_id]));

    try {
      $notifications = $promise->wait();
    }
    catch (ConnectException $e) {
      // This may be a timeout if no notifications are available. However, there
      // is no good method from the exception to determine if a timeout
      // occurred.
      if (strpos($e->getMessage(), 'cURL error 28') !== FALSE) {
        $this->logger()->info('A timeout occurred while waiting for notifications. This is expected when no content is changing in mpx. No action is required.');
        return;
      }

      // Some other connection exception occurred, so throw that up.
      throw $e;
    }

    // @todo format_plural().
    $this->io()->note(dt('Processing @count notifications', ['@count' => count($notifications)]));
    $this->io()->progressStart(count($notifications));
    $seen_ids = [];
    $notifications = array_filter($notifications, function ($notification) use (&$seen_ids) {
      /** @var \Lullabot\Mpx\DataService\Notification $notification */
      $id = (string) $notification->getEntry()->getId();
      if (isset($seen_ids[$id])) {
        return FALSE;
      }

      $this->logger()->info(dt('Queuing @method notification for object @id', ['@method' => $notification->getMethod(), '@id' => $id]));
      $seen_ids[$id] = TRUE;
      return TRUE;
    });

    $chunks = array_chunk($notifications, 10);
    $q = \Drupal::queue('media_mpx_notification');
    foreach ($chunks as $chunk) {
      $items = [];
      foreach ($chunk as $notification) {
        $items[] = new Notification($notification, $media_type);
      }
      $q->createItem($items);
      //      $this->importItem($media_type, $mpx_media);
      $this->io()->progressAdvance();
    }
    $state->set($notification_id, end($notifications)->getId());

    $this->io()->progressFinish();
//    $this->listen($media_type_id);
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
    /** @var $media_type \Drupal\media\MediaTypeInterface */
    if (!$media_type = $this->entityTypeManager->getStorage($bundle_type)
      ->load($media_type_id)) {
      // Normally you wouldn't translate exception text, but Drush does it in
      // it's own commands.
      // @see https://github.com/drush-ops/drush/blob/76a28373e7d3bdd708ab54d54f0e686370b46506/examples/Commands/PolicyCommands.php#L37
      throw new \InvalidArgumentException(dt('The media type @type does not exist.', ['@type' => $media_type_id]));
    }
    return $media_type;
  }

  /**
   * Fetch all mpx items for a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type to load items for.
   *
   * @return \Lullabot\Mpx\DataService\ObjectListIterator
   *   An iterator over all retrieved media items.
   */
  private function selectAll(MediaTypeInterface $media_type): ObjectListIterator {
    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $account = $media_source->getAccount();

    $mpx_account = new Account();
    $mpx_account->setId($account->get('account'));

    $factory = $this->dataObjectFactory->fromMediaSource($media_source);
    // @todo Remove this when it's made optional upstream.
    // @see https://github.com/Lullabot/mpx-php/issues/78
    $fields = new ByFields();
    $results = $factory->select($fields, $mpx_account);
    return $results;
  }

}
