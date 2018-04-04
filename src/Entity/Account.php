<?php

namespace Drupal\media_mpx\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\media_mpx\AccountInterface;

/**
 * Defines the mpx account entity type.
 *
 * @ConfigEntityType(
 *   id = "media_mpx_account",
 *   label = @Translation("mpx Account"),
 *   handlers = {
 *     "list_builder" = "Drupal\media_mpx\AccountListBuilder",
 *     "form" = {
 *       "add" = "Drupal\media_mpx\Form\AccountForm",
 *       "edit" = "Drupal\media_mpx\Form\AccountForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "media_mpx_account",
 *   admin_permission = "administer media_mpx_account",
 *   label_collection = @Translation("mpx Users"),
 *   links = {
 *     "collection" = "/admin/structure/media-mpx-account",
 *     "add-form" = "/admin/structure/media-mpx-account/add",
 *     "edit-form" = "/admin/structure/media-mpx-account/{media_mpx_account}",
 *     "delete-form" = "/admin/structure/media-mpx-account/{media_mpx_account}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class Account extends ConfigEntityBase implements AccountInterface {

  /**
   * The mpx account ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The mpx account label.
   *
   * @var string
   */
  protected $label;

  /**
   * The mpx account status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The media_mpx_account description.
   *
   * @var string
   */
  protected $description;

}
