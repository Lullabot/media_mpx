<?php

namespace Drupal\media_mpx_test\Plugin\media_mpx\CustomField;

// @codingStandardsIgnoreStart
use Lullabot\Mpx\DataService\Annotation\CustomField;
// @codingStandardsIgnoreEnd
use Lullabot\Mpx\DataService\CustomFieldInterface;

/**
 * A mock custom field implementation containing a series.
 *
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
   * Return the series the Media object belongs to.
   *
   * @return string
   *   The series name.
   */
  public function getSeries(): string {
    return $this->series;
  }

  /**
   * Set the series.
   *
   * @param string $series
   *   The series to set.
   */
  public function setSeries(string $series) {
    $this->series = $series;
  }

}
