<?php

namespace Drupal\media_mpx\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining mpx User entities.
 */
interface UserInterface extends ConfigEntityInterface {

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
