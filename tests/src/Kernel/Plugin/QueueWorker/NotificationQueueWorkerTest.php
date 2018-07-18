<?php

namespace Drupal\Tests\media_mpx\Kernel\Plugin\QueueWorker;

use Drupal\media_mpx\Notification;
use Drupal\media_mpx_test\JsonResponse;
use Drupal\Tests\media_mpx\Kernel\MediaMpxTestBase;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\Media\Media;
use Lullabot\Mpx\DataService\Notification as MpxNotification;

/**
 * Tests notification processing.
 *
 * @group media_mpx
 * @coversDefaultClass \Drupal\media_mpx\Plugin\QueueWorker\NotificationQueueWorker
 */
class NotificationQueueWorkerTest extends MediaMpxTestBase {

  /**
   * Test loads from the notification queue are not returned from the cache.
   *
   * @covers ::processItem
   */
  public function testNotificationQueueUncached() {
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');

    $entry = new Media();
    $id = new Uri('http://data.media.theplatform.com/media/data/Media/2602559');
    $entry->setId($id);

    $notification = new MpxNotification();
    $notification->setEntry($entry);
    $data[] = new Notification($notification, $this->mediaType);

    $this->handler->append(new JsonResponse(200, [], 'signin-success.json'));
    $this->handler->append(new JsonResponse(200, [], 'media-object.json'));
    $this->handler->append(new JsonResponse(200, [], 'media-object.json'));

    // Load a media object into the cache.
    $factory = $this->container->get('media_mpx.data_object_factory_creator')->fromMediaSource($this->source);
    $factory->load($id)->wait();

    /** @var \Drupal\media_mpx\Plugin\QueueWorker\NotificationQueueWorker $worker */
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance('media_mpx_notification');
    $worker->processItem($data);

    $this->assertEquals(0, $this->handler->count());
  }

}
