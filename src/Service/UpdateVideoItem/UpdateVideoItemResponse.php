<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service\UpdateVideoItem;

use Lullabot\Mpx\DataService\Media\Media;

/**
 * Represents a response for a single video update from UpdateVideoItem.
 *
 * @package Drupal\media_mpx\Service\UpdateVideoItem
 */
class UpdateVideoItemResponse {

  /**
   * The mpx Media item hydrated after an mpx response.
   *
   * This holds the data of the video item for which the update request was
   * issued in first place.
   *
   * @var \Lullabot\Mpx\DataService\Media\Media
   */
  private $mpxItem;

  /**
   * Holds the updated entities.
   *
   * @var \Drupal\media\Entity\Media[]
   */
  private $updatedEntities;

  /**
   * UpdateVideoItemResponse constructor.
   */
  public function __construct(Media $mpxItem, array $updatedEntities) {
    $this->updatedEntities = $updatedEntities;
    $this->mpxItem = $mpxItem;
  }

  /**
   * Returns the mpx Media item.
   *
   * @return \Lullabot\Mpx\DataService\Media\Media
   *   The mpx Media Item that is constructed after an mpx response.
   */
  public function getMpxItem(): Media {
    return $this->mpxItem;
  }

  /**
   * Returns the updated entities.
   *
   * @return \Drupal\media\Entity\Media[]
   *   The entities.
   */
  public function getUpdatedEntities(): array {
    return $this->updatedEntities;
  }

}
