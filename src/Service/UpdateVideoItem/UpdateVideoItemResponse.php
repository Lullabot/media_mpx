<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service\UpdateVideoItem;

/**
 * Represents a response for a single video update from UpdateVideoItem.
 *
 * @package Drupal\media_mpx\Service\UpdateVideoItem
 */
class UpdateVideoItemResponse {

  /**
   * Holds the ids of the updated entities.
   *
   * @var array
   */
  private $updatedIds;

  /**
   * UpdateVideoItemResponse constructor.
   */
  public function __construct(array $updated_ids) {
    $this->updatedIds = $updated_ids;
  }

  /**
   * Returns the media ids of the updated entities.
   *
   * @return array
   *   The ids of entities updated.
   */
  public function getUpdatedIds(): array {
    return $this->updatedIds;
  }

}
