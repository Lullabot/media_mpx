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
  private $videosQueued;

  /**
   * The http response iterator.
   *
   * @var \Lullabot\Mpx\DataService\ObjectListIterator
   */
  private $iterator;

  /**
   * The number of videos that could not be queued.
   *
   * @var int
   */
  private $errors;

  /**
   * QueueVideoImportsResponse constructor.
   *
   * @param int $videos_queued
   *   The number of videos queued.
   * @param int $errors
   *   The number of videos that could not be queued.
   * @param \Lullabot\Mpx\DataService\ObjectListIterator $iterator
   *   The http response iterator.
   */
  public function __construct(int $videos_queued, int $errors, ObjectListIterator $iterator) {
    $this->videosQueued = $videos_queued;
    $this->iterator = $iterator;
    $this->errors = $errors;
  }

  /**
   * Returns the number of videos that were queued.
   *
   * @return int
   *   The number of videos queued.
   */
  public function getVideosQueued(): int {
    return $this->videosQueued;
  }

  /**
   * Returns the number of videos that could not be queued.
   *
   * @return int
   *   The number of videos that could not be queued.
   */
  public function getErrors(): int {
    return $this->errors;
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
