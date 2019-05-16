<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service\UpdateVideoItem;

use Drupal\media\Entity\Media;
use GuzzleHttp\Psr7\Uri;

/**
 * Contains the data needed to update a media entity via UpdateVideoItem.
 *
 * @package Drupal\media_mpx\Service\UpdateVideoItem
 */
class UpdateVideoItemRequest {

  const MPX_MEDIA_DATA_SERVICE_URL = "http://data.media.theplatform.com/media/data/Media/";

  /**
   * The mpx global item id.
   *
   * @var \GuzzleHttp\Psr7\Uri
   */
  private $mpxId;

  /**
   * The media type id (bundle).
   *
   * @var string
   */
  private $mediaTypeId;

  /**
   * UpdateVideoItemRequest constructor.
   *
   * @param \GuzzleHttp\Psr7\Uri $mpxId
   *   The Uri object specifying the video item id.
   * @param string $mediaTypeId
   *   The media type id.
   */
  private function __construct(Uri $mpxId, string $mediaTypeId) {
    $this->mpxId = $mpxId;
    $this->mediaTypeId = $mediaTypeId;
  }

  /**
   * Creates a request object to update a local video item, given its entity.
   *
   * @param \Drupal\media\Entity\Media $entity
   *   The entity whose associated video will be updated.
   *
   * @return \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest
   *   The request object, to be passed to the UpdateVideoItem service.
   */
  public static function createFromMediaEntity(Media $entity): UpdateVideoItemRequest {
    $mpx_id = $entity->get('field_mpx_id')->value;
    $mpx_id = new Uri(self::MPX_MEDIA_DATA_SERVICE_URL . $mpx_id);
    $media_type_id = $entity->bundle();

    return new self($mpx_id, $media_type_id);
  }

  /**
   * Returns the mpx global item id.
   *
   * @return \GuzzleHttp\Psr7\Uri
   *   The mpx global item id.
   */
  public function getMpxId(): Uri {
    return $this->mpxId;
  }

  /**
   * The media type id (Drupal bundle).
   *
   * @return string
   *   The media type id.
   */
  public function getMediaTypeId(): string {
    return $this->mediaTypeId;
  }

}
