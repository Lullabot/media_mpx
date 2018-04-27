<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\media\MediaSourceInterface;
use Drupal\media_mpx\Entity\Account;

interface MpxMediaSourceInterface extends MediaSourceInterface{

  /**
   * Return the mpx account used for this media type.
   *
   * @return \Drupal\media_mpx\Entity\Account
   *   The mpx account.
   */
  public function getAccount(): Account;
}
