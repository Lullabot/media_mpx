<?php

namespace Drupal\Tests\media_mpx\Kernel\Access;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\Tests\media_mpx\Kernel\MediaMpxTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;

/**
 * Tests the MediaAvailableAccess service.
 *
 * @group media_mpx
 * @coversDefaultClass \Drupal\media_mpx\Access\MediaAvailableAccess
 */
class MediaAvailableAccessTest extends MediaMpxTestBase {
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'file',
    'datetime',
    'user',
    'image',
    'guzzle_cache',
    'media',
    'media_mpx',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');

    $this->createExpirationAndAvailableFields();
  }

  /**
   * Tests Media Available Access can handle timestamp access fields.
   *
   * @covers ::getDateTime
   */
  public function testAccessChecksForMediaItemWithTimestampFields() {
    $anonymous = User::getAnonymousUser();
    $media = $this->createMedia();

    $field_map = [];
    $field_map['Media:availableDate'] = 'field_available_date_timestamp';
    $field_map['Media:expirationDate'] = 'field_expiration_date_timestamp';
    $this->mediaType->setFieldMap($field_map);
    $this->mediaType->save();

    /** @var \Drupal\media_mpx\Access\MediaAvailableAccess $available_access_service */
    $available_access_service = $this->container->get('media_mpx.media_available_access');

    $combinations = $this->timestampValuesWithAccessResultsProvider();
    foreach ($combinations as $combination) {
      list($available, $expiration, $expectedClass) = $combination;
      $media->set('field_available_date_timestamp', $available);
      $media->set('field_expiration_date_timestamp', $expiration);
      $access = $available_access_service->view($media, $anonymous);
      $this->assertInstanceOf($expectedClass, $access);
    }
  }

  /**
   * Tests Media Available Access can handle datetime access fields.
   *
   * @covers ::getDateTime
   */
  public function testAccessChecksForMediaItemWithDateTimeFields() {
    $anonymous = User::getAnonymousUser();
    $media = $this->createMedia();

    $this->setExpirationFieldMapDateTime();

    /** @var \Drupal\media_mpx\Access\MediaAvailableAccess $available_access_service */
    $available_access_service = $this->container->get('media_mpx.media_available_access');

    $combinations = $this->dateTimeValuesWithAccessResultsProvider();
    foreach ($combinations as $combination) {
      list($available, $expiration, $expectedClass) = $combination;
      $media->set('field_available_date_datetime', $available->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
      $media->set('field_expiration_date_datetime', $expiration->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
      $access = $available_access_service->view($media, $anonymous);
      $this->assertInstanceOf($expectedClass, $access);
    }
  }

  /**
   * Test that availability access is skipped if you can edit the media.
   */
  public function testAccessWithEditPermission() {
    $user = $this->setUpCurrentUser([], ['update any media']);

    $this->setExpirationFieldMapDateTime();

    $media = $this->createMedia();

    // Set the video to expired 10 seconds in the past.
    $media->set('field_available_date_datetime', 0);
    $media->set('field_expiration_date_datetime', time() - 10);

    /** @var \Drupal\media_mpx\Access\MediaAvailableAccess $available_access_service */
    $available_access_service = $this->container->get('media_mpx.media_available_access');
    $this->assertTrue($available_access_service->view($media, $user)->isNeutral());
  }

  /**
   * Test that availability access is used if you can not edit the media.
   */
  public function testAccessWithoutEditPermission() {
    $user = $this->setUpCurrentUser();

    $this->setExpirationFieldMapDateTime();

    $media = $this->createMedia();

    $media->set('field_available_date_datetime', 0);
    $media->set('field_expiration_date_datetime', time() - 10);

    /** @var \Drupal\media_mpx\Access\MediaAvailableAccess $available_access_service */
    $available_access_service = $this->container->get('media_mpx.media_available_access');
    $this->assertTrue($available_access_service->view($media, $user)->isForbidden());
  }

  /**
   * Data provider of available and expiration dates as timestamps.
   *
   * Not used via the @dataProvider annotation on purpose, as that'll make each
   * iteration run the setUp logic, which is not desired for these tests.
   *
   * @return array
   *   The array of values to test with, alongside the expected access result
   *   for each case.
   */
  public function timestampValuesWithAccessResultsProvider() {
    return [
      // Available | Expiration | expected AccessResult object.
      [time() - 86400, time() + 3600, AccessResultNeutral::class],
      [time() + 3600, time() + 7200, AccessResultForbidden::class],
      [time() - 3600, time() - 1800, AccessResultForbidden::class],
      [time() + 3600, time() - 1800, AccessResultForbidden::class],
    ];
  }

  /**
   * Data provider of available and expiration dates as datetime objects.
   *
   * Not used via the @dataProvider annotation on purpose, as that'll make each
   * iteration run the setUp logic, which is not desired for these tests.
   *
   * @return array
   *   The array of values to test with, alongside the expected access result
   *   for each case.
   */
  public function dateTimeValuesWithAccessResultsProvider() {
    return [
      // Available | Expiration | expected AccessResult object.
      [
        \DateTime::createFromFormat('U', time() - 86400),
        \DateTime::createFromFormat('U', time() + 86400),
        AccessResultNeutral::class,
      ],
      [
        \DateTime::createFromFormat('U', time() + 86400),
        \DateTime::createFromFormat('U', time() + 100000),
        AccessResultForbidden::class,
      ],
      [
        \DateTime::createFromFormat('U', time() - 3600),
        \DateTime::createFromFormat('U', time() - 28800),
        AccessResultForbidden::class,
      ],
      [
        \DateTime::createFromFormat('U', time() + 3600),
        \DateTime::createFromFormat('U', time() - 1800),
        AccessResultForbidden::class,
      ],
    ];
  }

  /**
   * Creates necessary datetime and integer fields for Access tests.
   */
  protected function createExpirationAndAvailableFields() {
    $mpx_media_type_id = $this->mediaType->get('id');
    $field_avail_timestamp_storage = FieldStorageConfig::create([
      'field_name' => 'field_available_date_timestamp',
      'entity_type' => 'media',
      'type' => 'integer',
      'cardinality' => 1,
    ]);
    $field_avail_timestamp_storage->save();

    $field_avail_timestamp = FieldConfig::create([
      'field_storage' => $field_avail_timestamp_storage,
      'field_name' => 'field_available_date_timestamp',
      'label' => 'Available Date - Timestamp',
      'description' => 'Available Date stored as a timestamp',
      'entity_type' => 'media',
      'bundle' => $mpx_media_type_id,
      'required' => TRUE,
    ]);
    $field_avail_timestamp->save();

    $field_exp_timestamp_storage = FieldStorageConfig::create([
      'field_name' => 'field_expiration_date_timestamp',
      'entity_type' => 'media',
      'type' => 'integer',
      'cardinality' => 1,
    ]);
    $field_exp_timestamp_storage->save();

    $field_exp_timestamp = FieldConfig::create([
      'field_storage' => $field_exp_timestamp_storage,
      'field_name' => 'field_expiration_date_timestamp',
      'label' => 'Expiration Date - Timestamp',
      'description' => 'Expiration Date stored as a timestamp',
      'entity_type' => 'media',
      'bundle' => $mpx_media_type_id,
      'required' => TRUE,
    ]);
    $field_exp_timestamp->save();

    $field_avail_datetime_storage = FieldStorageConfig::create([
      'field_name' => 'field_available_date_datetime',
      'entity_type' => 'media',
      'type' => 'datetime',
      'settings' => ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      'cardinality' => 1,
    ]);
    $field_avail_datetime_storage->save();

    $field_avail_datetime = FieldConfig::create([
      'field_storage' => $field_avail_datetime_storage,
      'field_name' => 'field_available_date_datetime',
      'label' => 'Available Date - Datetime',
      'description' => 'Available Date stored as datetime data.',
      'entity_type' => 'media',
      'bundle' => $mpx_media_type_id,
      'required' => TRUE,
    ]);
    $field_avail_datetime->save();

    $field_exp_datetime_storage = FieldStorageConfig::create([
      'field_name' => 'field_expiration_date_datetime',
      'entity_type' => 'media',
      'type' => 'datetime',
      'settings' => ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE],
      'cardinality' => 1,
    ]);
    $field_exp_datetime_storage->save();

    $field_exp_datetime = FieldConfig::create([
      'field_storage' => $field_exp_datetime_storage,
      'field_name' => 'field_expiration_date_datetime',
      'label' => 'Expiration Date - Datetime',
      'description' => 'Expiration Date stored as datetime data.',
      'entity_type' => 'media',
      'bundle' => $mpx_media_type_id,
      'required' => TRUE,
    ]);
    $field_exp_datetime->save();
  }

  /**
   * Set field mappings for datetime date fields.
   */
  private function setExpirationFieldMapDateTime() {
    $field_map = [];
    $field_map['Media:availableDate'] = 'field_available_date_datetime';
    $field_map['Media:expirationDate'] = 'field_expiration_date_datetime';
    $this->mediaType->setFieldMap($field_map);
    $this->mediaType->save();
  }

  /**
   * Create a media entity.
   *
   * @return \Drupal\media\Entity\Media
   *   Returns an unsaved media entity.
   */
  private function createMedia() {
    $media = Media::create([
      'bundle' => $this->mediaType->get('id'),
      'title' => 'test available access',
      'field_media_media_mpx_media' => 'http://example.com/1234',
    ]);
    return $media;
  }

}
