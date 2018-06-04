<?php

namespace Drupal\Tests\media_mpx\Kernel\Event;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\media_mpx\Entity\Account;
use Drupal\media_mpx\Entity\User;
use Drupal\media_mpx_test\JsonResponse;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use Lullabot\Mpx\Client;

/**
 * Base class for event example tests.
 */
abstract class EventTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'user',
    'file',
    'image',
    'media',
    'media_mpx',
    'media_mpx_test',
  ];

  /**
   * The media source under test.
   *
   * @var \Drupal\media_mpx\Plugin\media\Source\Media
   */
  protected $mediaSource;

  /**
   * The media type interface under test.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $mediaType;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('media');
    $handler = new MockHandler([
      new JsonResponse(200, [], 'signin-success.json'),
      new JsonResponse(200, [], 'media-object.json'),
    ]);
    $client = new Client(new GuzzleClient(['handler' => $handler]));
    $this->container->set('media_mpx.client', $client);
    $user = User::create([
      'label' => 'JavaScript test user',
      'id' => 'mpx_testing_example_com',
      'username' => 'mpx/testing@example.com',
      'password' => 'SECRET',
    ]);
    $user->save();
    $account = Account::create([
      'label' => 'JavaScript test account',
      'id' => 'mpx_account',
      'user' => $user->id(),
      'account' => 'http://example.com/account/1',
      'public_id' => 'public-id',
    ]);
    $account->save();
    $this->mediaType = MediaType::create([
      'id' => 'mpx',
      'label' => 'mpx media type',
      'source' => 'media_mpx_media',
      'source_configuration' => [
        'source_field' => 'field_media_media_mpx_media',
        'account' => $account->id(),
      ],
    ]);
    $this->mediaType->save();
    $source_field = $this->mediaType->getSource()->createSourceField($this->mediaType);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();

    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $media_source */
    $this->mediaSource = $this->container->get('plugin.manager.media.source')->createInstance('media_mpx_media', [
      'account' => $account->id(),
      'source_field' => 'field_media_media_mpx_media',
    ]);
  }

}
