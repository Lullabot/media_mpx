<?php

namespace Drupal\media_mpx\Plugin\views\filter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by mpx availability.
 *
 * @ViewsFilter("media_mpx_availability")
 */
class Availability extends FilterPluginBase {

  const AVAILABLE_UPCOMING = 'available_upcoming';

  const AVAILABLE = 'available';

  const EXPIRED = 'expired';

  /**
   * @var bool
   * Disable the possibility to use operators.
   */
  public $no_operator = TRUE;

  /**
   * @var bool
   * Disable the possibility to force a single value.
   */
  protected $alwaysMultiple = TRUE;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a Availability object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Date time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
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
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'select',
      '#options' => [
        self::AVAILABLE_UPCOMING => $this->t('Available or upcoming'),
        self::AVAILABLE => $this->t('Available'),
        self::EXPIRED => $this->t('Expired'),
      ],
      '#title' => $this->t('Availability'),
      '#default_value' => $this->value,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function query() {
    $this->ensureMyTable();
    $media_storage = $this->entityTypeManager->getStorage('media');
    // This will be limited to SQL storage backends, b/c it's highly SQL
    // centric.
    if (!$media_storage instanceof SqlContentEntityStorage) {
      return;
    }

    try {
      $this->ensureAvailabilityTables();
      $this->addConditionOnBundle();
      $this->addConditionOnAvailability();
    }
    catch (\RuntimeException $e) {
      // Nothing can do.
    }
  }

  /**
   * Get the field names mapped to the mpx available and expired dates.
   *
   * @return array
   *   An array with the field name mapped to the mpx available date in the
   *   first index, and the field name mapped to the mpx expired date in the
   *   second index.
   *
   * @throws \RuntimeException
   *   Thrown when the available date and/or expiration date is not mapped.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAvailableExpiredFieldNames() {
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($this->definition['media_type']);
    $field_map = $media_type->getFieldMap();
    if (isset($field_map['Media:availableDate']) && isset($field_map['Media:expirationDate'])) {
      return [$field_map['Media:availableDate'], $field_map['Media:expirationDate']];
    }
    throw new \RuntimeException(sprintf('mpx available date and/or mpx expiration date are not mapped for the %s media bundle.', $this->definition['media_type']));
  }

  /**
   * Ensure that we are joined with the availability tables.
   *
   * @throws \RuntimeException
   *   Thrown when the available date and/or expiration date is not mapped.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function ensureAvailabilityTables() {
    [$available_table_name, $expired_table_name] = $this->getAvailableExpiredTableNames();
    $this->query->ensureTable($available_table_name, $this->relationship);
    $this->query->ensureTable($expired_table_name, $this->relationship);
  }

  /**
   * Get the available and expired table names, optionally with the column.
   *
   * @param bool $include_column
   *   Include the column name for the value.
   *
   * @return array
   *   An array with the available table name in the first entry and the expired
   *   table name in the entry.
   *
   * @throws \RuntimeException
   *   Thrown when the available date and/or expiration date is not mapped.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAvailableExpiredTableNames($include_column = FALSE) {
    [$available_field_name, $expired_field_name] = $this->getAvailableExpiredFieldNames();
    $field_storage = $this->entityTypeManager->getStorage('field_storage_config');
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $available_field_storage */
    $available_field_storage = $field_storage->load('media.' . $available_field_name);
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $expired_field_storage */
    $expired_field_storage = $field_storage->load('media.' . $expired_field_name);
    $media_storage = $this->entityTypeManager->getStorage('media');
    $table_mapping = $media_storage->getTableMapping();
    $available_table_name = $table_mapping->getDedicatedDataTableName($available_field_storage);
    $expired_table_name = $table_mapping->getDedicatedDataTableName($expired_field_storage);
    if ($include_column) {
      $available_table_name = sprintf('%s.%s', $available_table_name, $table_mapping->getFieldColumnName($available_field_storage, 'value'));
      $expired_table_name = sprintf('%s.%s', $expired_table_name, $table_mapping->getFieldColumnName($expired_field_storage, 'value'));
    }
    return [$available_table_name, $expired_table_name];
  }

  /**
   * Add a condition to the query on the bundle this filter id for.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addConditionOnBundle() {
    // Add a condition on the bundle that this filter is defined for.
    $media_definition = $this->entityTypeManager->getDefinition('media');
    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], sprintf('%s.%s', $this->tableAlias, $media_definition->getKey('bundle')), $this->definition['media_type']);
  }

  /**
   * Add a condition to the query for availability according to the selection.
   *
   * @throws \RuntimeException
   *   Thrown when the available date and/or expiration date is not mapped.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addConditionOnAvailability() {
    if (empty($this->value) || $this->value === self::AVAILABLE_UPCOMING) {
      $this->addConditionOnAvailableOrUpcoming();
    }
    elseif ($this->value === self::AVAILABLE) {
      $this->addConditionOnAvailable();
    }
    elseif ($this->value === self::EXPIRED) {
      $this->addConditionOnExpired();
    }
  }

  /**
   * Add a condition on available or upcoming content.
   *
   * @throws \RuntimeException
   *   Thrown when the available date and/or expiration date is not mapped.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addConditionOnAvailableOrUpcoming() {
    [, $expired_field] = $this->getAvailableExpiredTableNames(TRUE);
    $condition = (new Condition('OR'))
      ->condition($expired_field, 0)
      ->condition($expired_field, NULL, 'IS NULL')
      ->condition($expired_field, $this->time->getRequestTime(), '>=');
    $this->query->addWhere($this->options['group'], $condition);
  }

  /**
   * Add a condition on available content.
   *
   * @throws \RuntimeException
   *   Thrown when the available date and/or expiration date is not mapped.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addConditionOnAvailable() {
    [$available_field, $expired_field] = $this->getAvailableExpiredTableNames(TRUE);
    $available_condition = (new Condition('OR'))
      ->condition($available_field, 0)
      ->condition($available_field, NULL, 'IS NULL')
      ->condition($available_field, $this->time->getRequestTime(), '<=');
    $this->query->addWhere($this->options['group'], $available_condition);
    $expired_condition = (new Condition('OR'))
      ->condition($expired_field, 0)
      ->condition($expired_field, NULL, 'IS NULL')
      ->condition($expired_field, $this->time->getRequestTime(), '>=');
    $this->query->addWhere($this->options['group'], $expired_condition);
  }

  /**
   * Add a condition on expired content.
   *
   * @throws \RuntimeException
   *   Thrown when the available date and/or expiration date is not mapped.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function addConditionOnExpired() {
    [, $expired_field] = $this->getAvailableExpiredTableNames(TRUE);
    $condition = (new Condition('AND'))
      ->condition($expired_field, 0, '<>')
      ->condition($expired_field, NULL, 'IS NOT NULL')
      ->condition($expired_field, $this->time->getRequestTime(), '<=');
    $this->query->addWhere($this->options['group'], $condition);
  }

}
