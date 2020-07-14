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
   */
  public function query() {
    $this->ensureMyTable();

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($this->definition['media_type']);
    $field_map = $media_type->getFieldMap();
    if (isset($field_map['Media:availableDate']) && isset($field_map['Media:expirationDate'])) {
      $media_storage = $this->entityTypeManager->getStorage('media');
      assert($media_storage instanceof SqlContentEntityStorage);
      $table_mapping = $media_storage->getTableMapping();

      $field_storage = $this->entityTypeManager->getStorage('field_storage_config');
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $available_field_storage */
      $available_field_storage = $field_storage->load('media.' . $field_map['Media:availableDate']);
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $expired_field_storage */
      $expired_field_storage = $field_storage->load('media.' . $field_map['Media:expirationDate']);
      $available_table_name = $table_mapping->getDedicatedDataTableName($available_field_storage);
      $expired_table_name = $table_mapping->getDedicatedDataTableName($expired_field_storage);
      $this->query->ensureTable($available_table_name);
      $this->query->ensureTable($expired_table_name);

      // Add a condition on the bundle that this filter is defined for.
      $media_definition = \Drupal::entityTypeManager()->getDefinition('media');
      $this->query->addWhere($this->options['group'], sprintf('%s.%s', $media_storage->getDataTable(), $media_definition->getKey('bundle')), $this->definition['media_type']);

      $available_field = sprintf('%s.%s', $available_table_name, $table_mapping->getFieldColumnName($available_field_storage, 'value'));
      $expired_field = sprintf('%s.%s', $expired_table_name, $table_mapping->getFieldColumnName($expired_field_storage, 'value'));
      if (empty($this->value) || $this->value === self::AVAILABLE_UPCOMING) {
        // In other words, not expired.
        $condition = (new Condition('OR'))
          ->condition($expired_field, 0)
          ->condition($expired_field, NULL, 'IS NULL')
          ->condition($expired_field, $this->time->getRequestTime(), '>=');
        $this->query->addWhere($this->options['group'], $condition);
      }
      elseif ($this->value === self::AVAILABLE) {
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
      elseif ($this->value === self::EXPIRED) {
        $condition = (new Condition('AND'))
          ->condition($expired_field, 0, '<>')
          ->condition($expired_field, NULL, 'IS NOT NULL')
          ->condition($expired_field, $this->time->getRequestTime(), '<=');
        $this->query->addWhere($this->options['group'], $condition);
      }
    }
  }

}
