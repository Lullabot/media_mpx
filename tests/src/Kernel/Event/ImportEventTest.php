<?php

namespace Drupal\Tests\media_mpx\Kernel\Event;

use Drupal\media\Entity\MediaType;
use Drupal\media_mpx\Entity\Account;
use Drupal\media_mpx\Entity\User;
use Drupal\media_mpx_test\Event\ImportEventSubscriber;
use Drupal\media_mpx_test\JsonResponse;
use Drupal\Tests\video_embed_field\Kernel\KernelTestBase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\Client;

/**
 * Tests the event subscriber for import events.
 *
 * @group media_mpx
 */
class ImportEventTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'file',
    'media',
    'media_mpx',
    'media_mpx_test',
  ];

  /**
   * Tests triggering an import event.
   *
   * @covers \Drupal\media_mpx\DataObjectImporter::importItem
   */
  public function testImportEvent() {
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
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = MediaType::create([
      'id' => 'mpx',
      'label' => 'mpx media type',
      'source' => 'media_mpx_media',
      'source_configuration' => [
        'source_field' => 'field_media_media_mpx_media',
        'account' => $account->id(),
      ],
    ]);
    $media_type->save();
    $source_field = $media_type->getSource()->createSourceField($media_type);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();

    $this->container->get('event_dispatcher')->addSubscriber(new ImportEventSubscriber());

    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $media_source */
    $media_source = $this->container->get('plugin.manager.media.source')->createInstance('media_mpx_media', [
      'account' => $account->id(),
      'source_field' => 'field_media_media_mpx_media',
    ]);
    $dof = $this->container->get('media_mpx.data_object_factory_creator')->fromMediaSource($media_source);
    $object = $dof->load(new Uri('http://data.media.theplatform.com/media/data/Media/2602559'))->wait();

    $importer = $this->container->get('media_mpx.data_object_importer');
    $importer->importItem($object, $media_type);

    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('media')->loadMultiple());
  }

}
