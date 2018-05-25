<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
use GuzzleHttp\Exception\TransferException;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;

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
    return $this->mpxMetadataProperties() + $this->customMetadataProperties();
  }

  /**
   * Returns metadata mappings for thePlatform-defined fields.
   *
   * @return array
   *   The array of metadata labels, keyed by their property.
   */
  private function mpxMetadataProperties(): array {
    $metadata = [];
    $extractor = $this->propertyExtractor();
    foreach ($extractor->getProperties(MpxMedia::class) as $property) {
      $label = $property;
      if ($shortDescription = $extractor->getShortDescription(MpxMedia::class, $property)) {
        $label = $shortDescription . ' (' . $label . ')';
      }
      $metadata[$property] = $label;
    }
    return $metadata;
  }

  /**
   * Returns an array of custom field metadata.
   *
   * @return array
   *   An array of labels, keyed by an encoded namespace / property key.
   */
  private function customMetadataProperties() {
    $metadata = [];

    $service_info = $this->getPluginDefinition()['media_mpx'];
    $fields = $this->customFieldManager->getCustomFields();
    /* @var \Lullabot\Mpx\DataService\DiscoveredCustomField $discoveredCustomField */
    $service_name = $service_info['service_name'];
    $object_type = $service_info['object_type'];
    if (isset($fields[$service_name]) && isset($fields[$service_name][$object_type]) && $var = $fields[$service_name][$object_type]) {
      foreach ($var as $namespace => $discoveredCustomField) {
        $class = $discoveredCustomField->getClass();
        $namespace = $discoveredCustomField->getAnnotation()->namespace;
        $this->addProperties($metadata, $namespace, $this->propertyExtractor()
          ->getProperties($class), $class);
      }
    }

    return $metadata;
  }

  /**
   * Add custom mpx field properties to the metadata array.
   *
   * @param array &$metadata
   *   The array of metadata to add to.
   * @param string $namespace
   *   The mpx namespace the property belongs to.
   * @param array $properties
   *   An array of property names.
   * @param string $class
   *   The custom field class implementation name.
   */
  private function addProperties(array &$metadata, string $namespace, array $properties, string $class) {
    foreach ($properties as $property) {
      $label = $this->propertyLabel($namespace, $property, $class);

      // The config system does not allow periods in values, so we encode
      // those using URL rules.
      $key = str_replace('.', '%2E', $namespace) . '/' . $property;
      $metadata[$key] = $label;
    }
  }

  /**
   * Return a human-readable label for a property.
   *
   * Different namespaces may define the same property, making it difficult for
   * site builders to map custom fields. This builds a label that includes the
   * short description, if available, along with the namespaced-property itself.
   *
   * @param string $namespace
   *   The mpx namespace the property belongs to.
   * @param string $property
   *   An property name.
   * @param string $class
   *   The custom field class implementation name.
   *
   * @return string
   *   The property label.
   */
  private function propertyLabel($namespace, $property, $class): string {
    $label = sprintf('%s:%s', $namespace, $property);
    if ($shortDescription = $this->propertyExtractor()
      ->getShortDescription($class, $property)) {
      $label = $shortDescription . ' (' . $label . ')';
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    // Load the media type.
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $source_field = $this->sourceField($media);

    // No mpx URL is set. While the UI field may be required, it's possible to
    // have this blank from custom code.
    if ($media->get($source_field->getName())->isEmpty()) {
      return parent::getMetadata($media, $attribute_name);
    }

    return $this->getMpxMetadata($media, $attribute_name);
  }

  /**
   * Return the source field definition for a media item.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item to return the source field definition for.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The source field definition, if one exists.
   */
  private function sourceField(MediaInterface $media) {
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')
      ->load($media->bundle());
    return $this->getSourceFieldDefinition($media_type);
  }

  /**
   * Return the mpx metadata for a given attribute on a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to return metadata for.
   * @param string $attribute_name
   *   The mpx field or property name.
   *
   * @return mixed|null|string
   *   The metadata value.
   */
  private function getMpxMetadata(MediaInterface $media, $attribute_name) {
    $value = NULL;

    // First, check for the special thumbnail attributes that are defined by
    // the media module.
    $value = $this->getThumbnailMetadata($media, $attribute_name);

    // Check if the attribute is a core thePlatform-defined field.
    if (!$value && $this->hasReflectedProperty($attribute_name)) {
      $mpx_media = $this->getMpxObject($media);
      $value = $this->getReflectedProperty($media, $attribute_name, $mpx_media);
    }

    // Finally, check if a custom field own this attribute.
    if (!$value) {
      $value = $this->getCustomFieldsMetadata($media, $attribute_name);
    }

    if ($value) {
      return $value;
    }

    return parent::getMetadata($media, $attribute_name);
  }

  /**
   * Download a thumbnail to the local file system.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being accessed.
   * @param string $attribute_name
   *   The metadata attribute being accessed.
   *
   * @return string
   *   The existing thumbnail, or the newly downloaded thumbnail.
   */
  private function downloadThumbnail(MediaInterface $media, string $attribute_name) {
    try {
      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
      $mpx_media = $this->getMpxObject($media);

      $thumbnailUrl = $mpx_media->getDefaultThumbnailUrl();
      $local_uri = $this->thumbnailsDirectory . $thumbnailUrl->getHost() . $thumbnailUrl->getPath();
      if (!file_exists($local_uri)) {
        $directory = dirname($local_uri);
        file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
        $thumbnail = $this->httpClient->request('GET', $thumbnailUrl);
        file_unmanaged_save_data((string) $thumbnail->getBody(), $local_uri);
      }

      return $local_uri;
    }
    catch (TransferException $e) {
      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
      $mpx_media = $this->getMpxObject($media);
      // @todo Can this somehow deeplink to the mpx console?
      $link = Link::fromTextAndUrl($this->t('link to mpx object'), Url::fromUri($mpx_media->getId()))
        ->toString();
      $this->logger->error('An error occurred while downloading the thumbnail for @title: HTTP @code @message', [
        '@title' => $media->label(),
        '@code' => $e->getCode(),
        '@message' => $e->getMessage(),
        'link' => $link,
      ]);
      return parent::getMetadata($media, $attribute_name);
    }
  }

  /**
   * Return the alt tag for a thumbnail.
   *
   * While mpx has support for thumbnail descriptions, in practice they do not
   * look to be filled with useful text. Instead, we default to using the media
   * label, and if that is not available we fall back to the media title.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   *
   * @return string
   *   The thumbnail alt text.
   */
  private function thumbnailAlt(MediaInterface $media) {
    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    $mpx_media = $this->getMpxObject($media);
    if (!empty($media->label())) {
      return $media->label();
    }
    return $mpx_media->getTitle();
  }

  /**
   * Return a metadata value for a custom field.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   * @param string $attribute_name
   *   The attribute of the field.
   *
   * @return mixed|null
   *   The metadata value or NULL if one is not found.
   */
  private function getCustomFieldsMetadata(MediaInterface $media, string $attribute_name) {
    // Now check for custom fields.
    $service_info = $this->getPluginDefinition()['media_mpx'];
    $fields = $this->customFieldManager->getCustomFields();
    $properties = [];

    // First, we extract all possible custom fields that may be defined.
    /* @var \Lullabot\Mpx\DataService\DiscoveredCustomField $discoveredCustomField */
    foreach ($fields[$service_info['service_name']][$service_info['object_type']] as $namespace => $discoveredCustomField) {
      $class = $discoveredCustomField->getClass();
      $namespace = $discoveredCustomField->getAnnotation()->namespace;
      $properties[$namespace] = $this->propertyExtractor()
        ->getProperties($class);
    }

    list($attribute_namespace, $field) = $this->extractNamespaceField($attribute_name);

    if (in_array($attribute_namespace, array_keys($properties))) {
      $mpx_media = $this->getMpxObject($media);
      $method = 'get' . ucfirst($field);
      return $mpx_media->getCustomFields($attribute_namespace)->$method();
    }

    return NULL;
  }

  /**
   * Decode and extract the namespace and field for a custom metadata value.
   *
   * @param string $attribute_name
   *   The attribute being decoded.
   *
   * @return array
   *   An array containing:
   *     - The decoded namespace.
   *     - The field name.
   */
  private function extractNamespaceField($attribute_name): array {
    $decoded = str_replace('%2E', '.', $attribute_name);
    $parts = explode('/', $decoded);
    $field = array_pop($parts);
    $namespace = implode('/', $parts);
    return [$namespace, $field];
  }

  /**
   * Return if the mpx class has a given property.
   *
   * @param string $attribute_name
   *   The property name.
   *
   * @return bool
   *   True if the property is a valid field, FALSE otherwise.
   */
  private function hasReflectedProperty($attribute_name) {
    return in_array($attribute_name, $this->propertyExtractor()
      ->getProperties(MpxMedia::class));
  }

  /**
   * Return thumbnail metadata if it is set.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   * @param string $attribute_name
   *   The requested attribute.
   *
   * @return string|null
   *   The attribute value.
   */
  private function getThumbnailMetadata(MediaInterface $media, string $attribute_name) {
    $value = NULL;
    switch ($attribute_name) {
      case 'thumbnail_uri':
        $value = $this->downloadThumbnail($media, $attribute_name);
        break;

      case 'thumbnail_alt':
        $value = $this->thumbnailAlt($media);
        break;
    }
    return $value;
  }

}
