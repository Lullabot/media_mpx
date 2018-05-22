<?php

namespace Drupal\media_mpx\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Lullabot\Mpx\Service\IdentityManagement\UserInterface as MpxUserInterface;

/**
 * Provides an interface for defining mpx User entities.
 */
interface UserInterface extends ConfigEntityInterface, MpxUserInterface {

  /**
   * Return the mpx username.
   *
   * @return string
   *   The mpx username.
   */
  public function getUsername(): string;

  /**
   * Return the mpx password.
   *
   * @return string
   *   The mpx password.
   */
  public function getPassword(): string;

}
