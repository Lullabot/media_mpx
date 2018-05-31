<?php

namespace Drupal\media_mpx_test\Event;

use Drupal\media_mpx\Event\ImportEvent;
use Lullabot\Mpx\DataService\Media\Media;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Example import event subscriber filtering assets by country.
 *
 * Normally event subscribers need to be registered in the module's
 * services.yml file. However, we only want this subscriber enabled for specific
 * tests, so we omit that. See the README for an example.
 */
class ImportEventSubscriber implements EventSubscriberInterface {

  /**
   * Do not import assets that are for outside the United States.
   *
   * @param \Drupal\media_mpx\Event\ImportEvent $event
   *   The import event that was dispatched.
   */
  public function filterByCountry(ImportEvent $event) {
    $object = $event->getMpxObject();
    if ($object instanceof Media && !in_array('US', $object->getCountries())) {
      $event->setEntities([]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ImportEvent::IMPORT => 'filterByCountry',
    ];
  }

}
