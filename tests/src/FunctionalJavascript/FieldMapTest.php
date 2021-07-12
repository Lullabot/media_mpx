<?php

namespace Drupal\Tests\media_mpx\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\media_mpx\Entity\Account;
use Drupal\media_mpx\Entity\User;
use Drupal\media_mpx_test\JsonResponse;

/**
 * Tests mapping a custom mpx field to a Drupal field.
 *
 * @group media_mpx
 */
class FieldMapTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $minkDefaultDriverClass = DrupalSelenium2Driver::class;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_mpx',
    'media_mpx_test',
    'field_ui',
  ];

  /**
   * Tests mapping a custom field.
   *
   * This test:
   *   - Relies on a custom field defined in media_mpx_test.
   *   - Validates the UI for mapping a custom field.
   *   - Validates saving an mpx media entity and that the field is visible.
   */
  public function testMapCustomField() {
    $this->container->get('config.factory')
      ->getEditable('media.settings')
      ->set('standalone_url', TRUE)
      ->save(TRUE);
    $this->container->get('router.builder')->rebuild();

    /** @var \Drupal\media_mpx_test\MockClientFactory $factory */
    $factory = $this->container->get('media_mpx.client_factory');
    $factory->getMockHandler()->append([
      new JsonResponse(200, [], 'signin-success.json'),
      new JsonResponse(200, [], 'media-object.json'),
    ]);

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
    ]);
    $media_type->save();
    $source_field = $media_type->getSource()->createSourceField($media_type);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();

    // This saves us having to mock thumbnail http requests.
    $media_type->setQueueThumbnailDownloadsStatus(TRUE);
    $media_type
      ->set('source_configuration', [
        'source_field' => $source_field->getName(),
        'account' => $account->id(),
      ])
      ->save();

    // Add the new field to the default form and view displays for this
    // media type.
    if ($source_field->isDisplayConfigurable('form')) {
      $display = $this->container->get('entity_display.repository')->getFormDisplay('media', $media_type->id(), 'default');
      $media_type->getSource()->prepareFormDisplay($media_type, $display);
      $display->save();
    }
    if ($source_field->isDisplayConfigurable('view')) {
      $display = $this->container->get('entity_display.repository')->getViewDisplay('media', $media_type->id(), 'default');
      $media_type->getSource()->prepareViewDisplay($media_type, $display);
      $display->save();
    }

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->create([
        'entity_type' => 'media',
        'field_name' => 'field_description',
        'type' => 'string_long',
      ]);
    $storage->save();
    $series = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->create([
        'field_storage' => $storage,
        'bundle' => 'mpx',
        'label' => 'Description',
        'required' => FALSE,
      ]);
    $series->save();

    $display = $this->container->get('entity_display.repository')->getViewDisplay('media', $media_type->id(), 'default');
    $display->setComponent('field_description');
    $display->save();

    $this->drupalLogin($this->rootUser);

    $this->drupalGet('admin/structure/media/manage/mpx');
    $this->getSession()->getPage()->selectFieldOption('Media description: A description of this content.', 'field_description');
    $this->click('#edit-submit');
    $this->drupalGet('/media/add/mpx');
    $this->getSession()->getPage()->fillField('Name', $this->randomString());
    $this->getSession()->getPage()->fillField('mpx Media', 'http://data.media.theplatform.com/media/data/Media/2602559');
    $this->click('#edit-submit');
    $this->drupalGet('/media/1');

    $this->assertSession()->responseContains("Our CEO Ian Blaine discusses a bit of thePlatform's history");
  }

}
