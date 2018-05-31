<?php

namespace Drupal\media_mpx\Event;

use Lullabot\Mpx\DataService\ObjectInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class representing an import event.
 */
class ImportEvent extends Event {

  /**
   * The import event type.
   */
  const IMPORT = 'media_mpx.import';

  /**
   * The mpx object that was loaded from mpx.
   *
   * @var \Lullabot\Mpx\DataService\ObjectInterface
   */
  private $mpxObject;

  /**
   * The array of entities that would be created or updated.
   *
   * @var \Drupal\media\MediaInterface[]
   */
  private $entities;

  /**
   * ImportEvent constructor.
   *
   * @param \Lullabot\Mpx\DataService\ObjectInterface $mpx_object
   *   The mpx object that was loaded from mpx.
   * @param \Drupal\media\MediaInterface[] $entities
   *   The array of media entities being created or updated.
   */
  public function __construct(ObjectInterface $mpx_object, array &$entities) {
    $this->mpxObject = $mpx_object;
    $this->entities = $entities;
  }

  /**
   * Return the mpx object.
   *
   * @return \Lullabot\Mpx\DataService\ObjectInterface
   *   The mpx object triggering this import event.
   */
  public function getMpxObject(): ObjectInterface {
    return $this->mpxObject;
  }

  /**
   * Return the array of media entities being created or updated.
   *
   * @return \Drupal\media\MediaInterface[]
   *   The array of media entities.
   */
  public function getEntities(): array {
    return $this->entities;
  }

  /**
   * Set the media entities for this import event.
   *
   * This method is only needed if entities are being added or removed in an
   * event subscriber.
   *
   * @param \Drupal\media\MediaInterface[] $entities
   *   The array of media entities to set.
   */
  public function setEntities(array $entities) {
    $this->entities = $entities;
  }

}
