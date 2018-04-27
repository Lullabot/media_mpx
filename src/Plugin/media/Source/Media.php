<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;

/**
 * Media source for mpx Media items.
 *
 * @see \Lullabot\Mpx\DataService\Media\Media
 * @see https://docs.theplatform.com/help/media-media-object
 *
 * @MediaSource(
 *   id = "media_mpx_media",
 *   label = @Translation("mpx Media"),
 *   description = @Translation("mpx media data, such as videos."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "video.png",
 *   media_mpx = {
 *     "service_name" = "Media Data Service",
 *     "object_type" = "Media",
 *     "schema_version" = "1.10",
 *   },
 * )
 */
class Media extends MediaSourceBase implements MediaSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    list($propertyInfo, $properties) = $this->extractMediaProperties(MpxMedia::class);

    $metadata = [];
    foreach ($properties as $property) {
      $metadata[$property] = $propertyInfo->getShortDescription(MpxMedia::class, $property);
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    // Load the media type.
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    $source_field = $this->getSourceFieldDefinition($media_type);
    if (!$media->get($source_field->getName())->isEmpty()) {
      list(, $properties) = $this->extractMediaProperties(MpxMedia::class);

      if (in_array($attribute_name, $properties)) {
        $mpx_media = $this->getMpxMedia($media);

        $method = 'get' . ucfirst($attribute_name);
        // @todo At the least this should be a static cache tied to $media.
        try {
          $value = $mpx_media->$method();
        }
        catch (\TypeError $e) {
          // @todo The optional value was not set.
          // Remove this when https://github.com/Lullabot/mpx-php/issues/95 is
          // fixed.
          return parent::getMetadata($media, $attribute_name);
        }

        // @todo Is this the best way to handle complex values like dates and
        // sub-objects?
        if ($value instanceof \DateTime) {
          $value = $value->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
        }

        return $value;
      }
    };

    return parent::getMetadata($media, $attribute_name);
  }

}
