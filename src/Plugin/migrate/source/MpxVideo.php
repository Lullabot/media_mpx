<?php

namespace Drupal\media_mpx\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * mpx Video (aka Media) importer.
 *
 * @package Drupal\media_mpx\Plugin\migrate\source
 * @MigrateSource(
 *   id = "media_mpx_video",
 *   source_module = "media_theplatform_mpx",
 * )
 */
class MpxVideo extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'video_id' => $this->t('The Drupal video ID'),
      'title' => $this->t('Media title'),
      'description' => $this->t('Media description'),
      'id' => $this->t('mpx Media ID'),
      'guid' => $this->t('mpx Media GUID'),
      'parent_account' => $this->t('Account ID from mpx_accounts'),
      'account' => $this->t('Account name'),
      'thumbnail_url' => $this->t('Local thumbnail URL'),
      'created' => $this->t('Created timestamp'),
      'updated' => $this->t('Updated timestamp'),
      'status' => $this->t('Video status'),
      'released_file_pids' => $this->t('Released file public IDs (serialized)'),
      'default_released_file_pid' => $this->t('The default video public ID'),
      'categories' => $this->t('Categories (serialized)'),
      'author' => $this->t('Video author'),
      'airdate' => $this->t('Airdate timestamp'),
      'available_date' => $this->t('Available date timestamp'),
      'expiration_date' => $this->t('Expiration date timestamp'),
      'keywords' => $this->t('Comma-separated keywords'),
      'vchip_rating' => $this->t('V-Chip rating'),
      'vchip_sub_ratings' => $this->t('V-Chip sub-ratings (serialized)'),
      'exclude_countries' => $this->t('Exclude configured countries'),
      'countries' => $this->t('Excluded countries (serialized)'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'video_id' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('mpx_video', 'v')
      ->fields('v', array_keys($this->fields()));
  }

}
