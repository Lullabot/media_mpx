<?php

namespace Drupal\Tests\media_mpx\Unit\Service;

use Drupal\media_mpx\Service\QueueMpxImportResult;
use Drupal\media_mpx\Service\QueueVideoImportsResponse;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\Media\Media;
use Lullabot\Mpx\DataService\ObjectList;
use Lullabot\Mpx\DataService\ObjectListIterator;

/**
 * Tests response objects from the QueueVideoImport service.
 *
 * @group media_mpx
 * @coversDefaultClass \Drupal\media_mpx\Service\QueueVideoImportsResponse
 */
class QueueVideoImportsResponseTest extends UnitTestCase {

  /**
   * @covers ::getQueuedVideos
   * @dataProvider mpxImportResults
   */
  public function testGetQueuedVideos($import_results, $succesful_results, $failed_results) {
    $iterator = $this->getDummyIterator();

    $queue_service_response = new QueueVideoImportsResponse($import_results, $iterator);
    $this->assertEquals($succesful_results, $queue_service_response->getQueuedVideos());
  }

  /**
   * @covers ::getNotQueuedVideos
   * @dataProvider mpxImportResults
   */
  public function testGetNotQueuedVideos($import_results, $succesful_results, $failed_results) {
    $iterator = $this->getDummyIterator();

    $queue_service_response = new QueueVideoImportsResponse($import_results, $iterator);
    $this->assertEquals($failed_results, $queue_service_response->getNotQueuedVideos());
  }

  /**
   * Returns a Dummy mpx ObjectListIterator.
   *
   * @return \Lullabot\Mpx\DataService\ObjectListIterator
   *   A dummy iterator for usage in QueueVideoImportsResponse tests.
   */
  private function getDummyIterator(): ObjectListIterator {
    $list = new ObjectList();
    $promise = new Promise();
    $promise->resolve($list);
    $iterator = new ObjectListIterator($promise);
    return $iterator;
  }

  /**
   * Data provider to test queue video import service responses.
   *
   * @return array
   *   An array of test cases. Each case provides:
   *     - The array of queue import results objects (succesful and failed).
   *     - An array of queue import result objects that could be queued.
   *     - An array of queue import result objects that couldn't be queued.
   */
  public function mpxImportResults() {
    $split_entries = [];
    $import_results = [];
    for ($i = 0; $i <= 10; $i++) {
      $media_item = new Media();
      $id = new Uri('http://data.media.theplatform.com/media/data/Media/' . rand(1, 1000));
      $media_item->setId($id);

      $succesful = !(bool) $i % 2;
      $import_results[$i] = new QueueMpxImportResult($succesful, $media_item);
      if ($succesful) {
        $split_entries['succesful'][] = $media_item;
      }
      else {
        $split_entries['failed'][] = $media_item;
      }
    }

    return [
      'Single Test Case' => [
        $import_results,
        $split_entries['succesful'],
        $split_entries['failed'],
      ],
    ];
  }

}
