<?php

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class UserForm.
 */
class UserForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $media_mpx_user = $this->entity;
    // @todo Email validation.
    // @todo html5 placeholder
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('mpx user name'),
      '#maxlength' => 255,
      '#default_value' => $media_mpx_user->label(),
      '#description' => $this->t("The MPX user name."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $media_mpx_user->id(),
      '#machine_name' => [
        'exists' => '\Drupal\media_mpx\Entity\User::load',
        'source' => ['username'],
      ],
      '#disabled' => !$media_mpx_user->isNew(),
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('mpx password'),
      '#description' => $this->t('The mpx user password.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $media_mpx_user = $this->entity;
    $status = $media_mpx_user->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addStatus($this->t('Created the %label mpx User.', [
          '%label' => $media_mpx_user->label(),
        ]));
        break;

      default:
        $this->messenger->addStatus($this->t('Saved the %label mpx User.', [
          '%label' => $media_mpx_user->label(),
        ]));
    }
    $form_state->setRedirectUrl($media_mpx_user->toUrl('collection'));
  }

}
