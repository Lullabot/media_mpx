<?php

namespace Drupal\media_mpx\Plugin\migrate\source;

use Drupal\paragraphs\Plugin\migrate\source\DrupalSqlBase;

/**
 * Mpx Type source plugin.
 *
 * @MigrateSource(
 *   id = "media_mpx_type",
 *   source_module = "media_theplatform_mpx"
 * )
 */
class MediaMpxType extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('file_managed', 'f')
      ->distinct()
      ->fields('f', ['type'])
      ->fields('m', ['account'])
      ->fields('a', ['id']);
    $query->innerJoin('mpx_video', 'm', 'f.fid = m.fid');
    $query->innerJoin('mpx_accounts', 'a', 'm.account = a.import_account');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'type' => $this->t('The MPX machine name'),
      'account' => $this->t('The MPX account name'),
      'id' => $this->t('The MPX account identifier. Legacy, type will be used instead'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['type']['type'] = 'string';
    return $ids;
  }

}
