<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
use Lullabot\Mpx\DataService\Player\Player as MpxPlayer;

/**
 * Media source for mpx Player items.
 *
 * @see \Lullabot\Mpx\DataService\Player\Player
 * @see https://docs.theplatform.com/help/player-player-object
 *
 * @todo Change the default thumbnail.
 *
 * @MediaSource(
 *   id = "media_mpx_player",
 *   label = @Translation("mpx Player"),
 *   description = @Translation("mpx player data, such as video players."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "video.png",
 *   media_mpx = {
 *     "service_name" = "Player Data Service",
 *     "object_type" = "Player",
 *     "schema_version" = "1.6",
 *   },
 * )
 */
class Player extends MediaSourceBase implements MediaSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    $extractor = $this->propertyExtractor();

    $metadata = [];
    foreach ($extractor->getProperties(MpxPlayer::class) as $property) {
      $metadata[$property] = $extractor->getShortDescription(MpxPlayer::class, $property);
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
      $extractor = $this->propertyExtractor();


      if (in_array($attribute_name, $extractor->getProperties(MpxPlayer::class))) {
        return $this->getReflectedProperty($media, $attribute_name, $this->getMpxObject($media));
      }
    };

    return parent::getMetadata($media, $attribute_name);
  }

}
