<?php

namespace Drupal\media_mpx\Service;

/**
 * Class QueueVideoImportsRequest.
 *
 * @package Drupal\media_mpx\Service
 */
class QueueVideoImportsRequest {

  /**
   * The media type id to queue for import.
   *
   * @var string
   */
  private $mediaTypeId;

  /**
   * The limit of items to queue.
   *
   * @var int|null
   */
  private $limit;

  /**
   * The offset to start from, if there's a limit.
   *
   * @var null
   */
  private $offset;

  /**
   * QueueVideoImportsRequest constructor.
   */
  public function __construct(string $mediaTypeId, $limit = NULL, $offset = NULL) {
    $this->mediaTypeId = $mediaTypeId;
    $this->limit = $limit;
    $this->offset = $offset;
  }

  /**
   * Returns the media type id.
   *
   * @return string
   *   The media type id.
   */
  public function getMediaTypeId(): string {
    return $this->mediaTypeId;
  }

  /**
   * Returns the limit.
   *
   * @return int|null
   *   The limit, if one was provided, or NULL.
   */
  public function getLimit() {
    return $this->limit;
  }

  /**
   * Returns the offset, if one was provided.
   *
   * @return int|null
   *   The offset, if it was provided.
   */
  public function getOffset() {
    return $this->offset;
  }

}
