<?php

namespace Drupal\media_mpx;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use GuzzleHttp\Exception\TransferException;
use Lullabot\Mpx\Exception\MpxExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * Utility class for logging mpx errors.
 *
 * In general, this should only be used for errors that are unexpected. For
 * example, the user / password form has it's own error handling for invalid
 * credentials, but calls this class on other types of exceptions.
 */
class MpxLogger {

  /**
   * The system logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * MpxLogger constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The system logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Log an mpx exception.
   *
   * @param \GuzzleHttp\Exception\TransferException $exception
   *   The exception that is going to be logged.
   * @param int $severity
   *   The severity of the message, as per RFC 3164.
   * @param string $link
   *   A link to associate with the message.
   */
  public function logException(TransferException $exception, $severity = RfcLogLevel::ERROR, $link = NULL) {
    if (!($exception instanceof MpxExceptionInterface)) {
      $this->watchdogException($exception, NULL, [], $severity, $link);
      return;
    }

    $message = 'HTTP %code %title %description %correlation_id %type in %function (line %line of %file).';
    $variables = [
      '%code' => $exception->getCode(),
      '%title' => $exception->getTitle(),
      '%description' => $exception->getDescription(),
    ];

    try {
      $variables['%correlation_id'] = $exception->getCorrelationId();
    }
    catch (\OutOfBoundsException $e) {
      // No correlation ID is included so ignore it.
    }
    $this->watchdogException($exception, $message, $variables, $severity, $link);
  }

  /**
   * Logs an exception.
   *
   * @param \Exception $exception
   *   The exception that is going to be logged.
   * @param string $message
   *   The message to store in the log.
   * @param array $variables
   *   Array of variables to replace in the message on display or
   *   NULL if message is already translated or not possible to
   *   translate.
   * @param int $severity
   *   The severity of the message, as per RFC 3164.
   * @param string $link
   *   A link to associate with the message.
   *
   * @see \Drupal\Core\Utility\Error::decodeException()
   */
  private function watchdogException(\Exception $exception, $message = NULL, array $variables = [], $severity = RfcLogLevel::ERROR, $link = NULL) {
    // Use a default value if $message is not set.
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
      $variables['link'] = $link;
    }

    $variables += Error::decodeException($exception);

    $this->logger->log($severity, $message, $variables);
  }

}
