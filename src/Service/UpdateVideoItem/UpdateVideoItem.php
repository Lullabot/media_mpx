<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service\UpdateVideoItem;

use Drupal\media\MediaInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Repository\MpxMediaType;

/**
 * Updates a media entity with the associated data from mpx.
 *
 * @package Drupal\media_mpx\Service\ImportVideoItem
 */
class UpdateVideoItem {

  /**
   * The Data Object Importer.
   *
   * @var \Drupal\media_mpx\DataObjectImporter
   */
  private $importer;

  /**
   * The Data Object Factory Creator.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  private $objectFactoryCreator;

  /**
   * The mpx Media Type Repository.
   *
   * @var \Drupal\media_mpx\Repository\MpxMediaType
   */
  private $mpxMediaTypeRepository;

  /**
   * UpdateVideoItem constructor.
   */
  public function __construct(MpxMediaType $mpxMediaTypeRepository, DataObjectImporter $importer, DataObjectFactoryCreator $creator) {
    $this->mpxMediaTypeRepository = $mpxMediaTypeRepository;
    $this->importer = $importer;
    $this->objectFactoryCreator = $creator;
  }

  /**
   * Performs the update (import) of a video item into Drupal.
   *
   * @param \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemRequest $request
   *   The request object.
   *
   * @return \Drupal\media_mpx\Service\UpdateVideoItem\UpdateVideoItemResponse
   *   A response object holding data about the items that were updated.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\media_mpx\Exception\MediaTypeDoesNotExistException
   * @throws \Drupal\media_mpx\Exception\MediaTypeNotAssociatedWithMpxException
   */
  public function execute(UpdateVideoItemRequest $request): UpdateVideoItemResponse {
    $mpx_id = $request->getMpxId();
    $media_type_id = $request->getMediaTypeId();

    $media_type = $this->mpxMediaTypeRepository->findByTypeId($media_type_id);

    $media_source = $this->importer->loadMediaSource($media_type);
    $factory = $this->objectFactoryCreator->fromMediaSource($media_source);
    $results = $factory->load($mpx_id);
    $mpx_media = $results->wait();
    $saved = $this->importer->importItem($mpx_media, $media_type);

    $ids = array_map(function (MediaInterface $media) {
      return $media->id();
    }, $saved);

    $response = new UpdateVideoItemResponse($ids);
    return $response;
  }

}
