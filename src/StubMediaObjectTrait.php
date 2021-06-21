<?php

namespace Drupal\media_mpx;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\media\MediaInterface;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\DateTime\ConcreteDateTime;
use Lullabot\Mpx\DataService\DateTime\DateTimeFormatInterface;
use Lullabot\Mpx\DataService\DateTime\NullDateTime;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;

/**
 * Helper method to create stub mpx media objects.
 *
 * Used in cases where we can derive a stub mpx media object from data in Drupal
 * rather than call out via the API.
 */
trait StubMediaObjectTrait {

  /**
   * Returns an mpx media object for availability calculations.
   *
   * If both the available and expiration date fields have been mapped to Drupal
   * fields, those are used instead of potentially loading an mpx object from
   * their API.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media to load the mpx object for.
   *
   * @return \Lullabot\Mpx\DataService\Media\Media
   *   A media object with an ID and availability dates.
   */
  protected function getStubMediaObject(MediaInterface $media): MpxMedia {
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $source */
    $source = $media->getSource();
    $field_map = $media->bundle->entity->getFieldMap();

    // Only used mapped fields if both are available.
    if (isset($field_map['Media:availableDate']) && isset($field_map['Media:expirationDate'])) {
      $available = $this->getDateTime($media, $field_map['Media:availableDate']);

      $expiration = $this->getDateTime($media, $field_map['Media:expirationDate']);

      $mpx_object = new MpxMedia();
      $mpx_object->setId(new Uri($source->getSourceFieldValue($media)));
      $mpx_object->setAvailableDate($available);
      $mpx_object->setExpirationDate($expiration);
    }
    else {
      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_object */
      $mpx_object = $source->getMpxObject($media);
    }

    return $mpx_object;
  }

  /**
   * Return a new formattable date time object.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The entity with the field containing the date/time value.
   * @param string $field_name
   *   The ID of the field containing the date/time value.
   *
   * @return \Lullabot\Mpx\DataService\DateTime\DateTimeFormatInterface
   *   A formattable date/time.
   */
  protected function getDateTime(MediaInterface $media, string $field_name): DateTimeFormatInterface {
    $fieldItemList = $media->get($field_name);
    if ($fieldItemList->isEmpty()) {
      return new NullDateTime();
    }

    if ($date_time = \DateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $fieldItemList->value)) {
      return new ConcreteDateTime($date_time);
    }

    // One last attempt to get a date time object if the value is a timestamp.
    if ($date_time = \DateTime::createFromFormat('U', $fieldItemList->value)) {
      return new ConcreteDateTime($date_time);
    }

    return new NullDateTime();
  }

}
