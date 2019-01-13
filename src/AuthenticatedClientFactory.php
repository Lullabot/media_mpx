<?php

namespace Drupal\media_mpx;

use Drupal\media_mpx\Entity\UserInterface;
use Lullabot\Mpx\AuthenticatedClient;
use Lullabot\Mpx\Client;
use Lullabot\Mpx\DataService\IdInterface;
use Lullabot\Mpx\Service\IdentityManagement\UserSession;

/**
 * Factory to create Authenticated mpx Clients.
 */
class AuthenticatedClientFactory {

  /**
   * The underlying client for all mpx requests.
   *
   * @var \Lullabot\Mpx\Client
   */
  private $client;

  /**
   * The factory used to create user sessions.
   *
   * @var \Drupal\media_mpx\UserSessionFactory
   */
  private $userSessionFactory;

  /**
   * Construct a new AuthenticatedClientFactory.
   *
   * @param \Lullabot\Mpx\Client $client
   *   The underlying client for all mpx requests.
   * @param \Drupal\media_mpx\UserSessionFactory $userSessionFactory
   *   The factory used to create user sessions.
   */
  public function __construct(Client $client, UserSessionFactory $userSessionFactory) {
    $this->client = $client;
    $this->userSessionFactory = $userSessionFactory;
  }

  /**
   * Create a new Authenticated Client from an existing user session.
   *
   * @param \Lullabot\Mpx\Service\IdentityManagement\UserSession $session
   *   The user session to create the client for.
   * @param \Lullabot\Mpx\DataService\IdInterface|null $account
   *   (optional) An account to use as the account context for requests.
   *
   * @return \Lullabot\Mpx\AuthenticatedClient
   *   A new authenticated client.
   */
  public function fromSession(UserSession $session, IdInterface $account = NULL): AuthenticatedClient {
    $authenticatedClient = new AuthenticatedClient($this->client, $session, $account);
    $authenticatedClient->setTokenDuration(300);
    return $authenticatedClient;
  }

  /**
   * Create a session for a user and return an authenticated client.
   *
   * @param \Drupal\media_mpx\Entity\UserInterface $user
   *   The user to create the session for.
   * @param \Lullabot\Mpx\DataService\IdInterface|null $account
   *   (optional) An account to use as the account context for requests.
   *
   * @return \Lullabot\Mpx\AuthenticatedClient
   *   A new authenticated client.
   */
  public function fromUser(UserInterface $user, IdInterface $account = NULL): AuthenticatedClient {
    $session = $this->userSessionFactory->fromUser($user);
    return $this->fromSession($session, $account);
  }

  /**
   * Create a session for an account and return an authenticated client.
   *
   * @param \Drupal\media_mpx\AccountInterface $account
   *   An account to use as the account context for requests.
   *
   * @return \Lullabot\Mpx\AuthenticatedClient
   *   A new authenticated client.
   */
  public function fromAccount(AccountInterface $account): AuthenticatedClient {
    $user = $account->getUserEntity();
    $session = $this->userSessionFactory->fromUser($user);
    return $this->fromSession($session, $account);
  }

}
