<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem;
use Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form class to import / update a single mpx item for a given mpx account.
 *
 * @package Drupal\media_mpx\Form
 */
class UpdateMediaItemForAccount extends FormBase {

  /**
   * The mpx Accounts storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $accountsStorage;

  /**
   * The media storage.
   *
   * @var \Drupal\media\MediaStorage
   */
  private $mediaStorage;

  /**
   * The update video service.
   *
   * @var \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItem
   */
  private $updateVideoItemService;

  /**
   * UpdateMediaItemForAccount constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, UpdateVideoItem $updateVideoItem) {
    $this->mediaStorage = $entityTypeManager->getStorage('media');
    $this->accountsStorage = $entityTypeManager->getStorage('media_mpx_account');
    $this->updateVideoItemService = $updateVideoItem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_mpx.service.update_video_item')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $accounts = $this->accountsStorage->loadMultiple();
    $account_opts = [];

    foreach ($accounts as $account) {
      $account_opts[$account->id()] = $account->label();
    }

    $form['account'] = [
      '#type' => 'select',
      '#title' => $this->t('Accounts'),
      '#description' => $this->t('Choose the account that owns the video you want to update.'),
      '#options' => $account_opts,
      '#required' => TRUE,
    ];
    $form['guid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('guid'),
      '#placeholder' => 'Type the GUID of the mpx item you want to import.',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update item'),
    ];

    return $form;
  }

  /**
   * Submit handler for the 'media_mpx_asset_sync_single_by_account_guid' form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $account = $form_state->getValue('account');
    $guid = $form_state->getValue('guid');

    if (!$media_items = $this->loadMediaEntitiesWithMatchingGuid($guid)) {
      $this->messenger()->addError($this->t('There are no video items with the entered GUID.'));
      return;
    }

    // Of all the possible videos, get the one linked to the selected account,
    // since guids can vary between different accounts.
    if (!$account_linked_video = $this->getMediaItemLinkedToAccount($media_items, $account)) {
      $this->messenger()->addError($this->t('The guid entered is not associated to the selected account.'));
      return;
    }

    $updateRequest = UpdateVideoItemRequest::createFromMediaEntity($account_linked_video);
    $this->updateVideoItemService->execute($updateRequest);
    // @todo: add exception and response handling for the update service.
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_mpx_asset_sync_single_by_account_guid';
  }

  /**
   * Loads all the media entities with a given guid.
   *
   * @param string $guid
   *   The guid to filter by.
   *
   * @return \Drupal\media\Entity\Media[]
   *   The mpx Media entities matching the given guid
   */
  private function loadMediaEntitiesWithMatchingGuid(string $guid): array {
    $media_ids = $this->mediaStorage->getQuery()
      ->condition('field_mpx_guid', $guid)
      ->execute();

    $entities = [];
    if (!empty($media_ids)) {
      $entities = $this->mediaStorage->loadMultiple($media_ids);
    }

    return $entities;
  }

  /**
   * Retrieves the media entity linked to the given account.
   *
   * @param \Drupal\media\Entity\Media[] $media_items
   *   An array of Media entities.
   * @param string $account
   *   The mpx account id (machine_name) for which to get the video item.
   *
   * @return \Drupal\media\Entity\Media|null
   *   The media entity that is linked to the given account. NULL if not found.
   */
  private function getMediaItemLinkedToAccount(array $media_items, string $account):? Media {
    $media_item = NULL;
    foreach ($media_items as $item) {
      if ($item->getSource()->getConfiguration()['account'] === $account) {
        $media_item = $item;
        break;
      }
    }
    return $media_item;
  }

}
