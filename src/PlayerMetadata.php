<?php

namespace Drupal\media_mpx;

use Drupal\media\Entity\Media as DrupalMedia;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;
use Lullabot\Mpx\Service\Player\Url;

/**
 * Player metadata class.
 *
 * Build the metadata keys for schema.org tags.
 */
class PlayerMetadata {

  /**
   * Drupal media entity.
   *
   * @var \Drupal\media\Entity\Media
   */
  protected $drupalMedia;

  /**
   * Mpx media data service object.
   *
   * @var \Lullabot\Mpx\DataService\Media\Media
   */
  protected $mpxMedia;

  /**
   * Player URL.
   *
   * @var \Lullabot\Mpx\Service\Player\Url
   */
  protected $playerUrl;

  /**
   * Constructs a PlayerMetadata object.
   *
   * @param \Drupal\media\Entity\Media $drupal_media
   *   Drupal media entity.
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   Mpx media data service object.
   * @param \Lullabot\Mpx\Service\Player\Url $player_url
   *   Player URL.
   */
  public function __construct(DrupalMedia $drupal_media, MpxMedia $mpx_media, Url $player_url) {
    $this->drupalMedia = $drupal_media;
    $this->mpxMedia = $mpx_media;
    $this->playerUrl = $player_url;
  }

  /**
   * Convert this object to an array.
   *
   * Suitable for use as 'meta' property of the 'media_mpx_iframe_wrapper' theme
   * hook, which are the keys for scheme.org tags output with an MPX player.
   *
   * @return array
   *   Metadata keys for schema.org tags.
   */
  public function toArray() {
    $source_plugin = $this->drupalMedia->getSource();
    return [
      'name' => $this->drupalMedia->label(),
      'thumbnailUrl' => file_create_url($source_plugin->getMetadata($this->drupalMedia, 'thumbnail_uri')),
      'description' => $this->mpxMedia->getDescription(),
      'uploadDate' => $this->mpxMedia->getAvailableDate()->format(DATE_ISO8601),
      'embedUrl' => (string) $this->playerUrl->withEmbed(TRUE),
    ];
  }

}
