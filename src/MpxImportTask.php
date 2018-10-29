<?php

namespace Drupal\media_mpx;

use Psr\Http\Message\UriInterface;

/**
 * Class MpxImportTask.
 *
 * A simple storage object to hold the specifics of an
 * object that needs to be imported.
 */
class MpxImportTask {

  /**
   * The id ofo the media object.
   *
   * @var \Psr\Http\Message\UriInterface
   */
  private $mediaId;

  /**
   * The id of the media type.
   *
   * @var string
   */
  private $mediaTypeId;

  /**
   * MpxImportTask constructor.
   *
   * @param \Psr\Http\Message\UriInterface $media_id
   *   The media object id.
   * @param string $media_type_id
   *   The media type id.
   */
  public function __construct(UriInterface $media_id, string $media_type_id) {
    $this->setMediaId($media_id);
    $this->setMediaTypeId($media_type_id);
  }

  /**
   * Store the media object id.
   *
   * @param \Psr\Http\Message\UriInterface $media_id
   *   The media object id.
   */
  public function setMediaId(UriInterface $media_id) {
    $this->mediaId = $media_id;
  }

  /**
   * Get the media object id.
   *
   * @return \Psr\Http\Message\UriInterface
   *   The media object id.
   */
  public function getMediaId(): UriInterface {
    return $this->mediaId;
  }

  /**
   * Store the media type id.
   *
   * @param string $media_type_id
   *   The media type id.
   */
  public function setMediaTypeId(string $media_type_id) {
    $this->mediaTypeId = $media_type_id;
  }

  /**
   * Get the media type id.
   *
   * @return string
   *   The media type id.
   */
  public function getMediaTypeId(): string {
    return $this->mediaTypeId;
  }

}
