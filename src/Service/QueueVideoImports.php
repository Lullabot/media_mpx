<?php

namespace Drupal\media_mpx\Service;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\media\MediaTypeInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Event\ImportSelectEvent;
use Drupal\media_mpx\MpxImportTask;
use Drupal\media_mpx\Repository\MpxMediaType;
use Lullabot\Mpx\DataService\Fields;
use Lullabot\Mpx\DataService\Media\Media;
use Lullabot\Mpx\DataService\ObjectListIterator;
use Lullabot\Mpx\DataService\ObjectListQuery;
use Lullabot\Mpx\DataService\Range;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Queues MpxImportTask items to be processed by background processes.
 *
 * @package Drupal\media_mpx\Service
 */
class QueueVideoImports {

  const MEDIA_MPX_IMPORT_QUEUE = 'media_mpx_importer';

  /**
   * The Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * The Event Dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The Data Object Factory Creator.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  private $dataObjectFactoryCreator;

  /**
   * The mpx Media Types repository.
   *
   * @var \Drupal\media_mpx\Repository\MpxMediaType
   */
  private $mpxMediaTypeRepository;

  /**
   * The queue to use for mpx video imports.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  private $queue;

  /**
   * QueueContentsForm constructor.
   */
  public function __construct(MpxMediaType $mpxMediaTypeRepository, LoggerChannelInterface $loggerChannel, EventDispatcherInterface $eventDispatcher, DataObjectFactoryCreator $dataObjectFactoryCreator, QueueFactory $queueFactory) {
    $this->logger = $loggerChannel;
    $this->eventDispatcher = $eventDispatcher;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->queue = $queueFactory->get(self::MEDIA_MPX_IMPORT_QUEUE);
    $this->mpxMediaTypeRepository = $mpxMediaTypeRepository;
  }

  /**
   * Executes the queue service with the given parameters.
   *
   * @param \Drupal\media_mpx\Service\QueueVideoImportsRequest $request
   *   The request object containing the media type and query filters.
   *
   * @return \Drupal\media_mpx\Service\QueueVideoImportsResponse
   *   A response object with data about the queued videos.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\media_mpx\Exception\MediaTypeDoesNotExistException
   * @throws \Drupal\media_mpx\Exception\MediaTypeNotAssociatedWithMpxException
   */
  public function execute(QueueVideoImportsRequest $request): QueueVideoImportsResponse {
    $media_type_id = $request->getMediaTypeId();
    $limit = $request->getLimit();

    $media_type = $this->mpxMediaTypeRepository->findByTypeId($media_type_id);

    $results = $this->fetchMediaTypeItemIdsFromMpx($media_type, $request);
    $queue_results = [];

    foreach ($results as $index => $mpx_media) {
      if (!is_null($limit) && count($queue_results) >= $limit) {
        break;
      }
      $queue_results[] = $this->queueMpxItem($mpx_media, $media_type->id());
    }

    return new QueueVideoImportsResponse($queue_results, $results);
  }

  /**
   * Fetches the item ids for a given media type.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The Media Type object.
   * @param \Drupal\media_mpx\Service\QueueVideoImportsRequest $request
   *   The QueueVideoImportsRequest object.
   *
   * @return \Lullabot\Mpx\DataService\ObjectListIterator
   *   The ObjectListIterator returned by the mpx library.
   */
  private function fetchMediaTypeItemIdsFromMpx(MediaTypeInterface $media_type, QueueVideoImportsRequest $request): ObjectListIterator {
    // Only return the media ids to add to the queue.
    $field_query = new Fields();
    $field_query->addField('id');
    $query = new ObjectListQuery();
    $query->add($field_query);

    if ($request->getLimit()) {
      $range = new Range();
      $range->setStartIndex($request->getOffset());
      $range->setEndIndex($request->getOffset() + $request->getLimit() - 1);
      $query->setRange($range);
    }

    $media_source = DataObjectImporter::loadMediaSource($media_type);
    $factory = $this->dataObjectFactoryCreator->fromMediaSource($media_source);

    $event = new ImportSelectEvent($query, $media_source);
    $this->eventDispatcher->dispatch(ImportSelectEvent::IMPORT_SELECT, $event);
    $results = $factory->select($query);
    return $results;
  }

  /**
   * Queues an mpx Media item.
   *
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx Media item.
   * @param string $media_type_id
   *   The media item type id.
   *
   * @return \Drupal\media_mpx\Service\QueueMpxImportResult
   *   A QueueMpxImportResult object.
   */
  protected function queueMpxItem(Media $mpx_media, string $media_type_id): QueueMpxImportResult {
    $import_task = new MpxImportTask($mpx_media->getId(), $media_type_id);
    if (!$this->queue->createItem($import_task)) {
      $this->logger->error(t('@type @uri could not be queued for updates.',
          ['@type' => $media_type_id, '@uri' => $mpx_media->getId()])
      );
      return new QueueMpxImportResult($mpx_media, FALSE);
    }

    $this->logger->info(t('@type @uri has been queued to be imported.',
        ['@type' => $media_type_id, '@uri' => $mpx_media->getId()])
    );
    return new QueueMpxImportResult($mpx_media, TRUE);
  }

}
