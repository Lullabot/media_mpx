<?php

namespace Drupal\media_mpx\Plugin\migrate;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationDeriverTrait;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\MigrateFieldPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for MPX content.
 */
class MpxItemDeriver extends DeriverBase implements ContainerDeriverInterface {
  use MigrationDeriverTrait;

  /**
   * The base plugin ID this derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * Already-instantiated field plugins, keyed by ID.
   *
   * @var \Drupal\migrate_drupal\Plugin\MigrateFieldInterface[]
   */
  protected $fieldPluginCache;

  /**
   * The field plugin manager.
   *
   * @var \Drupal\migrate_drupal\Plugin\MigrateFieldPluginManagerInterface
   */
  protected $fieldPluginManager;

  /**
   * The migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * D7FileEntityItemDeriver constructor.
   *
   * @param string $base_plugin_id
   *   The base plugin ID for the plugin ID.
   * @param \Drupal\migrate_drupal\Plugin\MigrateFieldPluginManagerInterface $field_manager
   *   The field plugin manager.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   Migration plugin manager service.
   */
  public function __construct($base_plugin_id, MigrateFieldPluginManagerInterface $field_manager, MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->fieldPluginManager = $field_manager;
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('plugin.manager.migrate.field'),
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $types = static::getSourcePlugin('media_mpx_type');

    try {
      $types->checkRequirements();
    }
    catch (RequirementsException $e) {
      return $this->derivatives;
    }

    $fields = $this->getFileFieldInstances();

    try {
      foreach ($types as $row) {
        $this->getDerivativeDefinitionsByRow($base_plugin_definition, $row, $fields);
      }
    }
    catch (DatabaseExceptionWrapper $e) {
      // Once we begin iterating the source plugin it is possible that the
      // source tables will not exist. This can happen when the
      // MigrationPluginManager gathers up the migration definitions but we do
      // not actually have a Drupal 7 source database.
    }
    return $this->derivatives;
  }

  /**
   * Get all the source file entity field instances.
   *
   * @return array
   *   Array of file field instances keyed by bundle and field name.
   */
  protected function getFileFieldInstances() {
    $fields = [];
    try {
      $source_plugin = static::getSourcePlugin('d7_field_instance');
      $source_plugin->checkRequirements();

      foreach ($source_plugin as $row) {
        if ($row->getSourceProperty('entity_type') == 'file') {
          $fields[$row->getSourceProperty('bundle')][$row->getSourceProperty('field_name')] = $row->getSource();
        }
      }
    }
    catch (RequirementsException $e) {
      // No fields, no problem. We can keep going.
    }
    return $fields;
  }

  /**
   * Get the derivative definitions for each source file entity field instance.
   *
   * @param array $base_plugin_definition
   *   The definition array of the base plugin.
   * @param \Drupal\migrate\Row $row
   *   Source row.
   * @param array $fields
   *   Array of file field instances keyed by bundle and field name.
   */
  protected function getDerivativeDefinitionsByRow(array $base_plugin_definition, Row $row, array $fields) {
    /** @var \Drupal\migrate\Row $row */
    $values = $base_plugin_definition;
    $bundle_name = $row->getSourceProperty('type');

    // Create the migration derivative.
    $values['source']['type'] = $bundle_name;
    $values['label'] = t('@label (@type)', [
      '@label' => $base_plugin_definition['label'],
      '@type' => $bundle_name,
    ]);
    $values['destination']['bundle'] = $bundle_name;

    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationPluginManager
      ->createStubMigration($values);
    if (isset($fields[$bundle_name])) {
      $this->processFieldsByBundle($migration, $fields[$bundle_name]);
    }
    $this->derivatives[$bundle_name] = $migration->getPluginDefinition();
  }

  /**
   * Process the given field instances on a given bundle from the source.
   *
   * @param \Drupal\migrate\Plugin\Migration $migration
   *   Migration plugin.
   * @param array $bundle_fields
   *   Array of fields for a given bundle keyed by field name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function processFieldsByBundle(Migration $migration, array $bundle_fields) {
    foreach ($bundle_fields as $field_name => $info) {
      $this->createFieldPluginFromFieldPluginManager($field_name, $info, $migration);
    }
  }

  /**
   * Create field plugins from the field info for the given migration.
   *
   * Attempt to use the field plugin manager.
   *
   * @param string $field_name
   *   Field name of the source field to process.
   * @param array $info
   *   Field configuration for the field to process.
   * @param \Drupal\migrate\Plugin\Migration $migration
   *   Migration plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createFieldPluginFromFieldPluginManager(string $field_name, array $info, Migration $migration) {
    $field_type = $info['type'];
    $plugin_id = $this->fieldPluginManager->getPluginIdFromFieldType($field_type, ['core' => 7], $migration);
    if (!isset($this->fieldPluginCache[$field_type])) {
      $this->fieldPluginCache[$field_type] = $this->fieldPluginManager->createInstance($plugin_id, ['core' => 7], $migration);
    }
    $this->fieldPluginCache[$field_type]
      ->defineValueProcessPipeline($migration, $field_name, $info);
  }

}
