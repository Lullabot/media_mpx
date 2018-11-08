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
      ->fields('m', [
        'id',
        'title',
        'guid',
        'description',
        'released_file_pids',
        'updated',
        'default_released_file_pid',
        'categories',
        'author',
        'airdate',
        'available_date',
        'expiration_date',
        'keywords',
        'copyright',
        'related_link',
        'fab_rating',
        'fab_sub_ratings',
        'mpaa_rating',
        'mpaa_sub_ratings',
        'vchip_rating',
        'vchip_sub_ratings',
        'exclude_countries',
        'countries',
      ])
      ->orderBy('f.timestamp');
    $query->addField('m', 'created', 'mpx_created');
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

    // Unserialize released_file_pids.
    $released_file_pids = unserialize($row->getSourceProperty('released_file_pids'));
    $row->setSourceProperty('released_file_pids', $released_file_pids);

    // Unserialize categories.
    $categories = unserialize($row->getSourceProperty('categories'));
    $row->setSourceProperty('categories', $categories);

    // Unserialize fab_sub_ratings.
    $fab_sub_ratings = unserialize($row->getSourceProperty('fab_sub_ratings'));
    $row->setSourceProperty('fab_sub_ratings', $fab_sub_ratings);

    // Unserialize mpaa_sub_ratings.
    $mpaa_sub_ratings = unserialize($row->getSourceProperty('mpaa_sub_ratings'));
    $row->setSourceProperty('mpaa_sub_ratings', $mpaa_sub_ratings);

    // Unserialize vchip_sub_ratings.
    $vchip_sub_ratings = unserialize($row->getSourceProperty('vchip_sub_ratings'));
    $row->setSourceProperty('vchip_sub_ratings', $vchip_sub_ratings);

    // Unserialize countries.
    $countries = unserialize($row->getSourceProperty('countries'));
    $row->setSourceProperty('countries', $countries);

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
      'id' => $this->t('MPX Media ID'),
      'guid' => $this->t('MPX Media GUID'),
      'title' => $this->t('Title'),
      'description' => $this->t('Description'),
      'released_file_pids' => $this->t('MPX Released File Public IDs'),
      'default_released_file_pid' => $this->t('MPX Media Default Released File Public ID'),
      'categories' => $this->t('MPX Media Categories'),
      'author' => $this->t('MPX Media Author'),
      'airdate' => $this->t('MPX Media Air Date'),
      'available_date' => $this->t('MPX Media Available Date'),
      'expiration_date' => $this->t('MPX Media MPX Media Expiration Date'),
      'keywords' => $this->t('MPX Media Keywords'),
      'copyright' => $this->t('MPX Media Copyright'),
      'related_link' => $this->t('MPX Media Related Link'),
      'fab_rating' => $this->t('MPX Media Film Advisory Board Rating'),
      'fab_sub_ratings' => $this->t('MPX Media Film Advisory Board Sub-Ratings'),
      'mpaa_rating' => $this->t('MPX Media MPAA Rating'),
      'mpaa_sub_ratings' => $this->t('MPX Media MPAA Sub-Ratings'),
      'vchip_rating' => $this->t('MPX Media V-Chip Rating'),
      'vchip_sub_ratings' => $this->t('MPX Media V-Chip Sub-Ratings'),
      'exclude_countries' => $this->t('MPX Media Exclude Selected Countries'),
      'countries' => $this->t('MPX Media Selected Countries'),
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
