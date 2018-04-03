<?php

namespace Drupal\media_mpx\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the MPX User entity.
 *
 * @ConfigEntityType(
 *   id = "media_mpx_user",
 *   label = @Translation("MPX User"),
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
 *   links = {
 *     "canonical" = "/admin/structure/media_mpx_user/{media_mpx_user}",
 *     "add-form" = "/admin/structure/media_mpx_user/add",
 *     "edit-form" = "/admin/structure/media_mpx_user/{media_mpx_user}/edit",
 *     "delete-form" = "/admin/structure/media_mpx_user/{media_mpx_user}/delete",
 *     "collection" = "/admin/structure/media_mpx_user"
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
   * The MPX User name.
   *
   * @var string
   */
  protected $username;

  /**
   * The MPX password.
   *
   * @var string
   */
  protected $password;

}
