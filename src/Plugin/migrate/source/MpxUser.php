<?php

namespace Drupal\media_mpx\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * mpx User importer.
 *
 * @package Drupal\media_mpx\Plugin\migrate\source
 * @MigrateSource(
 *   id = "media_mpx_user",
 *   source_module = "media_theplatform_mpx",
 * )
 */
class MpxUser extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'username' => $this->t('mpx Username'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('mpx_accounts', 'a')
      ->fields('a', [
        'id',
        'username',
      ]);
  }

}
