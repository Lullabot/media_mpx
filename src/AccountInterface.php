<?php

namespace Drupal\media_mpx;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\media_mpx\Entity\UserInterface;
use Lullabot\Mpx\DataService\IdInterface;
use Lullabot\Mpx\DataService\PublicIdentifierInterface;

/**
 * Provides an interface defining a mpx account entity type.
 */
interface AccountInterface extends ConfigEntityInterface, IdInterface, PublicIdentifierInterface {

  /**
   * Get the User configuration entity associated with this account.
   *
   * @return \Drupal\media_mpx\Entity\UserInterface
   *   The User configuration entity.
   */
  public function getUserEntity(): UserInterface;

}
