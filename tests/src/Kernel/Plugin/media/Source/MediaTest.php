<?php

namespace Drupal\Tests\media_mpx\Kernel\Plugin\media\Source;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\media\Entity\Media;
use Drupal\Tests\media_mpx\Kernel\JsonResponse;
use Drupal\Tests\media_mpx\Kernel\MediaMpxTestBase;
use GuzzleHttp\Psr7\Response;
use Lullabot\Mpx\Exception\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Test the Media source.
 *
 * @group media_mpx
 * @coversDefaultClass \Drupal\media_mpx\Plugin\media\Source\Media
 */
class MediaTest extends MediaMpxTestBase {

  /**
   * The media entity to fetch metadata from.
   *
   * @var \Drupal\media\Entity\Media
   */
  private $media;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->media = Media::create([
      'bundle' => 'mpx',
      'title' => 'test',
      'field_media_media_mpx_media' => 'http://example.com/1',
    ]);
  }

  /**
   * Tests mpx error handling in getMetadata().
   *
   * @param array $error
   *   The error data returned from mpx.
   * @param string $log_message
   *   The expected log message string.
   * @param string $message
   *   The message displayed to the user.
   *
   * @dataProvider mpxErrors
   * @covers ::getMetadata
   */
  public function testMpxError(array $error, string $log_message, string $message) {
    $this->handler->append(
      function (RequestInterface $request) use ($error) {
        $response = new Response($error['responseCode'], [], json_encode($error));
        throw new ClientException($request, $response);
      }
    );

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Psr\Log\LoggerInterface $logger */
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('log')
      ->with(RfcLogLevel::ERROR, $log_message, $this->anything());
    $this->container->get('logger.factory')->addLogger($logger);

    /** @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())->method('addError')->with($message);
    $this->source->setMessenger($messenger);
    $this->source->getMetadata($this->media, 'Media:title');
  }

  /**
   * Data provider for testing mpx error responses.
   *
   * @return array
   *   An array of test cases, each containing:
   *     - The mpx error response json.
   *     - The log message template.
   *     - The message set with the messenger service.
   */
  public function mpxErrors() {
    return [
      'test 404 response' => [
        [
          'responseCode' => 404,
          'isException' => TRUE,
          'title' => 'Not found',
          'description' => 'Video not found',
        ],
        'HTTP %code %title %description %correlation_id %type in %function (line %line of %file).',
        'The video was not found in mpx. Check the mpx URL and try again.',
      ],
      'test 401 response' => [
        [
          'responseCode' => 401,
          'isException' => TRUE,
          'title' => 'Unauthorized',
          'description' => 'Unauthorized',
        ],
        'HTTP %code %title %description %correlation_id %type in %function (line %line of %file).',
        'Access was denied loading the video from mpx. Check the mpx URL and account credentials and try again.',
      ],
      'test 403 response' => [
        [
          'responseCode' => 403,
          'isException' => TRUE,
          'title' => 'Forbidden',
          'description' => 'Forbidden',
        ],
        'HTTP %code %title %description %correlation_id %type in %function (line %line of %file).',
        'Access was denied loading the video from mpx. Check the mpx URL and account credentials and try again.',
      ],
      'test 402 response' => [
        [
          'responseCode' => 402,
          'isException' => TRUE,
          'title' => 'Payment required',
          'description' => 'Time to pay up',
        ],
        'HTTP %code %title %description %correlation_id %type in %function (line %line of %file).',
        'There was an error loading the video from mpx. The error from mpx was: HTTP 402 Error Payment required: Time to pay up',
      ],
      'test 503 response' => [
        [
          'responseCode' => 503,
          'isException' => TRUE,
          'title' => 'Service Unavailable',
          'description' => 'The hamsters are on strike',
        ],
        'HTTP %code %title %description %correlation_id %type in %function (line %line of %file).',
        'There was an error loading the video from mpx. The error from mpx was: HTTP 503 Error Service Unavailable: The hamsters are on strike',
      ],
    ];
  }

  /**
   * Test mapping the duration of the video.
   *
   * @covers ::getMetadataAttributes
   * @covers ::getMetadata
   */
  public function testMediaFileMap() {
    $this->handler->append(new JsonResponse(200, [], 'signin-success.json'));
    $this->handler->append(new JsonResponse(200, [], 'media-object.json'));
    $this->assertContains('MediaFile:duration', array_keys($this->source->getMetadataAttributes()));
    $this->assertEquals(55.289, $this->source->getMetadata($this->media, 'MediaFile:duration'));
  }

}
