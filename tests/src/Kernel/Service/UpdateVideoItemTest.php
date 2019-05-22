<?php

namespace Drupal\Tests\media_mpx\Kernel\Service;

use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Drupal\media_mpx_test\JsonResponse;
use Drupal\Tests\media_mpx\Kernel\MediaMpxTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the Account form.
 *
 * @group media_mpx
 * @coversDefaultClass \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem
 */
class UpdateVideoItemTest extends MediaMpxTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');

    // Add fixtures to the guzzle handler for api requests.
    $this->handler->append(new JsonResponse(200, [], 'signin-success.json'));
    $this->handler->append(new JsonResponse(200, [], 'resolveDomain.json'));
    $this->handler->append(new JsonResponse(200, [], 'media-object.json'));

    // Mock http client so that it fakes correct thumbnail responses.
    $thumbnail_handler = new MockHandler();
    $thumbnail_handler->append(new Response(200));
    $client = new Client(['handler' => $thumbnail_handler]);
    $this->container->set('http_client', $client);
  }

  /**
   * Tests video item is available from the DB after import.
   */
  public function testMediaItemCanBeRetrievedAfterImport() {
    $mpx_media_type_id = $this->mediaType->get('id');

    $updateService = $this->container->get('media_mpx.service.update_video_item');
    $request = new UpdateVideoItemRequest(2602559, $mpx_media_type_id);

    $updateService->execute($request);
    $media_repository = $this->container->get('entity_type.manager')->getStorage('media');
    $query_filters = [
      'bundle' => $mpx_media_type_id,
      'field_media_media_mpx_media' => 'http://data.media.theplatform.com/media/data/Media/2602559',
    ];

    $entities = $media_repository->loadByProperties($query_filters);
    $this->assertCount(1, $entities);
  }

  /**
   * Tests the Update Video Item service returns accurate responses.
   */
  public function testUpdateVideoItemResponseHasCorrectData() {
    $updateService = $this->container->get('media_mpx.service.update_video_item');
    $mpx_media_type_id = $this->mediaType->get('id');
    $request = new UpdateVideoItemRequest(2602559, $mpx_media_type_id);

    $response = $updateService->execute($request);

    // Compare mpx item returned by the response, with the used fixtures data.
    $fixture_response = new JsonResponse(200, [], 'media-object.json');
    $fixture_contents = json_decode($fixture_response->getBody()->getContents());
    $this->assertEquals($fixture_contents->guid, $response->getMpxItem()->getGuid());
    $this->assertEquals($fixture_contents->title, $response->getMpxItem()->getTitle());
    $this->assertEquals($fixture_contents->author, $response->getMpxItem()->getAuthor());
    $this->assertEquals($fixture_contents->description, $response->getMpxItem()->getDescription());

    // Load the item in the database and compare with the first one from the
    // service response.
    $media_repository = $this->container->get('entity_type.manager')->getStorage('media');
    $query_filters = [
      'bundle' => $mpx_media_type_id,
      'field_media_media_mpx_media' => 'http://data.media.theplatform.com/media/data/Media/2602559',
    ];
    $videos = $media_repository->loadByProperties($query_filters);
    $db_video = reset($videos);
    $response_videos = $response->getUpdatedEntities();
    $response_video = reset($response_videos);

    $this->assertEquals($db_video->uuid(), $response_video->uuid());
    $this->assertEquals($db_video->id(), $response_video->id());
    $this->assertEquals($db_video->getName(), $response_video->getName());
  }

}
