<?php

namespace Drupal\media_mpx\Plugin\QueueWorker;

use Drupal\Core\Queue\PostponeItemException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\QueueWorker\ThumbnailDownloader as CoreThumbnailDownloader;
use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use Lullabot\Mpx\Exception\MpxExceptionInterface;

/**
 * Process a queue of media items to fetch their thumbnails.
 */
class ThumbnailDownloader extends CoreThumbnailDownloader {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Drupal doesn't allow for individual media sources to define their
    // thumbnail downloader. Instead, if we are processing any other media item
    // we defer to core's implementation.
    $media = $this->getMedia($data);
    if (!$this->isMpxMediaItem($media)) {
      parent::processItem($data);
      return;
    }

    try {
      $media->updateQueuedThumbnail();
    }
    catch (MpxExceptionInterface $e) {
      $message = sprintf('There was an error downloading the media entity %c thumbnail from %s. The thumbnail will be retried later.', $media->id(), $media->getSource()->getSourceFieldValue($media));

      // If core has been patched to support recreating a single queue item,
      // use that.
      // @see https://www.drupal.org/project/drupal/issues/1832818
      if (class_exists('\Drupal\Core\Queue\PostponeItemException')) {
        throw new PostponeItemException($message, $e->getCode(), $e);
      }

      // Otherwise, block the whole queue.
      throw new SuspendQueueException($message, $e->getCode(), $e);
    }

    $media->save();
  }

  /**
   * Determine if an entity is an mpx media item.
   *
   * @param \Drupal\media\MediaInterface|null $media
   *   (optional) The media entity to check. Supports null so we can use this
   *   with the return from loading an entity.
   *
   * @return bool
   *   TRUE if data references an mpx media entity, FALSE otherwise.
   */
  private function isMpxMediaItem(MediaInterface $media = NULL): bool {
    return $media && $media->getSource() instanceof MpxMediaSourceInterface;
  }

  /**
   * Get the media entity referenced by the queue data.
   *
   * @param array $data
   *   The queue data.
   *
   * @return \Drupal\media\Entity\Media|null
   *   The media entity, or null if one could not be loaded.
   */
  private function getMedia(array $data): ?Media {
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityTypeManager->getStorage('media')->load($data['id']);
    return $media;
  }

}
