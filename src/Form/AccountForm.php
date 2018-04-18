<?php

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Url;
use Drupal\media_mpx\DataObjectFactory;
use Lullabot\Mpx\DataService\ByFields;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The mpx Account form.
 *
 * @property \Drupal\media_mpx\AccountInterface $entity
 */
class AccountForm extends EntityForm {

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPathStack;

  /**
   * The factory used to load mpx objects.
   *
   * @var \Drupal\media_mpx\DataObjectFactory
   */
  protected $dataObjectFactory;

  /**
   * AccountForm constructor.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path service.
   * @param \Drupal\media_mpx\DataObjectFactory $dataObjectFactory
   *   The factory used to load mpx objects.
   */
  public function __construct(CurrentPathStack $currentPathStack, DataObjectFactory $dataObjectFactory) {
    $this->currentPathStack = $currentPathStack;
    $this->dataObjectFactory = $dataObjectFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.current'),
      $container->get('media_mpx.data_object_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form = $this->idLabelForm($form);

    $users = $this->loadMpxUsers($form_state);
    if (empty($users)) {
      $url = Url::fromRoute('entity.media_mpx_user.add_form')->toString() . '?destination=' . \Drupal::service('path.current')->getPath();
      $this->messenger()->addError($this->t('<a href="@add-user">Create at least one mpx user</a> before creating accounts.', [
        '@add-user' => $url,
      ]));
      return [];
    }

    $form = $this->userAccountForm($form, $form_state, $users);

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    return $form;
  }

  /**
   * Ajax callback to fetch the list of mpx accounts.
   *
   * @param array &$form
   *   The form being rendered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The accounts form item.
   */
  public function fetchAccounts(array &$form, FormStateInterface $form_state) : array {
    $user_entity_id = $form_state->getValue('user');

    /** @var \Drupal\media_mpx\Entity\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('media_mpx_user')->load($user_entity_id);

    $accountFactory = $this->dataObjectFactory->forObjectType($user, 'Access Data Service', 'Account', '1.0');
    $fields = new ByFields();
    $accounts = $accountFactory->select($fields);

    $options = [];
    foreach ($accounts as $account) {
      $options[(string) $account->getId()] = $this->t('@title (@id)', [
        '@title' => $account->getTitle(),
        '@id' => end(explode('/', $account->getId()->getPath())),
      ]);
    }
    $form['accounts_container']['account'] = [
      // @todo Change to radios when
      // https://www.drupal.org/project/drupal/issues/2758631 is fixed.
      '#type' => 'select',
      '#title' => $this->t('mpx account'),
      '#options' => $options,
      '#default_value' => $this->entity->get('account'),
    ];
    return $form['accounts_container']['account'];
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

  /**
   * Return the mpx user and account selection form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $users
   *   An array of mpx User options.
   *
   * @return array
   *   The complete form.
   */
  private function userAccountForm(array $form, FormStateInterface $form_state, array $users): array {
    $form['user'] = [
      '#type' => 'select',
      '#title' => $this->t('mpx user'),
      '#description' => $this->t('Select an mpx user to see what accounts are available.'),
      '#options' => $users,
      '#default_value' => $this->entity->get('user'),
      '#ajax' => [
        'callback' => [$this, 'fetchAccounts'],
        'event' => 'change',
        'wrapper' => 'media-mpx-accounts',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Fetching mpx accounts…'),
        ],
      ],
    ];

    $form['accounts_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'media-mpx-accounts',
      ],
    ];

    $this->fetchAccounts($form, $form_state);
    return $form;
  }

  /**
   * Return the id and label fields.
   *
   * @param array $form
   *   The current form.
   *
   * @return array
   *   The complete form.
   */
  private function idLabelForm(array $form): array {
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
    return $form;
  }

  /**
   * Return all configured mpx user names, keyed by their config entity ID.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array of user names, keyed by the config entity ID.
   */
  private function loadMpxUsers(FormStateInterface $form_state): array {
    $users = array_map(function ($entity) {
      /** @var \Drupal\media_mpx\Entity\UserInterface $entity */
      return $entity->label();
    }, $this->entityTypeManager->getStorage('media_mpx_user')->loadMultiple());

    // Set the currently selected user on the initial load.
    if (!$form_state->hasValue('user')) {
      reset($users);
      $form_state->setValue('user', key($users));
    }

    return $users;
  }

}
