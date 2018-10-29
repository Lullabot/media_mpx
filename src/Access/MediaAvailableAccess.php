<?php

namespace Drupal\media_mpx\Access;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\media_mpx\Plugin\media\Source\Media;
use Lullabot\Mpx\DataService\DateTime\AvailabilityCalculator;

/**
 * Check the availability of an mpx media entity.
 *
 * While mpx has an availability state property, we want to be able to use
 * cached mpx data instead of having to re-fetch it from upstream.
 *
 * @see \Lullabot\Mpx\DataService\DateTime\AvailabilityCalculator
 */
class MediaAvailableAccess {

  /**
   * The system time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * MediaAvailableAccess constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The system time service.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * Return if access is forbidden by availability rules.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result. A neutral result is returned if the entity is not an
   *   mpx media object, or if availability rules permit access. A forbidden
   *   result is returned if the video is expired.
   */
  public function view(MediaInterface $media, AccountInterface $account) {
    // The media entity is not an mpx media object.
    if (!($media->getSource() instanceof Media)) {
      return AccessResult::neutral();
    }

    // If you can edit an entity, don't apply availability rules.
    if ($media->access('edit', $account)) {
      return AccessResult::neutral();
    }

    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $source */
    $source = $media->getSource();

    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_object */
    $mpx_object = $source->getMpxObject($media);

    $now = \DateTime::createFromFormat('U', $this->time->getCurrentTime());
    $calculator = new AvailabilityCalculator();

    // We need to use forbid instead of allowing on available. Otherwise, if we
    // allow, Drupal will ignore other access controls like the published
    // status.
    if ($calculator->isExpired($mpx_object, $now)) {
      return AccessResult::forbidden('This video is not available.');
    }

    return AccessResult::neutral();
  }

}