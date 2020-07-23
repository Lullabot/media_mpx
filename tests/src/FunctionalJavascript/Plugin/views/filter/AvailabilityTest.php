<?php

namespace Drupal\Tests\media_mpx\FunctionalJavascript\Plugin\views\filter;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media_mpx\Entity\Account;
use Drupal\media_mpx\Entity\User;
use Drupal\media_mpx_test\JsonResponse;

/**
 * Test the Availability filter that we provide.
 *
 * @group media_mpx
 */
class AvailabilityTest extends WebDriverTestBase {

  const SHORT_DATE_FORMAT = 'm/d/Y - H:i';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_mpx',
    'media_mpx_test',
    'field_ui',
    'views',
    'views_ui',
    'datetime',
  ];

  /**
   * Creates a view of media that uses the Availability filter.
   */
  public function testViewsIntegration() {
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

    // Add new available and expiration fields.
    $available_date_storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->create([
        'entity_type' => 'media',
        'field_name' => 'field_available_date',
        'type' => 'timestamp',
      ]);
    $available_date_storage->save();
    $expiration_date_storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->create([
        'entity_type' => 'media',
        'field_name' => 'field_expiration_date',
        'type' => 'timestamp',
      ]);
    $expiration_date_storage->save();
    $available_date_field = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->create([
        'field_storage' => $available_date_storage,
        'bundle' => 'mpx',
        'label' => 'Available date',
        'required' => FALSE,
      ]);
    $available_date_field->save();
    $expiration_date_field = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->create([
        'field_storage' => $expiration_date_storage,
        'bundle' => 'mpx',
        'label' => 'Expiration date',
        'required' => FALSE,
      ]);
    $expiration_date_field->save();

    // Set the field map.
    $media_type->setFieldMap([
      'Media:availableDate' => 'field_available_date',
      'Media:expirationDate' => 'field_expiration_date',
    ]);
    $media_type->save();

    // Create some mpx media entities to test with.
    $upcoming_media = Media::create([
      'bundle' => 'mpx',
      'name' => $this->randomGenerator->name(),
      $source_field->getName() => 'http://data.media.theplatform.com/media/data/Media/2602559',
      'field_available_date' => strtotime('+1 day'),
      'field_expiration_date' => strtotime('+1 month'),
    ]);
    $upcoming_media->save();
    $available_media = Media::create([
      'bundle' => 'mpx',
      'name' => $this->randomGenerator->name(),
      $source_field->getName() => 'http://data.media.theplatform.com/media/data/Media/2602559',
      'field_available_date' => strtotime('-1 month'),
      'field_expiration_date' => strtotime('+1 month'),
    ]);
    $available_media->save();
    $expired_media = Media::create([
      'bundle' => 'mpx',
      'name' => $this->randomGenerator->name(),
      $source_field->getName() => 'http://data.media.theplatform.com/media/data/Media/2602559',
      'field_available_date' => strtotime('-1 month'),
      'field_expiration_date' => strtotime('-1 day'),
    ]);
    $expired_media->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/structure/media/manage/mpx');
    $this->assertFieldByName('field_map[Media:availableDate]', 'field_available_date');
    $this->assertFieldByName('field_map[Media:expirationDate]', 'field_expiration_date');

    // Create a view that lists all media.
    $this->drupalGet('admin/structure/views/add');
    $page->fillField('edit-label', 'mpx_media');
    $page->selectFieldOption('edit-show-wizard-key', 'media');
    $assert_session->assertWaitOnAjaxRequest();
    $page->findField('edit-page-create')->check();
    $page->selectFieldOption('page[style][style_plugin]', 'table');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Save and edit');

    // Add the available and expiration dates to the view.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#views-add-field'));
    $this->click('#views-add-field');
    $assert_session->assertWaitOnAjaxRequest();
    $page->fillField('override[controls][options_search]', 'available');
    $page->checkField('name[media__field_available_date.field_available_date]');
    // The Save and continue button has a weird structure.
    $page->find('css', 'div > button.button.button--primary.js-form-submit.form-submit.ui-button.ui-corner-all.ui-widget')->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Set the availability field options.
    $page->fillField('options[label]', 'Availability');
    $page->selectFieldOption('options[type]', 'media_mpx_availability');
    $assert_session->assertWaitOnAjaxRequest();
    $page->selectFieldOption('options[settings][date_format]', 'short');
    // The Apply and continue button has a weird structure.
    $page->find('css', 'div > button.button.button--primary.js-form-submit.form-submit.ui-button.ui-corner-all.ui-widget')->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Setup an availability filter.
    $this->click('#views-add-filter');
    $assert_session->assertWaitOnAjaxRequest();
    $page->fillField('override[controls][options_search]', 'availability');
    $page->checkField('name[media_field_data.media_mpx_availability_mpx]');
    // The Save and continue button has a weird structure.
    $page->find('css', 'div > button.button.button--primary.js-form-submit.form-submit.ui-button.ui-corner-all.ui-widget')->click();
    $assert_session->assertWaitOnAjaxRequest();

    $page->checkField('options[expose_button][checkbox][checkbox]');
    $assert_session->assertWaitOnAjaxRequest();
    $page->fillField('options[expose][label]', 'Availability');
    // The Save and continue button has a weird structure.
    $page->find('css', 'div > button.button.button--primary.js-form-submit.form-submit.ui-button.ui-corner-all.ui-widget')->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Save the view.
    $page->pressButton('edit-actions-submit');

    // Finally view the view and assert it's behavior.
    $this->drupalGet('mpx-media');
    // All should be listed by default.
    $assert_session->pageTextContains($upcoming_media->label());
    $assert_session->pageTextContains($available_media->label());
    $assert_session->pageTextContains($expired_media->label());
    // Check the formatter output.
    $assert_session->pageTextContains(sprintf('Upcoming %s', date(self::SHORT_DATE_FORMAT, $upcoming_media->field_available_date->value)));
    $assert_session->pageTextContains(sprintf('Available until %s', date(self::SHORT_DATE_FORMAT, $available_media->field_expiration_date->value)));
    $assert_session->pageTextContains(sprintf('Expired on %s', date(self::SHORT_DATE_FORMAT, $expired_media->field_expiration_date->value)));
    // Select available or upcoming, only expired should be missing.
    $page->selectFieldOption('media_mpx_availability_mpx', 'available_upcoming');
    $page->pressButton('Apply');
    $assert_session->pageTextContains($upcoming_media->label());
    $assert_session->pageTextContains($available_media->label());
    $assert_session->pageTextNotContains($expired_media->label());
    // Select available, only available should be visible.
    $page->selectFieldOption('media_mpx_availability_mpx', 'available');
    $page->pressButton('Apply');
    $assert_session->pageTextContains($available_media->label());
    $assert_session->pageTextNotContains($upcoming_media->label());
    $assert_session->pageTextNotContains($expired_media->label());
    // Select expired, only expired should be visible.
    $page->selectFieldOption('media_mpx_availability_mpx', 'expired');
    $page->pressButton('Apply');
    $assert_session->pageTextContains($expired_media->label());
    $assert_session->pageTextNotContains($upcoming_media->label());
    $assert_session->pageTextNotContains($available_media->label());
  }

}
