<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
use GuzzleHttp\Exception\TransferException;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;
use Psr\Http\Message\UriInterface;

/**
 * Media source for mpx Media items.
 *
 * @see \Lullabot\Mpx\DataService\Media\Media
 * @see https://docs.theplatform.com/help/media-media-object
 *
 * @MediaSource(
 *   id = "media_mpx_media",
 *   label = @Translation("mpx Media"),
 *   description = @Translation("mpx media data, such as videos."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "video.png",
 *   thumbnail_alt_metadata_attribute="thumbnail_alt",
 *   default_thumbnail_filename = "video.png",
 *   media_mpx = {
 *     "service_name" = "Media Data Service",
 *     "object_type" = "Media",
 *     "schema_version" = "1.10",
 *   },
 * )
 */
class Media extends MediaSourceBase implements MediaSourceInterface {

  /**
   * The path to the thumbnails directory.
   *
   * Normally this would be a class constant, but file_prepare_directory()
   * requires the string to be passed by reference.
   *
   * @var string
   */
  private $thumbnailsDirectory = 'public://media_mpx/thumbnails/';

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    list($propertyInfo, $properties) = $this->extractMediaProperties(MpxMedia::class);

    $metadata = [];
    foreach ($properties as $property) {
      $metadata[$property] = $propertyInfo->getShortDescription(MpxMedia::class, $property);
    }

    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    // Load the media type.
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    $source_field = $this->getSourceFieldDefinition($media_type);
    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    $mpx_media = $this->getMpxObject($media);
    if (!$media->get($source_field->getName())->isEmpty()) {

      switch ($attribute_name) {
        case 'thumbnail_uri':
          return $this->downloadThumbnail($media, $attribute_name, $mpx_media->getDefaultThumbnailUrl());

        case 'thumbnail_alt':
          return $mpx_media->getTitle();
      }

      list(, $properties) = $this->extractMediaProperties(MpxMedia::class);

      if (in_array($attribute_name, $properties)) {
        return $this->getReflectedProperty($media, $attribute_name, $mpx_media);
      }
    };

    return parent::getMetadata($media, $attribute_name);
  }

  /**
   * Download a thumbnail to the local file system.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being accessed.
   * @param string $attribute_name
   *   The metadata attribute being accessed.
   * @param \Psr\Http\Message\UriInterface $uri
   *   The URI of the thumbnail to download.
   *
   * @return string
   *   The existing thumbnail, or the newly downloaded thumbnail.
   */
  private function downloadThumbnail(MediaInterface $media, string $attribute_name, UriInterface $uri) {
    try {
      $local_uri = $this->thumbnailsDirectory . $uri->getHost() . $uri->getPath();
      if (!file_exists($local_uri)) {
        $directory = dirname($local_uri);
        file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
        $thumbnail = $this->httpClient->request('GET', $uri);
        file_unmanaged_save_data((string) $thumbnail->getBody(), $local_uri);
      }

      return $local_uri;
    }
    catch (TransferException $e) {
      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
      $mpx_media = $this->getMpxObject($media);
      // @todo Can this somehow deeplink to the mpx console?
      $link = Link::fromTextAndUrl($this->t('link to mpx object'), Url::fromUri($mpx_media->getId()))->toString();
      $this->logger->error('An error occurred while downloading the thumbnail for @title: HTTP @code @message', [
        '@title' => $media->label(),
        '@code' => $e->getCode(),
        '@message' => $e->getMessage(),
        'link' => $link,
      ]);
      return parent::getMetadata($media, $attribute_name);
    }
  }

}
