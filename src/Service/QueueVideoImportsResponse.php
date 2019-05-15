<?php

namespace Drupal\media_mpx\Service;

use Lullabot\Mpx\DataService\ObjectListIterator;

/**
 * Class QueueVideoImportsResponse.
 *
 * @package Drupal\media_mpx\Service
 */
class QueueVideoImportsResponse {

  /**
   * The number of videos queued.
   *
   * @var int
   */
  private $queueResults;

  /**
   * The http response iterator.
   *
   * @var \Lullabot\Mpx\DataService\ObjectListIterator
   */
  private $iterator;

  /**
   * QueueVideoImportsResponse constructor.
   *
   * @param \Drupal\media_mpx\Service\QueueMpxImportResult[] $queueMpxImportsResults
   *   An array of mpx import queue results.
   * @param \Lullabot\Mpx\DataService\ObjectListIterator $iterator
   *   The http response iterator.
   */
  public function __construct(array $queueMpxImportsResults, ObjectListIterator $iterator) {
    $this->queueResults = $queueMpxImportsResults;
    $this->iterator = $iterator;
  }

  /**
   * Returns the mpx Media Items that were successfully queued.
   *
   * @return \Lullabot\Mpx\DataService\Media\Media[]
   *   An array of mpx Media items.
   */
  public function getQueuedVideos(): array {
    $queued = [];
    foreach ($this->queueResults as $result) {
      if ($result->wasSuccesful()) {
        $queued[] = $result->getMpxMediaItem();
      }
    }
    return $queued;
  }

  /**
   * Returns the mpx Media items that could not be queued.
   *
   * @return \Lullabot\Mpx\DataService\Media\Media[]
   *   An array of mpx Media items.
   */
  public function getNotQueuedVideos(): array {
    $not_queued = [];
    foreach ($this->queueResults as $result) {
      if ($result->wasSuccesful() === FALSE) {
        $not_queued[] = $result->getMpxMediaItem();
      }
    }
    return $not_queued;
  }

  /**
   * Returns the Iterator object returned by Guzzle.
   *
   * @return \Lullabot\Mpx\DataService\ObjectListIterator
   *   The http response iterator.
   */
  public function getIterator(): ObjectListIterator {
    return $this->iterator;
  }

}
