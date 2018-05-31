<?php

namespace Drupal\media_mpx\Event;

use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use Lullabot\Mpx\DataService\ByFields;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event class for when content is being imported.
 *
 * @package Drupal\media_mpx\Event
 */
class ImportSelectEvent extends Event {

  /**
   * The event type.
   */
  const IMPORT_SELECT = 'media_mpx.import_select';

  /**
   * The fields class to be used when querying mpx.
   *
   * @var \Lullabot\Mpx\DataService\ByFields
   */
  private $fields;

  /**
   * The media source being imported for.
   *
   * @var \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface
   */
  private $mediaSource;

  /**
   * ImportSelectEvent constructor.
   *
   * @param \Lullabot\Mpx\DataService\ByFields $fields
   *   The fields class to be used when querying mpx.
   * @param \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface $media_source
   *   The media source being imported for.
   */
  public function __construct(ByFields $fields, MpxMediaSourceInterface $media_source) {
    $this->fields = $fields;
    $this->mediaSource = $media_source;
  }

  /**
   * Return the fields used to limit this request.
   *
   * @return \Lullabot\Mpx\DataService\ByFields
   *   The ByFields object.
   */
  public function getFields(): ByFields {
    return $this->fields;
  }

  /**
   * Return the media source plugin being used for importing.
   *
   * @return \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface
   *   The media source plugin.
   */
  public function getMediaSource(): MpxMediaSourceInterface {
    return $this->mediaSource;
  }

}
