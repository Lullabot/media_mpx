<?php

namespace Drupal\media_mpx\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * mpx Account importer.
 *
 * @package Drupal\media_mpx\Plugin\migrate\source
 * @MigrateSource(
 *   id = "media_mpx_account",
 *   source_module = "media_theplatform_mpx",
 * )
 */
class MpxAccount extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'username' => $this->t('mpx Username'),
      'import_account' => $this->t('Import account or account label'),
      'account_id' => $this->t('mpx Account ID'),
      'account_pid' => $this->t('mpx Account Public ID'),
      'default_player' => $this->t('Default account player'),
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
        'import_account',
        'account_id',
        'account_pid',
        'default_player',
      ]);
  }

}
