<?php

namespace Drupal\Tests\media_mpx\Kernel\Event;

use Drupal\media_mpx\Event\ImportSelectEvent;
use Drupal\media_mpx_test\Event\ImportSelectSubscriber;
use Lullabot\Mpx\DataService\ObjectListQuery;

/**
 * Tests altering filters when selecting objects for import from mpx.
 *
 * @group media_mpx
 */
class ImportSelectEventTest extends EventTestBase {

  /**
   * Tests adding a condition to a select query.
   */
  public function testImportSelectEvent() {
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addSubscriber(new ImportSelectSubscriber());
    $query = new ObjectListQuery();
    $event = new ImportSelectEvent($query, $this->mediaSource);
    $dispatcher->dispatch(ImportSelectEvent::IMPORT_SELECT, $event);
    $this->assertEquals('{excludeDrupal}{false|-}', $query->toQueryParts()['byCustomValue']);
  }

}
