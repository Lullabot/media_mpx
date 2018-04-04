<?php

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Mpx Account form.
 *
 * @property \Drupal\media_mpx\AccountInterface $entity
 */
class AccountForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the mpx account.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\media_mpx\Entity\MpxAccount::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $users = array_map(function ($entity) {
      /** @var \Drupal\media_mpx\Entity\UserInterface $entity */
      return $entity->label();
    }, $this->entityTypeManager->getStorage('media_mpx_user')->loadMultiple());

    if (empty($users)) {
      $url = Url::fromRoute('entity.media_mpx_user.add_form')->toString() . '?destination=' . \Drupal::service('path.current')->getPath();
      $this->messenger()->addError($this->t('<a href="@add-user">Create at least one mpx user</a> before creating accounts.', [
        '@add-user' => $url,
      ]));
      return [];
    }

    $form['user'] = [
      '#type' => 'select',
      '#title' => $this->t('mpx user'),
      '#description' => $this->t('Select an mpx user to see what accounts are available.'),
      '#options' => $users,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new mpx account %label.', $message_args)
      : $this->t('Updated mpx account %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
