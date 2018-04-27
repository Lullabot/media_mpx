<?php

namespace Drupal\media_mpx\Plugin\media\Source;

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
use Lullabot\Mpx\DataService\ObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

abstract class MediaSourceBase extends \Drupal\media\MediaSourceBase implements MpxMediaSourceInterface {
  use MessengerTrait;

  /**
   * The service to load mpx data.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  protected $dataObjectFactoryCreator;

  /**
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

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
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, KeyValueFactoryInterface $keyValueFactory, DataObjectFactoryCreator $dataObjectFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->dataObjectFactoryCreator = $dataObjectFactory;
    $this->keyValueFactory = $keyValueFactory;
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
      $container->get('media_mpx.data_object_factory_creator')
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
   * Get the complete mpx Media object associated with a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Lullabot\Mpx\DataService\ObjectInterface
   *   The mpx object.
   */
  public function getMpxMedia(MediaInterface $media): ObjectInterface {
    $id = $media->get($this->configuration['source_field'])->getString();
    $store = $this->keyValueFactory->get($this->getPluginId());
    if (!$mpx_item = $store->get($id)) {
      $factory = $this->dataObjectFactoryCreator->fromMediaSource($this);

      $mpx_item = $factory->load($id)->wait();
      $store->set($id, $mpx_item);
    }
    return $mpx_item;
  }

  /**
   * Extract the properties available to set on a media entity.
   *
   * @param string $class
   *   The mpx library class to extract properties for.
   *
   * @return array
   *   An array of property values and their descriptions.
   */
  protected function extractMediaProperties(string $class): array {
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
    return [$propertyInfo, $propertyInfo->getProperties($class)];
  }
}
