<?php

namespace Drupal\Tests\media_mpx\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\media_mpx\Entity\Account;
use Drupal\media_mpx\Entity\User;
use Drupal\media_mpx_test\JsonResponse;

/**
 * Tests the video player formatter.
 *
 * @group media_mpx
 */
class PlayerFormatterTest extends WebDriverTestBase {

  protected $minkDefaultDriverClass = DrupalSelenium2Driver::class;

  protected static $modules = [
    'media_mpx',
    'media_mpx_test',
    'field_ui',
  ];

  /**
   * Tests that a player formatter can be viewed.
   *
   * Note that the player itself will not be functional - all we care about is
   * that an iframe is properly rendered.
   */
  public function testViewPlayerFormatter() {
    /** @var \Drupal\media_mpx_test\MockClientFactory $factory */
    $factory = $this->container->get('media_mpx.client_factory');
    $factory->getMockHandler()->append([
      new JsonResponse(200, [], 'signin-success.json'),
      new JsonResponse(200, [], 'resolveDomain.json'),
      new JsonResponse(200, [], 'select-player.json'),
      new JsonResponse(200, [], 'select-player.json'),
      new JsonResponse(200, [], 'select-player.json'),
      new JsonResponse(200, [], 'select-player.json'),
      new JsonResponse(200, [], 'media-object.json'),
      new JsonResponse(200, [], 'player-object.json'),
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
      // @todo Replace entity_get_form_display() when #2367933 is done.
      // https://www.drupal.org/node/2872159.
      $display = entity_get_form_display('media', $media_type->id(), 'default');
      $media_type->getSource()->prepareFormDisplay($media_type, $display);
      $display->save();
    }
    if ($source_field->isDisplayConfigurable('view')) {
      // @todo Replace entity_get_display() when #2367933 is done.
      // https://www.drupal.org/node/2872159.
      $display = entity_get_display('media', $media_type->id(), 'default');
      $media_type->getSource()->prepareViewDisplay($media_type, $display);
      $display->save();
    }

    $this->drupalLogin($this->rootUser);

    $this->drupalGet('admin/structure/media/manage/mpx/display');
    $this->getSession()->getPage()->selectFieldOption('fields[field_media_media_mpx_media][type]', 'media_mpx_video');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->click($this->drupalSelector("edit-fields-field-media-media-mpx-media-settings-edit"));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->click($this->drupalSelector("edit-fields-field-media-media-mpx-media-settings-edit-form-actions-save-settings"));
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->click('#edit-submit');
    $this->assertSession()->pageTextContains("Your settings have been saved.");

    $this->drupalGet('/media/add/mpx');
    $this->getSession()->getPage()->fillField('Name', $this->randomString());
    $this->getSession()->getPage()->fillField('mpx Media', 'http://data.media.theplatform.com/media/data/Media/2602559');
    $this->click('#edit-submit');
    $this->drupalGet('/media/1');
    $this->assertSession()->responseContains('<iframe class="mpx-player mpx-player-account--' . $account->id() . '" src="https://player.theplatform.com/p/public-id/DemoPlayer/select/media/Zy1n9JQPy6hw?autoPlay=false&amp;playAll=false"></iframe>');
  }

  /**
   * Return a drupal-data-selector string.
   *
   * @param string $id
   *   The id of the selector.
   *
   * @return string
   *   The selector.
   */
  private function drupalSelector(string $id): string {
    return sprintf('[data-drupal-selector="%s"]', $id);
  }

}
