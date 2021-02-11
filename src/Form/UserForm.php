<?php

namespace Drupal\media_mpx\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\UserSessionFactory;
use GuzzleHttp\Exception\TransferException;
use Lullabot\Mpx\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for the mpx User entity type.
 *
 * @see \Drupal\media_mpx\Entity\User
 */
class UserForm extends EntityForm {

  /**
   * The user being edited.
   *
   * @var \Drupal\media_mpx\Entity\UserInterface
   */
  protected $entity;

  /**
   * The factory used to test user credentials.
   *
   * @var \Drupal\media_mpx\UserSessionFactory
   */
  protected $userSessionFactory;

  /**
   * The logger for unhandled mpx errors.
   *
   * @var \Drupal\media_mpx\MpxLogger
   */
  protected $mpxLogger;

  /**
   * UserForm constructor.
   *
   * @param \Drupal\media_mpx\UserSessionFactory $userSessionFactory
   *   The factory used to test user credentials.
   * @param \Drupal\media_mpx\MpxLogger $mpxLogger
   *   The logger for unhandled mpx errors.
   */
  public function __construct(UserSessionFactory $userSessionFactory, MpxLogger $mpxLogger) {
    $this->userSessionFactory = $userSessionFactory;
    $this->mpxLogger = $mpxLogger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('media_mpx.user_session_factory'),
      $container->get('media_mpx.exception_logger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // @todo Email validation.
    // @todo html5 placeholder
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('mpx user name'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('The MPX user name. Typically, this is an email address. See the <a href="@user-docs">user setup documentation</a> for more details.', [
        '@user-docs' => 'https://docs.theplatform.com/help/setting-up-new-mpx-users',
      ]),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\media_mpx\Entity\User::load',
        'source' => ['username'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('mpx password'),
      '#description' => $this->t('The mpx user password. This can be blank if the password is set through settings.php.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $this->addMpxDirectory();

    if (empty($this->entity->getPassword())) {
      $this->messenger()->addWarning($this->t('The mpx user credentials were not validated as no password was specified. This is expected if passwords are being injected through settings.php.'));
      return;
    }

    $this->validateMpxCredentials($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->addMpxDirectory();
    $status = $this->entity->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label mpx User.', [
          '%label' => $this->entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label mpx User.', [
          '%label' => $this->entity->label(),
        ]));
    }
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

  /**
   * Set an mpx directory on the username if one is not specified.
   *
   * By default mpx accounts are in the 'mpx' directory. Only legacy accounts
   * are in other directories. If no directory is specified, add it
   * automatically.
   */
  private function addMpxDirectory() {
    if (strpos($this->entity->getUsername(), '/') === FALSE) {
      $this->entity->set('username', 'mpx/' . $this->entity->getUsername());
    }
  }

  /**
   * Validate the mpx username and password.
   *
   * @param array &$form
   *   The settings form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function validateMpxCredentials(array &$form, FormStateInterface $form_state) {
    $session = $this->userSessionFactory->fromUser($this->entity);
    try {
      try {
        $session->acquireToken(1);
      }
      catch (ClientException $e) {
        if ($e->getCode() == 401 || $e->getCode() == 403) {
          $form_state->setError($form, $this->t('Access was denied connecting to mpx. @error',
            [
              '@error' => $e->getMessage(),
            ])
          );
          return;
        }
        throw $e;
      }
    }
    catch (TransferException $e) {
      $form_state->setError($form, $this->t('An error occurred connecting to mpx. The full error has been logged. @error',
        [
          '@error' => $e->getMessage(),
        ])
      );
      $this->mpxLogger->logException($e);
    }
  }

}
