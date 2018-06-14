<?php

namespace Drupal\Tests\media_mpx\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media_mpx\Entity\User;
use Drupal\media_mpx\Form\AccountForm;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Lullabot\Mpx\Client;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the Account form.
 *
 * @group media_mpx
 */
class AccountFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'media_mpx',
  ];

  /**
   * Tests that invalid credentials don't cause an exception.
   */
  public function testAuthError() {
    $user = User::create([
      'label' => 'JavaScript test user',
      'id' => 'mpx_testing_example_com',
      'username' => 'mpx/testing@example.com',
      'password' => 'SECRET',
    ]);
    $user->save();

    $handler = new MockHandler([
      function (RequestInterface $request) {
        throw new ClientException('Unauthorized', $request, new Response(401));
      },
    ]);
    $client = new Client(new GuzzleClient(['handler' => $handler]));
    $this->container->set('media_mpx.client', $client);

    $accountForm = AccountForm::create($this->container);
    $accountForm->setEntityTypeManager($this->container->get('entity_type.manager'));

    $form = [];
    $formState = new FormState();
    $formState->setValue('user', 'mpx_testing_example_com');

    $this->assertEmpty($accountForm->fetchAccounts($form, $formState));
  }

}
