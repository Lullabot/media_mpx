<?php

namespace Drupal\media_mpx\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\media_mpx\AccountInterface;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\IdInterface;
use Lullabot\Mpx\DataService\PublicIdentifierInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;

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
 *     "collection" = "/admin/config/media/mpx/account",
 *     "add-form" = "/admin/config/media/mpx/account/add",
 *     "edit-form" = "/admin/config/media/mpx/account/{media_mpx_account}",
 *     "delete-form" = "/admin/config/media/mpx/account/{media_mpx_account}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class Account extends ConfigEntityBase implements AccountInterface, IdInterface, PublicIdentifierInterface {

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
   * The entity id of the User configuration entity.
   *
   * @var string
   */
  protected $user;

  /**
   * The URI of the account.
   *
   * @var string
   */
  protected $account;

  /**
   * The public ID of the account.
   *
   * @var string
   * @see https://docs.theplatform.com/help/wsf-account-object
   */
  protected $public_id;

  /**
   * Get the User configuration entity associated with this account.
   *
   * @return \Drupal\media_mpx\Entity\UserInterface
   *   The User configuration entity.
   */
  public function getUserEntity(): UserInterface {
    /** @var \Drupal\media_mpx\Entity\UserInterface $user */
    $user = $this->entityTypeManager()->getStorage('media_mpx_user')->load($this->user);
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): PsrUriInterface {
    return new Uri($this->account);
  }

  /**
   * {@inheritdoc}
   */
  public function setId(PsrUriInterface $id) {
    $this->account = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPid(): string {
    return $this->public_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setPid(string $pid) {
    $this->public_id = $pid;
  }

}
