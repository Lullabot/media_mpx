<?php

namespace Drupal\media_mpx\Event;

use Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface;
use Lullabot\Mpx\DataService\ObjectListQuery;
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
   * @var \Lullabot\Mpx\DataService\ObjectListQuery
   */
  private $query;

  /**
   * The media source being imported for.
   *
   * @var \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface
   */
  private $mediaSource;

  /**
   * ImportSelectEvent constructor.
   *
   * @param \Lullabot\Mpx\DataService\ObjectListQuery $query
   *   The fields class to be used when querying mpx.
   * @param \Drupal\media_mpx\Plugin\media\Source\MpxMediaSourceInterface $media_source
   *   The media source being imported for.
   */
  public function __construct(ObjectListQuery $query, MpxMediaSourceInterface $media_source) {
    $this->query = $query;
    $this->mediaSource = $media_source;
  }

  /**
   * Return the fields used to limit this request.
   *
   * @return \Lullabot\Mpx\DataService\ObjectListQuery
   *   The ObjectListQuery object.
   */
  public function getFields(): ObjectListQuery {
    return $this->query;
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
