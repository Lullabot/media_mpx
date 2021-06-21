<?php

declare(strict_types = 1);

namespace Drupal\media_mpx\Service\UpdateVideoItem;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\DataObjectImporter;
use Drupal\media_mpx\Repository\MpxMediaType;

/**
 * Updates a media entity with the associated data from mpx.
 *
 * @package Drupal\media_mpx\Service\ImportVideoItem
 */
class UpdateVideoItem {
  use DependencySerializationTrait;

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
    $results = $factory->loadByNumericId($mpx_id, FALSE, ['headers' => ['Cache-Control' => 'no-cache']]);
    $mpx_media = $results->wait();
    $saved = $this->importer->importItem($mpx_media, $media_type);

    $response = new UpdateVideoItemResponse($mpx_media, $saved);
    return $response;
  }

}
