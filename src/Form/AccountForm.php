<?php

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Url;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\MpxLogger;
use GuzzleHttp\Exception\TransferException;
use Lullabot\Mpx\DataService\ByFields;
use Lullabot\Mpx\Exception\ClientException;
use Lullabot\Mpx\Exception\MpxExceptionInterface;
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
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  protected $dataObjectFactory;

  /**
   * @var \Drupal\media_mpx\MpxLogger
   */
  private $mpxLogger;

  /**
   * AccountForm constructor.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $currentPathStack
   *   The current path service.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactory
   *   The factory used to load mpx objects.
   */
  public function __construct(CurrentPathStack $currentPathStack, DataObjectFactoryCreator $dataObjectFactory, MpxLogger $mpxLogger) {
    $this->currentPathStack = $currentPathStack;
    $this->dataObjectFactory = $dataObjectFactory;
    $this->mpxLogger = $mpxLogger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.current'),
      $container->get('media_mpx.data_object_factory_creator'),
      $container->get('media_mpx.exception_logger')
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
    try {

      try {
        list($options, $account_pids) = $this->accountOptions($form_state);
      }
      catch (ClientException $e) {
        $this->displayCredentialError($e);
        return [];
      }
    }
    catch (TransferException $e) {
      // Something went very wrong, so we log the whole exception for reference.
      $this->mpxLogger->logException($e);
      $this->messenger()->addError($this->t('An unexpected error occurred. The full error has been logged. %error',
        [
          '%error' => $e->getMessage(),
        ])
      );
      return [];
    }

    $form['account_pids'] = [
      '#type' => 'value',
      '#value' => $account_pids,
    ];
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
    $this->entity->set('public_id', $form_state->getValue('account_pids')[$this->entity->get('account')]);
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
        'exists' => '\Drupal\media_mpx\Entity\Account::load',
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

  /**
   * Return the account options and their public IDs.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array with:
   *     - The account options.
   *     - The account public IDs.
   */
  private function accountOptions(FormStateInterface $form_state): array {
    $user_entity_id = $form_state->getValue('user');

    /** @var \Drupal\media_mpx\Entity\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('media_mpx_user')
      ->load($user_entity_id);

    $accountFactory = $this->dataObjectFactory->forObjectType($user, 'Access Data Service', 'Account', '1.0');
    $fields = new ByFields();
    $accounts = $accountFactory->select($fields);

    $options = [];
    $account_pids = [];
    /** @var \Lullabot\Mpx\DataService\Access\Account $account */
    foreach ($accounts as $account) {
      $path_parts = explode('/', $account->getId()->getPath());
      $options[(string) $account->getId()] = $this->t('@title (@id)', [
        '@title' => $account->getTitle(),
        '@id' => end($path_parts),
      ]);
      $account_pids[(string) $account->getId()] = $account->getPid();
    }
    return [$options, $account_pids];
  }

  /**
   * Display an access denied error.
   *
   * @param \Lullabot\Mpx\Exception\ClientException $e
   *   The mpx client exception.
   */
  private function displayCredentialError(ClientException $e) {
    // First, we have special handling for credential errors.
    if ($e->getCode() == 401 || $e->getCode() == 403) {
      $this->messenger()->addError($this->t('Access was denied connecting to mpx. %error',
        [
          '%error' => $e->getMessage(),
        ])
      );
      return;
    }

    // This is a client exception, but not an authentication error so we
    // throw this up to the "unexpected error" case.
    throw $e;
  }

}
