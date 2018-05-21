<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\media\MediaSourceBase as DrupalMediaSourceBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\media\MediaInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\Entity\Account;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\CachingPhpDocExtractor;
use Lullabot\Mpx\DataService\CustomFieldManager;
use Lullabot\Mpx\DataService\ObjectInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

/**
 * Base class for mpx media sources.
 */
abstract class MediaSourceBase extends DrupalMediaSourceBase implements MpxMediaSourceInterface {
  use MessengerTrait;

  /**
   * The service to load mpx data.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  protected $dataObjectFactoryCreator;

  /**
   * The factory used to store mpx objects.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The http client used to download thumbnails.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger used to log HTTP errors downloading thumbnails.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The manager used to load custom field implementations.
   *
   * @var \Lullabot\Mpx\DataService\CustomFieldManager
   */
  protected $customFieldManager;

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
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   The factory for the key value service to load full mpx objects from.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactory
   *   The service to load mpx data.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client used to download thumbnails.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger used to log errors while downloading thumbnails.
   * @param \Lullabot\Mpx\DataService\CustomFieldManager $customFieldManager
   *   The manager used to load custom field classes.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, KeyValueFactoryInterface $keyValueFactory, DataObjectFactoryCreator $dataObjectFactory, ClientInterface $httpClient, LoggerInterface $logger, CustomFieldManager $customFieldManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->dataObjectFactoryCreator = $dataObjectFactory;
    $this->keyValueFactory = $keyValueFactory;
    $this->httpClient = $httpClient;
    $this->logger = $logger;
    $this->customFieldManager = $customFieldManager;
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
      $container->get('keyvalue'),
      $container->get('media_mpx.data_object_factory_creator'),
      $container->get('http_client'),
      $container->get('logger.channel.media_mpx'),
      $container->get('media_mpx.custom_field_manager')
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
    $accounts = $this->entityTypeManager->getStorage('media_mpx_account')
      ->loadMultiple();

    if (empty($accounts)) {
      // @todo the #ajax callback isn't showing this, core bug?
      $this->messenger()
        ->addError($this->t('Create an account before configuring an mpx media type.'));
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
   * Return the mpx account used for this media type.
   *
   * @return \Drupal\media_mpx\Entity\Account
   *   The mpx account.
   */
  public function getAccount(): Account {
    $id = $this->getConfiguration()['account'];
    /** @var \Drupal\media_mpx\Entity\Account $account */
    $account = $this->entityTypeManager->getStorage('media_mpx_account')
      ->load($id);
    return $account;
  }

  /**
   * Get the complete mpx object associated with a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Lullabot\Mpx\DataService\ObjectInterface
   *   The mpx object.
   */
  public function getMpxObject(MediaInterface $media): ObjectInterface {
    $id = $media->get($this->configuration['source_field'])->getString();
    $store = $this->keyValueFactory->get($this->getPluginId());
    if (!$mpx_item = $store->get($id)) {
      $factory = $this->dataObjectFactoryCreator->fromMediaSource($this);

      $mpx_item = $factory->load(new Uri($id))->wait();
      $store->set($id, $mpx_item);
    }
    return $mpx_item;
  }

  /**
   * Return a property extractor.
   *
   * @return \Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface
   *   An array of property values and their descriptions.
   */
  protected function propertyExtractor(): PropertyInfoExtractorInterface {
    $phpDocExtractor = new CachingPhpDocExtractor();
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
    return $propertyInfo;
  }

  /**
   * Call a get method on the mpx media object and return it's value.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being accessed.
   * @param string $attribute_name
   *   The metadata attribute being accessed.
   * @param mixed $mpx_object
   *   The mpx object.
   *
   * @return mixed|null
   *   Metadata attribute value or NULL if unavailable.
   */
  protected function getReflectedProperty(MediaInterface $media, string $attribute_name, $mpx_object) {
    $method = 'get' . ucfirst($attribute_name);
    // @todo At the least this should be a static cache tied to $media.
    try {
      $value = $mpx_object->$method();
    }
    catch (\TypeError $e) {
      // @todo The optional value was not set.
      // Remove this when https://github.com/Lullabot/mpx-php/issues/95 is
      // fixed.
      return parent::getMetadata($media, $attribute_name);
    }

    // @todo Is this the best way to handle complex values like dates and
    // sub-objects?
    if ($value instanceof \DateTime) {
      $value = $value->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }

    return $value;
  }

}
