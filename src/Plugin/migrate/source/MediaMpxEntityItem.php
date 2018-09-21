<?php

namespace Drupal\media_mpx\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Media MPX Item source plugin.
 *
 * Available configuration keys:
 * - entity_type: (optional) If supplied, this will only return fields
 *   of that particular type.
 *
 * @MigrateSource(
 *   id = "media_mpx_entity_item",
 *   source_module = "media_theplatform_mpx",
 * )
 */
class MediaMpxEntityItem extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('file_managed', 'f')
      ->fields('f')
      ->fields('m', ['id', 'title', 'description'])
      ->orderBy('f.timestamp');
    $query->innerJoin('mpx_video', 'm', 'f.fid = m.fid');

    // Filter by type, if configured.
    if (isset($this->configuration['type'])) {
      $query->condition('type', $this->configuration['type']);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Get Field API field values.
    foreach (array_keys($this->getFields('file', $row->getSourceProperty('type'))) as $field) {
      $fid = $row->getSourceProperty('fid');
      $row->setSourceProperty($field, $this->getFieldValues('file', $field, $fid));
    }

    // Set the mpx_url field.
    $id = $row->getSourceProperty('id');
    $row->setSourceProperty('mpx_url', 'http://data.media.theplatform.com/media/data/Media/' . $id);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'fid' => $this->t('The file identifier'),
      'uid' => $this->t('The user identifier'),
      'filename' => $this->t('The file name'),
      'uri' => $this->t('The URI of the file'),
      'filemime' => $this->t('The file mimetype'),
      'filesize' => $this->t('The file size'),
      'status' => $this->t('The file status'),
      'timestamp' => $this->t('The time that the file was added'),
      'type' => $this->t('The file type'),
      'created' => $this->t('The created timestamp'),
      'published' => $this->t('The published timestamp'),
      'promote' => $this->t('The promoted flag'),
      'sticky' => $this->t('The sticky flag'),
      'vid' => $this->t('The vid'),
      'mpx_url' => $this->t('The full MPX URL for this media'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['fid'] = [
      'type' => 'integer',
      'alias' => 'f',
    ];
    return $ids;
  }

}
