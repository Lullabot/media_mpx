<?php

namespace Drupal\media_mpx;

use Drupal\media\MediaTypeInterface;
use Lullabot\Mpx\DataService\Notification as MpxNotification;

/**
 * Wrap an mpx notification to include an associated media type.
 */
class Notification {

  /**
   * The mpx notification.
   *
   * @var \Lullabot\Mpx\DataService\Notification
   */
  protected $notification;

  /**
   * The media type associated with the notification.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $media_type;

  /**
   * Notification constructor.
   *
   * @param \Lullabot\Mpx\DataService\Notification $notification
   *   The mpx notification.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type associated with the notification.
   */
  public function __construct(MpxNotification $notification, MediaTypeInterface $media_type) {
    $this->notification = $notification;
    $this->media_type = $media_type;
  }

  /**
   * Return the mpx notification.
   *
   * @return \Lullabot\Mpx\DataService\Notification
   *   The mpx notification.
   */
  public function getNotification(): MpxNotification {
    return $this->notification;
  }

  /**
   * Return the media type.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   The media type.
   */
  public function getMediaType(): MediaTypeInterface {
    return $this->media_type;
  }

}
