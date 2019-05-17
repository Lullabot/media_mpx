<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service\UpdateVideoItem;

use Drupal\media\Entity\Media;
use Drupal\media_mpx\MpxImportTask;

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
   * Creates an update video request from an mpx import task.
   *
   * @param \Drupal\media_mpx\MpxImportTask $importTask
   *   The import task coming from the import queue.
   *
   * @return \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest
   *   The request object, to be passed to the UpdateVideoItem service.
   */
  public static function createFromMpxImportTask(MpxImportTask $importTask): UpdateVideoItemRequest {
    // Import tasks store the actual URI object pointing to the mpx object. Get
    // the last part only, which is the numeric id.
    $mpx_id = (int) end(explode('/', $importTask->getMediaId()->getPath()));

    return new self($mpx_id, $importTask->getMediaTypeId());
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
