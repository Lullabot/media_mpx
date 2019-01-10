<?php

namespace Drupal\Tests\media_mpx\Kernel\Plugin\QueueWorker;

use Drupal\media_mpx\Notification;
use Drupal\media_mpx_test\JsonResponse;
use Drupal\Tests\media_mpx\Kernel\MediaMpxTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\Media\Media;
use Lullabot\Mpx\DataService\Notification as MpxNotification;
use Psr\Log\LoggerInterface;

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

    // Mock an empty thumbnail return.
    $thumbnail_handler = new MockHandler();
    $thumbnail_handler->append(new Response(200));
    $client = new Client(['handler' => $thumbnail_handler]);
    $this->container->set('http_client', $client);

    // Load a media object into the cache.
    $factory = $this->container->get('media_mpx.data_object_factory_creator')->fromMediaSource($this->source);
    $factory->load($id)->wait();

    /** @var \Drupal\media_mpx\Plugin\QueueWorker\NotificationQueueWorker $worker */
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance('media_mpx_notification');
    $worker->processItem($data);

    $this->assertEquals(0, $this->handler->count());
  }

  /**
   * Test errors in the queue are logged.
   *
   * @covers ::processItem
   */
  public function testNotificationQueueErrorsLogged() {
    /** @var \PHPUnit_Framework_MockObject_MockObject|\Psr\Log\LoggerInterface $logger */
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('log');
    $this->container->get('logger.factory')->addLogger($logger);

    $entry = new Media();
    $id = new Uri('http://data.media.theplatform.com/media/data/Media/2602559');
    $entry->setId($id);

    $notification = new MpxNotification();
    $notification->setEntry($entry);
    $data[] = new Notification($notification, $this->mediaType);

    $this->handler->append(new JsonResponse(200, [], 'signin-success.json'));
    $error = \GuzzleHttp\json_encode([
      'responseCode' => 404,
      'isException' => TRUE,
      'description' => 'Object not found',
      'title' => '404 Not Found',
      'correlationId' => 'the-correlation-id',
    ]);
    $this->handler->append(new JsonResponse(200, [], $error));

    /** @var \Drupal\media_mpx\Plugin\QueueWorker\NotificationQueueWorker $worker */
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance('media_mpx_notification');
    $worker->processItem($data);

    $this->assertEquals(0, $this->handler->count());
  }

}
