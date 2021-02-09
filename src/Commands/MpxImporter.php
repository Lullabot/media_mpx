<?php

// phpcs:disable Drupal.Commenting.FunctionComment.ParamMissingDefinition

namespace Drupal\media_mpx\Commands;

use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Repository\MpxMediaType;
use Drupal\media_mpx\Service\QueueVideoImports;
use Drupal\media_mpx\Service\QueueVideoImportsRequest;
use Drush\Commands\DrushCommands;
use function GuzzleHttp\Promise\each_limit;
use Lullabot\Mpx\DataService\ObjectList;
use Lullabot\Mpx\DataService\ObjectListQuery;

/**
 * Drush commands for mpx.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MpxImporter extends DrushCommands {

  /**
   * The name of the queue for notifications.
   */
  const MEDIA_MPX_IMPORT_QUEUE = 'media_mpx_importer';

  /**
   * The factory used to load media data from mpx.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  private $dataObjectFactoryCreator;

  /**
   * The class to import mpx objects.
   *
   * @var \Drupal\media_mpx\DataObjectImporter
   */
  private $dataObjectImporter;

  /**
   * The mpx media type repository.
   *
   * @var \Drupal\media_mpx\Repository\MpxMediaType
   */
  private $mpxTypeRepository;

  /**
   * The Queue video imports service.
   *
   * @var \Drupal\media_mpx\Service\QueueVideoImports
   */
  private $queueVideoService;

  /**
   * MpxImporter constructor.
   *
   * @param \Drupal\media_mpx\Repository\MpxMediaType $mpxTypeRepository
   *   The mpx media type repository.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactoryCreator
   *   The creator used to configure a factory for loading mpx objects.
   * @param \Drupal\media_mpx\DataObjectImporter $dataObjectImporter
   *   The class used to import mpx objects.
   * @param \Drupal\media_mpx\Service\QueueVideoImports $queueVideoService
   *   The Queue Video imports service.
   */
  public function __construct(MpxMediaType $mpxTypeRepository, DataObjectFactoryCreator $dataObjectFactoryCreator, DataObjectImporter $dataObjectImporter, QueueVideoImports $queueVideoService) {
    $this->mpxTypeRepository = $mpxTypeRepository;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->dataObjectImporter = $dataObjectImporter;
    $this->queueVideoService = $queueVideoService;
  }

  /**
   * Import mpx objects for a media type.
   *
   * @param string $media_type_id
   *   The media type ID to import for.
   *
   * @option batch_size An integer with the number of items to import per batch.
   *
   * @usage media_mpx:import {bundle}
   *   Import all mpx data for the mpx_video media type.
   *
   * @command media_mpx:import
   * @aliases mpxi
   */
  public function import(string $media_type_id, $options = ['batch_size' => 100]) {
    $batch_size = $options['batch_size'];
    $last_index = 0;

    $this->io()->title(dt('Importing @type media', ['@type' => $media_type_id]));

    // First request is done out of the loop, to fetch total results and set up
    // the progress bar.
    $request = new QueueVideoImportsRequest($media_type_id, $batch_size, $last_index + 1);
    $response = $this->queueVideoService->execute($request);
    $total_count = $response->getIterator()->getTotalResults();
    $this->io()->progressStart($total_count);

    while ($last_index <= $total_count) {
      $last_index += $batch_size;
      $request = new QueueVideoImportsRequest($media_type_id, $batch_size, $last_index + 1);
      $response = $this->queueVideoService->execute($request);
      foreach ($response->getQueuedVideos() as $queued) {
        $this->io()->progressAdvance();
        $this->logger()->info(dt('@type @uri has been queued to be imported.',
            ['@type' => $media_type_id, '@uri' => $queued->getId()])
        );
      }
    }
    $this->io()->progressFinish();
  }

  /**
   * Warm the mpx response cache.
   *
   * @param string $media_type_id
   *   The media type ID to warm.
   *
   * @command media_mpx:warm
   * @aliases mpxw
   */
  public function warmCache(string $media_type_id) {
    $media_type = $this->mpxTypeRepository->findByTypeId($media_type_id);

    $media_source = DataObjectImporter::loadMediaSource($media_type);

    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);
    $query = new ObjectListQuery();
    $request = $factory->selectRequest($query);
    $list = $request->wait();

    // @todo Support fetching the total results via ObjectList.
    $this->io()->title(dt('Warming cache for @type media', ['@type' => $media_type_id]));
    $this->io()->progressStart();

    $service_info = $media_source->getPluginDefinition()['media_mpx'];

    each_limit($list->yieldLists(), 4, function (ObjectList $list) use ($media_type, $service_info) {
      foreach ($list as $index => $item) {
        // For each item, we need to inject it into the response cache as if
        // a single request for the item was made.
        $this->dataObjectImporter->cacheItem($item, $service_info);
        $this->io()->progressAdvance();
        $this->logger()->info(dt('Fetched @type @uri.', [
          '@type' => $media_type->id(),
          '@uri' => $item->getId(),
        ]));
      }

    })->wait();
  }

}
