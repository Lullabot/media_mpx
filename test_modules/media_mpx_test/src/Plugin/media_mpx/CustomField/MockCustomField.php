<?php

namespace Drupal\media_mpx_test\Plugin\media_mpx\CustomField;

use Lullabot\Mpx\DataService\Annotation\CustomField;
use Lullabot\Mpx\DataService\CustomFieldInterface;

/**
 * @CustomField(
 *   namespace="http://xml.example.com",
 *   service="Media Data Service",
 *   objectType="Media",
 * )
 */
class MockCustomField implements CustomFieldInterface {

  /**
   * The name of the series.
   *
   * @var string
   */
  protected $series;

  /**
   * @return string
   */
  public function getSeries(): string {
    return $this->series;
  }

  /**
   * @param string $series
   */
  public function setSeries(string $series) {
    $this->series = $series;
  }

}
