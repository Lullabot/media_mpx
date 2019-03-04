<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use Lullabot\Mpx\DataService\CustomFieldManager;
use Psr\Log\LoggerInterface;

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
   * The discovered class that is used for mpx Media objects.
   *
   * @var string
   */
  private $mediaClass;

  /**
   * The discovered class that is used for mpx MediaFile objects.
   *
   * @var string
   */
  private $mediaFileClass;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, DataObjectFactoryCreator $dataObjectFactory, ClientInterface $httpClient, LoggerInterface $logger, CustomFieldManager $customFieldManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory, $dataObjectFactory, $httpClient, $logger, $customFieldManager);

    $service_info = $this->getPluginDefinition()['media_mpx'];
    $dataServiceManager = $dataObjectFactory->getDataServiceManager();
    $this->mediaClass = $dataServiceManager->getDataService($service_info['service_name'], $service_info['object_type'], $service_info['schema_version'])->getClass();
    $this->mediaFileClass = $dataServiceManager->getDataService($service_info['service_name'], 'MediaFile', $service_info['schema_version'])->getClass();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'media_image_bundle' => NULL,
      'media_image_field' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return $this->mpxMetadataProperties('Media', $this->mediaClass) + $this->mpxMetadataProperties('MediaFile', $this->mediaFileClass) + $this->customMetadataProperties();
  }

  /**
   * Returns metadata mappings for thePlatform-defined fields.
   *
   * @param string $mpx_object_name
   *   The name of the object type from mpx, such as 'MediaFile'.
   * @param string $class
   *   The class name to extract properties from. This typically contains the
   *   mpx object name but is not required.
   *
   * @return array
   *   The array of metadata labels, keyed by their property.
   */
  private function mpxMetadataProperties(string $mpx_object_name, string $class): array {
    $metadata = [];
    $extractor = $this->propertyExtractor();
    foreach ($extractor->getProperties($class) as $property) {
      $label = $property;
      if ($shortDescription = $extractor->getShortDescription($class, $property)) {
        $label = sprintf('%s %s: %s', $mpx_object_name, $label, $shortDescription);
      }
      $metadata[$mpx_object_name . ':' . $property] = $label;
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

    try {
      return $this->getMpxMetadata($media, $attribute_name);
    }
    catch (ClientException $e) {
      // Unfortunately, the media API has no way for us to set a form validation
      // error when fetching metadata during a save operation. Instead, it
      // expects a NULL return for a given attribute. There are a variety of
      // user-caused conditions that can cause mpx videos to fail to load (such
      // as a typo'ed mpx URL), and using the Messenger service gives us a
      // method to tell the user something went wrong, even if their entity does
      // get saved.
      $this->mpxLogger->logException($e);
      if ($e->getCode() == 404) {
        $this->messenger()->addError($this->t('The video was not found in mpx. Check the mpx URL and try again.'));
      }
      elseif ($e->getCode() == 401 || $e->getCode() == 403) {
        $this->messenger()->addError($this->t('Access was denied loading the video from mpx. Check the mpx URL and account credentials and try again.'));
      }
      else {
        $this->messenger()->addError($this->t('There was an error loading the video from mpx. The error from mpx was: @message', ['@code' => $e->getCode(), '@message' => $e->getMessage()]));
      }
    }
    catch (TransferException $e) {
      $this->mpxLogger->logException($e);
      $this->messenger()->addError($this->t('There was an error loading the video from mpx. The error from mpx was: @message', ['@code' => $e->getCode(), '@message' => $e->getMessage()]));
    }
    return parent::getMetadata($media, $attribute_name);
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
    if (is_null($value)) {
      $value = $this->getMediaValue($media, $attribute_name);
    }

    // Check if the attribute is on the first video file.
    if (is_null($value)) {
      $value = $this->getMediaFileValue($media, $attribute_name);
    }

    // Finally, check if a custom field own this attribute.
    if (is_null($value)) {
      $value = $this->getCustomFieldsValue($media, $attribute_name);
    }

    if (is_null($value)) {
      return parent::getMetadata($media, $attribute_name);
    }

    return $value;
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
  private function getCustomFieldsValue(MediaInterface $media, string $attribute_name) {
    // Now check for custom fields.
    $service_info = $this->getPluginDefinition()['media_mpx'];
    $fields = $this->customFieldManager->getCustomFields();
    $properties = [];

    $service_name = $service_info['service_name'];
    $object_type = $service_info['object_type'];
    if (!isset($fields[$service_name]) || !isset($fields[$service_name][$object_type])) {
      // No custom fields for this object exist, so we can exit early.
      return NULL;
    }

    // First, we extract all possible custom fields that may be defined.
    foreach ($fields[$service_name][$object_type] as $discoveredCustomField) {
      /* @var \Lullabot\Mpx\DataService\DiscoveredCustomField $discoveredCustomField */
      $class = $discoveredCustomField->getClass();
      $namespace = $discoveredCustomField->getAnnotation()->namespace;
      $properties[$namespace] = $this->propertyExtractor()
        ->getProperties($class);
    }

    list($attribute_namespace, $field) = $this->extractNamespaceField($attribute_name);

    if (in_array($attribute_namespace, array_keys($properties))) {
      $mpx_media = $this->getMpxObject($media);
      $method = 'get' . ucfirst($field);
      return $mpx_media->getCustomFields()[$attribute_namespace]->$method();
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
   * @param string $class
   *   The class to inspect.
   *
   * @return bool
   *   True if the property is a valid field, FALSE otherwise.
   */
  private function hasReflectedProperty($attribute_name, $class) {
    return in_array($attribute_name, $this->propertyExtractor()
      ->getProperties($class));
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
        /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_object */
        $mpx_object = $this->getMpxObject($media);

        try {
          return $this->downloadThumbnail($mpx_object);
        }
        catch (TransferException $e) {
          // @todo Can this somehow deeplink to the mpx console?
          $link = Link::fromTextAndUrl($this->t('link to mpx object'), Url::fromUri($mpx_object->getId()))
            ->toString();
          $this->logger->error('An error occurred while downloading the thumbnail for @title: HTTP @code @message', [
            '@title' => $mpx_object->getTitle(),
            '@code' => $e->getCode(),
            '@message' => $e->getMessage(),
            'link' => $link,
          ]);
        }
        break;

      case 'thumbnail_alt':
        $value = $this->thumbnailAlt($media);
        break;
    }
    return $value;
  }

  /**
   * Call a get method on the first media file video and return it's value.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being accessed.
   * @param string $attribute_name
   *   The metadata attribute being accessed.
   *
   * @return mixed|null
   *   Metadata attribute value or NULL if unavailable.
   */
  protected function getMediaFileReflectedProperty(MediaInterface $media, string $attribute_name) {
    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_object */
    $mpx_object = $this->getMpxObject($media);
    foreach ($mpx_object->getContent() as $media_file) {
      if ($media_file->getContentType() == 'video') {
        return $this->getReflectedProperty($media, $attribute_name, $media_file);
      }
    }

    return NULL;
  }

  /**
   * Return a metadata value for Media File.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   * @param string $attribute_name
   *   The attribute of the field.
   *
   * @return mixed|null
   *   The metadata value or NULL if one is not found.
   */
  private function getMediaFileValue(MediaInterface $media, $attribute_name) {
    $value = NULL;
    if (strpos($attribute_name, 'MediaFile:') === 0) {
      $property = substr($attribute_name, strlen('MediaFile:'));
      if ($this->hasReflectedProperty($property, $this->mediaFileClass)) {
        $value = $this->getMediaFileReflectedProperty($media, $property);
      }
    }
    return $value;
  }

  /**
   * Return a metadata value for a Media object.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   * @param string $attribute_name
   *   The attribute of the field.
   *
   * @return mixed|null
   *   The metadata value or NULL if one is not found.
   */
  private function getMediaValue(MediaInterface $media, $attribute_name) {
    $value = NULL;
    if (strpos($attribute_name, 'Media:') === 0) {
      $property = substr($attribute_name, strlen('Media:'));
      if ($this->hasReflectedProperty($property, $this->mediaClass)) {
        $mpx_object = $this->getMpxObject($media);
        $value = $this->getReflectedProperty($media, $property, $mpx_object);
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['media_image_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Media image bundle'),
      '#default_value' => $this->getConfiguration()['media_image_bundle'],
      '#options' => $this->getImageMediaBundles(),
      '#empty_option' => $this->t('- Select - '),
      '#description' => $this->t('To have the thumbnail mapped to a media entity rather than an unmanaged file (the default) choose a media type and field to map the thumbnail to.'),
      '#ajax' => [
        'callback' => [$this, 'mediaImageBundleOnChange'],
        'event' => 'change',
        'wrapper' => 'media-image-field',
      ]
    ];

    if (!empty($this->getConfiguration()['media_image_bundle'])) {
      $form['media_image_field'] = $this->getMediaImageFieldElement($this->getConfiguration()['media_image_bundle']);
    }
    else {
      $form['placeholder_media_image_field'] = [
        '#markup' => '<div id="media-image-field"></div>',
      ];
    }

    return $form;
  }

  /**
   * AJAX callback for when the media bundle changes.
   *
   * Used to update the field selection.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The render array to render in place of the media image fields spot.
   */
  public function mediaImageBundleOnChange(array &$form, FormStateInterface $form_state) {
    if ($media_image_bundle = $form_state->getValue(['source_configuration', 'media_image_bundle'])) {
      $element = $this->getMediaImageFieldElement($media_image_bundle);
    }
    else {
      $element = [
        '#markup' => '<div id="media-image-field"></div>',
      ];
    }
    return $element;
  }

  /**
   * Get a list of media bundles that could be used to save an image.
   *
   * @return array
   *   Array of media bundle labels keyed by bundle id, suitable for use in a
   *   #type => select return array.
   */
  protected function getImageMediaBundles() {
    // Get all media bundles.
    $bundles = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    $bundles = array_filter($bundles, function (MediaType $bundle) {
      // Filter out any bundles that are use the same source plugin. That would be
      // silly.
      if ($bundle->getSource()->getPluginId() === $this->getPluginId()) {
        return FALSE;
      }
      // Filter out any bundles that don't have a suitable image field. Also
      // silly.
      return !empty($this->getImageFieldsForMediaBundle($bundle->id()));
    });

    return array_map(function (MediaType $bundle) {
      return $bundle->label();
    }, $bundles);
  }

  /**
   * Build a select element from image fields on the given media bundle.
   *
   * @param string $media_bundle_id
   *   Media bundle id for the media bundle we want a list of image field
   *   options for.
   *
   * @return array
   *   Form API select element to choose an image field of the given media
   *   bundle.
   */
  protected function getMediaImageFieldElement($media_bundle_id) {
    return [
      '#type' => 'select',
      '#title' => $this->t('Media image field'),
      '#default_value' => $this->getConfiguration()['media_image_field'],
      '#options' => $this->getImageFieldsForMediaBundle($media_bundle_id),
      '#description' => $this->t('Select an image field from the selected media bundle.'),
      '#prefix' => '<div id="media-image-field">',
      '#suffix' => '</div>',
      '#required' => TRUE,
    ];
  }

  /**
   * Get a list of image fields on the given bundle.
   *
   * @param string $media_bundle_id
   *   Image bundle id.
   *
   * @return array
   *   Array of image fields keyed by field name.
   */
  protected function getImageFieldsForMediaBundle($media_bundle_id) {
    $image_field_options = [];
    foreach ($this->entityFieldManager->getFieldDefinitions('media', $media_bundle_id) as $field_name => $field) {
      if (!($field instanceof BaseFieldDefinition) && $field->getType() == 'image') {
        $image_field_options[$field_name] = sprintf('%s (%s)', $field->getLabel(), $field_name);
      }
    }
    return $image_field_options;
  }

}
