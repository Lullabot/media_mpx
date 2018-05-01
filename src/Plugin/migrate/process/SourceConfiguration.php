<?php

namespace Drupal\media_mpx\Plugin\migrate\process;

use Drupal\migrate\Annotation\MigrateProcessPlugin;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Class SourceConfiguration
 *
 * @todo Should this be submitted to core?
 *
 * @MigrateProcessPlugin(
 *   id="media_source_configuration"
 * )
 */
class SourceConfiguration extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $source = $this->configuration['source'];
    foreach ($source as $key => $value) {
      $source[$key] = $row->getSourceProperty($value);
    }
    return $source;
  }

}
