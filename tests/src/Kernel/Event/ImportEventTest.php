<?php

namespace Drupal\Tests\media_mpx\Kernel\Event;

use Drupal\media_mpx_test\Event\ImportEventSubscriber;
use GuzzleHttp\Psr7\Uri;

/**
 * Tests the event subscriber for import events.
 *
 * @group media_mpx
 */
class ImportEventTest extends EventTestBase {

  /**
   * Tests triggering an import event.
   *
   * @covers \Drupal\media_mpx\DataObjectImporter::importItem
   */
  public function testImportEvent() {
    $this->container->get('event_dispatcher')->addSubscriber(new ImportEventSubscriber());

    $dof = $this->container->get('media_mpx.data_object_factory_creator')->fromMediaSource($this->mediaSource);
    $object = $dof->load(new Uri('http://data.media.theplatform.com/media/data/Media/2602559'))->wait();

    $importer = $this->container->get('media_mpx.data_object_importer');
    $importer->importItem($object, $this->mediaType);

    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('media')->loadMultiple());
  }

}
