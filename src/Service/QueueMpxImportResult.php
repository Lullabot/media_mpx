<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service;

use Lullabot\Mpx\DataService\Media\Media;

/**
 * Holds the result of trying to queue an mpx item for later import.
 *
 * @package Drupal\media_mpx\Service
 */
class QueueMpxImportResult {

  /**
   * Whether the queueing attempt was successful.
   *
   * @var bool
   */
  private $success;

  /**
   * The mpx Media item.
   *
   * @var \Lullabot\Mpx\DataService\Media\Media
   */
  private $mpxMediaItem;

  /**
   * QueueMpxImportResult constructor.
   *
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx Media item.
   * @param bool $success
   *   Whether the queueing attempt was successful or not.
   */
  public function __construct(Media $mpx_media, bool $success) {
    $this->success = $success;
    $this->mpxMediaItem = $mpx_media;
  }

  /**
   * Returns whether the queuing attempt was successful or not.
   *
   * @return bool
   *   TRUE if the item was queued for later import. FALSE otherwise.
   */
  public function wasSuccessful(): bool {
    return $this->success;
  }

  /**
   * Returns the mpx Media item for which the queue attempt was done.
   *
   * @return \Lullabot\Mpx\DataService\Media\Media
   *   The mpx Media item.
   */
  public function getMpxMediaItem(): Media {
    return $this->mpxMediaItem;
  }

}
