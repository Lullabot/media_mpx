<?php

namespace Drupal\media_mpx\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the mpx User entity.
 *
 * @ConfigEntityType(
 *   id = "media_mpx_user",
 *   label = @Translation("mpx User"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\media_mpx\UserListBuilder",
 *     "form" = {
 *       "add" = "Drupal\media_mpx\Form\UserForm",
 *       "edit" = "Drupal\media_mpx\Form\UserForm",
 *       "delete" = "Drupal\media_mpx\Form\UserDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\media_mpx\UserHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "media_mpx_user",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "username",
 *     "uuid" = "uuid"
 *   },
 *   label_collection = @Translation("mpx Users"),
 *   links = {
 *     "canonical" = "/admin/config/media/mpx/user/{media_mpx_user}",
 *     "add-form" = "/admin/config/media/mpx/user/add",
 *     "edit-form" = "/admin/config/media/mpx/user/{media_mpx_user}/edit",
 *     "delete-form" = "/admin/config/media/mpx/user/{media_mpx_user}/delete",
 *     "collection" = "/admin/config/media/mpx/user"
 *   }
 * )
 */
class User extends ConfigEntityBase implements UserInterface {

  /**
   * The machine name.
   *
   * @var string
   */
  protected $id;

  /**
   * The mpx User name.
   *
   * @var string
   */
  protected $username;

  /**
   * The mpx password.
   *
   * @var string
   */
  protected $password;

  /**
   * {@inheritdoc}
   */
  public function getUsername(): string {
    return $this->username;
  }

  /**
   * {@inheritdoc}
   */
  public function getPassword(): string {
    // @todo Handle null password.
    return $this->password;
  }

  /**
   * {@inheritdoc}
   */
  public function getMpxUsername() {
    return $this->getUsername();
  }

  /**
   * {@inheritdoc}
   */
  public function getMpxPassword() {
    return $this->getPassword();
  }

}
