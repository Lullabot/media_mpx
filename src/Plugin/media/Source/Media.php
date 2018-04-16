<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaSourceInterface;
use Drupal\media_mpx\DataObjectFactory;
use Drupal\media_mpx\Entity\Account;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

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
 *   default_thumbnail_filename = "video.png"
 * )
 */
class Media extends MediaSourceBase implements MediaSourceInterface {
  use MessengerTrait;

  /**
   * The service to load mpx data.
   *
   * @var \Drupal\media_mpx\DataObjectFactory
   */
  private $dataObjectFactory;

  /**
   * Media constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\media_mpx\DataObjectFactory $dataObjectFactory
   *   The service to load mpx data.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, DataObjectFactory $dataObjectFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->dataObjectFactory = $dataObjectFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('media_mpx.data_object_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'account' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return parent::calculateDependencies() + [
      'config' => [
        'media_mpx.media_mpx_account.' . $this->getConfiguration()['account'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $accounts = $this->entityTypeManager->getStorage('media_mpx_account')->loadMultiple();

    if (empty($accounts)) {
      // @todo the #ajax callback isn't showing this, core bug?
      $this->messenger()->addError($this->t('Create an account before configuring an mpx media type.'));
      return $form;
    }

    $options = [];
    foreach ($accounts as $account) {
      $options[$account->id()] = $this->t('@title', [
        '@title' => $account->label(),
      ]);
    }

    $form['account'] = [
      '#type' => 'select',
      '#title' => $this->t('mpx account'),
      '#description' => $this->t('Select the mpx account to associate with this media type.'),
      '#default_value' => $this->getConfiguration()['account'],
      '#options' => $options,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    list($propertyInfo, $properties) = $this->extractMediaProperties();

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
    list(, $properties) = $this->extractMediaProperties();

    if (in_array($attribute_name, $properties)) {
      $user = $this->getAccount()->getUserEntity();
      $mediaFactory = $this->dataObjectFactory->forObjectType($user, 'Media Data Service', 'Media', '1.10');

      $id = $media->get($this->configuration['source_field'])->getString();
      $mpx_media = $mediaFactory->load($id);
      $method = 'get' . ucfirst($attribute_name);
      // @todo At the least this should be a static cache tied to $media.
      $value = $mpx_media->wait()->$method();

      // @todo Is this the best way to handle complex values like dates and
      // sub-objects?
      if ($value instanceof \DateTime) {
        $value = $value->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
      }

      return $value;
    }

    return parent::getMetadata($media, $attribute_name);
  }

  /**
   * Return the mpx account used for this media type.
   *
   * @return \Drupal\media_mpx\Entity\Account
   *   The mpx account.
   */
  protected function getAccount(): Account {
    $id = $this->getConfiguration()['account'];
    /** @var \Drupal\media_mpx\Entity\Account $account */
    $account = $this->entityTypeManager->getStorage('media_mpx_account')->load($id);
    return $account;
  }

  /**
   * Extract the properties available to set on a media entity.
   *
   * @return array
   *   An array of property values and their descriptions.
   */
  private function extractMediaProperties(): array {
    // @todo Cache this!
    $phpDocExtractor = new PhpDocExtractor();
    $reflectionExtractor = new ReflectionExtractor();

    $listExtractors = [$reflectionExtractor];

    $typeExtractors = [$phpDocExtractor, $reflectionExtractor];

    $descriptionExtractors = [$phpDocExtractor];

    $accessExtractors = [$reflectionExtractor];

    $propertyInfo = new PropertyInfoExtractor(
      $listExtractors,
      $typeExtractors,
      $descriptionExtractors,
      $accessExtractors
    );

    // @todo This should probably be discovered and not hardcoded.
    $class = MpxMedia::class;
    return [$propertyInfo, $propertyInfo->getProperties($class)];
  }

}
