<?php

namespace Drupal\Tests\media_mpx\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\media_mpx\Entity\Account;
use Drupal\media_mpx\Entity\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use Lullabot\Mpx\Client;

/**
 * Base class for media_mpx kernel tests.
 */
abstract class MediaMpxTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'file',
    'user',
    'image',
    'guzzle_cache',
    'media',
    'media_mpx',
  ];

  /**
   * The mock HTTP handler.
   *
   * @var \GuzzleHttp\Handler\MockHandler
   */
  protected $handler;

  /**
   * The media source being tested.
   *
   * @var \Drupal\media_mpx\Plugin\media\Source\Media
   */
  protected $source;

  /**
   * The media type being tested.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $mediaType;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->handler = new MockHandler();
    /** @var \GuzzleHttp\HandlerStack $handler */
    $handler = $this->container->get('media_mpx.client')->getConfig('handler');
    $handler->push(function (callable $handler) {
      return $this->handler;
    });
    $handler->remove('test.http_client.middleware');
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

    $manager = $this->container->get('plugin.manager.media.source');
    $this->source = $manager->createInstance('media_mpx_media', [
      'source_field' => 'field_media_media_mpx_media',
      'account' => $account->id(),
    ]);

    $this->mediaType = MediaType::create([
      'id' => 'mpx',
      'label' => 'mpx media type',
      'source' => 'media_mpx_media',
      'queue_thumbnail_downloads' => TRUE,
      'source_configuration' => [
        'source_field' => 'field_media_media_mpx_media',
        'account' => $account->id(),
      ],
    ]);
    $this->mediaType->save();
    $source_field = $this->mediaType->getSource()->createSourceField($this->mediaType);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();

  }

}
