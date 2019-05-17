<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service\UpdateVideoItem;

use Drupal\media\Entity\Media;

/**
 * Contains the data needed to update a media entity via UpdateVideoItem.
 *
 * @package Drupal\media_mpx\Service\UpdateVideoItem
 */
class UpdateVideoItemRequest {

  /**
   * The mpx global item numeric id.
   *
   * @var int
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
   * @param int $mpxId
   *   The mpx video item global id.
   * @param string $mediaTypeId
   *   The media type id.
   */
  private function __construct(int $mpxId, string $mediaTypeId) {
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
    $mpx_id = (int) $entity->get('field_mpx_id')->value;
    $media_type_id = $entity->bundle();
    return new self($mpx_id, $media_type_id);
  }

  /**
   * Returns the mpx global item numeric id.
   *
   * @return int
   *   The mpx global item id.
   */
  public function getMpxId(): int {
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
