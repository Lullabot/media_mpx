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

    /** @var \Drupal\media_mpx\Entity\UserInterface[] $users */
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
    $form_state->setValue('user', reset(array_keys($users)));

//    $form['accounts_wrapper'] = [
//      '#prefix' => '<div id="media-mpx-accounts">',
//      '#suffix' => '</div>',
//    ];

    $form['account'] = $this->fetchAccounts($form, $form_state);

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    return $form;
  }

  public function fetchAccounts(array &$form, FormStateInterface $form_state) : array {
    $user_entity_id = $form_state->getValue('user');
    $user = $this->entityTypeManager->getStorage('media_mpx_user')->load($user_entity_id);

    list($account, $accounts) = $this->ignoreThisForNow($user);

    $options = [];
    foreach ($accounts as $account) {
      $options[(string) $account->getId()] = $this->t('@title (@id)', [
        '@title' => $account->getTitle(),
        '@id' => end(explode('/', $account->getId()->getPath())),
      ]);
    }
    return [
      'accounts_wrapper' => [
        '#prefix' => '<div id="media-mpx-accounts">',
        'accounts' => [
          // If I change this to 'select' everything works.
          '#type' => 'radios',
          '#title' => $this->t('mpx account'),
          '#options' => $options,
        ],
        '#suffix' => '</div>',
      ],
    ];
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
   * @param $user
   *
   * @return array
   */
  private function ignoreThisForNow($user): array {
    $manager = \Lullabot\Mpx\DataService\DataServiceManager::basicDiscovery();
    $client = new \Lullabot\Mpx\Client(\Drupal::httpClient());
    $store = new \Lullabot\DrupalSymfonyLock\DrupalStore(\Drupal::lock());
    $p = new \HighWire\DrupalPSR16\Cache(\Drupal::cache('default'));
    $p = new \Symfony\Component\Cache\Adapter\SimpleCacheAdapter($p);
    $tokenCachePool = new \Lullabot\Mpx\TokenCachePool($p);
    $mpx_user = new \Lullabot\Mpx\Service\IdentityManagement\User($user->getUsername(), $user->getPassword());
    $session = new \Lullabot\Mpx\Service\IdentityManagement\UserSession($mpx_user, $client, $store, $tokenCachePool);
    $client = new \Lullabot\Mpx\AuthenticatedClient($client, $session);
    $data_service = $manager->getDataService('Access Data Service', 'Account', '1.0');
    $dof = new \Lullabot\Mpx\DataService\DataObjectFactory($data_service, $client);

    $fields = new \Lullabot\Mpx\DataService\ByFields();

    $account = new \Lullabot\Mpx\DataService\Access\Account();
    $account->setId($session->acquireToken()->getUserId());
    /** @var \Lullabot\Mpx\DataService\Access\Account $account */
    $accounts = $dof->select($fields, $account);
    return [$account, $accounts];
  }

}
