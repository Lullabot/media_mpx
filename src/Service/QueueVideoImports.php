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
use Lullabot\Mpx\DataService\ObjectListIterator;
use Lullabot\Mpx\DataService\ObjectListQuery;
use Lullabot\Mpx\DataService\Range;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class QueueVideoImports.
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
   * The factory to load the mpx notification queue.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  private $queueFactory;

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
   * QueueContentsForm constructor.
   */
  public function __construct(MpxMediaType $mpxMediaTypeRepository, LoggerChannelInterface $loggerChannel, EventDispatcherInterface $eventDispatcher, DataObjectFactoryCreator $dataObjectFactoryCreator, QueueFactory $queueFactory) {
    $this->logger = $loggerChannel;
    $this->eventDispatcher = $eventDispatcher;
    $this->dataObjectFactoryCreator = $dataObjectFactoryCreator;
    $this->queueFactory = $queueFactory;
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
    $queue = $this->queueFactory->get(self::MEDIA_MPX_IMPORT_QUEUE);
    $media_type_id = $request->getMediaTypeId();
    $limit = $request->getLimit();

    $media_type = $this->mpxMediaTypeRepository->findByTypeId($media_type_id);

    $results = $this->fetchMediaTypeItemIdsFromMpx($media_type, $request);
    $queued = 0;
    $errored = 0;
    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    foreach ($results as $index => $mpx_media) {
      if (!is_null($limit) && $queued >= $limit) {
        break;
      }

      $import_task = new MpxImportTask($mpx_media->getId(), $media_type->id());
      if (!$queue->createItem($import_task)) {
        $errored++;
        $this->logger->error(t('@type @uri could not be queued for updates.',
            ['@type' => $media_type->id(), '@uri' => $mpx_media->getId()])
        );
        continue;
      }

      $this->logger->info(t('@type @uri has been queued to be imported.',
          ['@type' => $media_type->id(), '@uri' => $mpx_media->getId()])
      );
      $queued++;
    }

    return new QueueVideoImportsResponse($queued, $errored, $results);
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

}
