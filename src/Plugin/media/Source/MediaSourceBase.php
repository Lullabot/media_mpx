<?php

namespace Drupal\media_mpx\Plugin\media\Source;

use Drupal\Core\File\FileSystemInterface;
use Drupal\media\MediaSourceBase as DrupalMediaSourceBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\media\MediaInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\Entity\Account;
use Drupal\media_mpx\MpxLogger;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\CachingPhpDocExtractor;
use Lullabot\Mpx\DataService\CustomFieldManager;
use Lullabot\Mpx\DataService\DateTime\DateTimeFormatInterface;
use Lullabot\Mpx\DataService\Media\Media;
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
   * The logger used for mpx-specific errors.
   *
   * @var \Drupal\media_mpx\MpxLogger
   */
  protected $mpxLogger;

  /**
   * The path to the thumbnails directory.
   *
   * Normally this would be a class constant, but file_prepare_directory()
   * requires the string to be passed by reference.
   *
   * @var string
   */
  protected $thumbnailsDirectory = 'public://media_mpx/thumbnails/';

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

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
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $dataObjectFactory
   *   The service to load mpx data.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client used to download thumbnails.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger used to log errors while downloading thumbnails.
   * @param \Lullabot\Mpx\DataService\CustomFieldManager $customFieldManager
   *   The manager used to load custom field classes.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   *
   * @todo Refactor this constructor to reduce the number of parameters.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, DataObjectFactoryCreator $dataObjectFactory, ClientInterface $httpClient, LoggerInterface $logger, CustomFieldManager $customFieldManager, FileSystemInterface $fileSystem) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->dataObjectFactoryCreator = $dataObjectFactory;
    $this->httpClient = $httpClient;
    $this->logger = $logger;
    $this->customFieldManager = $customFieldManager;

    $this->mpxLogger = new MpxLogger($logger);
    $this->fileSystem = $fileSystem;
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
      $container->get('media_mpx.data_object_factory_creator'),
      $container->get('http_client'),
      $container->get('logger.channel.media_mpx'),
      $container->get('media_mpx.custom_field_manager'),
      $container->get('file_system')
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
    $factory = $this->dataObjectFactoryCreator->fromMediaSource($this);

    return $factory->load(new Uri($id))->wait();
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
   * @param object $mpx_object
   *   The mpx object to get the property on.
   *
   * @return mixed|null
   *   Metadata attribute value or NULL if unavailable.
   */
  protected function getReflectedProperty(MediaInterface $media, string $attribute_name, $mpx_object) {
    $method = 'get' . ucfirst($attribute_name);
    $value = $mpx_object->$method();

    if ($value instanceof \DateTime || $value instanceof DateTimeFormatInterface) {
      // @todo Remove this when https://bravotv.atlassian.net/browse/BR-6856
      // is fixed.
      $value = $value->format('U');
    }

    return $value;
  }

  /**
   * Download a thumbnail to the local file system.
   *
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx media object to download the thumbnail for.
   *
   * @return string
   *   The existing thumbnail, or the newly downloaded thumbnail.
   */
  protected function downloadThumbnail(Media $mpx_media): string {
    $thumbnailUrl = $mpx_media->getNormalizedDefaultThumbnailUrl();
    $local_uri = $this->thumbnailsDirectory . $thumbnailUrl->getHost() . $thumbnailUrl->getPath();
    if (!file_exists($local_uri)) {
      $directory = dirname($local_uri);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $thumbnail = $this->httpClient->request('GET', $thumbnailUrl);
      $this->fileSystem->saveData((string) $thumbnail->getBody(), $local_uri);
    }

    return $local_uri;
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
  protected function thumbnailAlt(MediaInterface $media) {
    /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
    $mpx_media = $this->getMpxObject($media);
    if (!empty($media->label())) {
      return $media->label();
    }
    return $mpx_media->getTitle();
  }

}
