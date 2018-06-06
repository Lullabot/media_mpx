<?php

namespace Drupal\media_mpx_test\Event;

use Drupal\media_mpx\Event\ImportSelectEvent;
use Lullabot\Mpx\DataService\ByFields;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Example subscriber showing how to add fields to limit imports.
 */
class ImportSelectSubscriber implements EventSubscriberInterface {

  /**
   * Exclude mpx objects not flagged for import based on a custom field.
   *
   * @param \Drupal\media_mpx\Event\ImportSelectEvent $event
   *   The import event.
   */
  public function excludeDrupal(ImportSelectEvent $event) {
    $fields = new ByFields();
    $event->getFields()->add($fields->addField('customValue', '{excludeDrupal}{false|-}'));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ImportSelectEvent::IMPORT_SELECT => 'excludeDrupal',
    ];
  }

}
