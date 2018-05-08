<?php

namespace Drupal\media_mpx_test;

use GuzzleHttp\Psr7\Response;

/**
 * A JSON response implementation.
 */
class JsonResponse extends Response {

  /**
   * The relative path to test fixtures.
   */
  const TESTS_FIXTURES = __DIR__ . '/../../../tests/fixtures/';

  /**
   * {@inheritdoc}
   */
  public function __construct($status = 200, array $headers = [], $body = NULL, $version = '1.1', $reason = NULL) {
    if (isset($body)) {
      $body = $this->generateBody($body);
    }
    $headers += [
      'Content-Type' => 'application/json',
    ];
    parent::__construct($status, $headers, $body, $version, $reason);
  }

  /**
   * Generate the body from a path, string, or array.
   *
   * @param string|array $body
   *   The mock body.
   *
   * @return bool|resource|string
   *   The body resource or string.
   */
  private function generateBody($body) {
    if (is_string($body) && is_file($body)) {
      $body = fopen($body, 'r');
    }
    elseif (is_string($body) && is_file(self::TESTS_FIXTURES . $body)) {
      $body = fopen(self::TESTS_FIXTURES . $body, 'r');
    }
    elseif (is_array($body)) {
      $body = \GuzzleHttp\json_encode($body);
    }
    return $body;
  }

}
