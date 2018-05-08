<?php

namespace Drupal\media_mpx_test;

use Psr\Http\Message\StreamInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use function GuzzleHttp\Psr7\parse_response;
use function GuzzleHttp\Psr7\str;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Mock HTTP client handler that uses the state system to store requests.
 *
 * @see \GuzzleHttp\Handler\MockHandler
 */
class MockStateHandler implements \Countable {

  /**
   * The state backend storing the queue of requests.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The last request that was handled.
   *
   * @var \Psr\Http\Message\RequestInterface
   */
  private $lastRequest;

  /**
   * The last set of request options.
   *
   * @var array
   */
  private $lastOptions;

  /**
   * Called on fulfilled requests.
   *
   * @var callable
   */
  private $onFulfilled;

  /**
   * Called on rejected requests.
   *
   * @var callable
   */
  private $onRejected;

  /**
   * Construct a new MockStateHandler.
   *
   * The state queue must be an array of
   * {@see Psr7\Http\Message\ResponseInterface} objects, Exceptions,
   * callables, or Promises.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state backend.
   * @param callable $onFulfilled
   *   Callback to invoke when the return value is fulfilled.
   * @param callable $onRejected
   *   Callback to invoke when the return value is rejected.
   */
  public function __construct(
    StateInterface $state,
    callable $onFulfilled = NULL,
    callable $onRejected = NULL
  ) {
    $this->state = $state;
    $this->onFulfilled = $onFulfilled;
    $this->onRejected = $onRejected;
  }

  /**
   * Guzzle handler callback.
   */
  public function __invoke(RequestInterface $request, array $options) {
    $queue = $this->state->get('media_mpx_test_queue', []);
    if (empty($queue)) {
      throw new \OutOfBoundsException('Mock queue is empty');
    }

    if (isset($options['delay'])) {
      usleep($options['delay'] * 1000);
    }

    $this->lastRequest = $request;
    $this->lastOptions = $options;
    $response = parse_response(array_shift($queue));
    $this->state->set('media_mpx_test_queue', $queue);

    if (isset($options['on_headers'])) {
      if (!is_callable($options['on_headers'])) {
        throw new \InvalidArgumentException('on_headers must be callable');
      }
      try {
        $options['on_headers']($response);
      }
      catch (\Exception $e) {
        $msg = 'An error was encountered during the on_headers event';
        $response = new RequestException($msg, $request, $response, $e);
      }
    }

    if (is_callable($response)) {
      $response = call_user_func($response, $request, $options);
    }

    $response = $response instanceof \Exception
      ? \GuzzleHttp\Promise\rejection_for($response)
      : \GuzzleHttp\Promise\promise_for($response);

    return $response->then(
      function ($value) use ($request, $options) {
        $this->invokeStats($request, $options, $value);
        if ($this->onFulfilled) {
          call_user_func($this->onFulfilled, $value);
        }
        if (isset($options['sink'])) {
          $contents = (string) $value->getBody();
          $sink = $options['sink'];

          if (is_resource($sink)) {
            fwrite($sink, $contents);
          }
          elseif (is_string($sink)) {
            file_put_contents($sink, $contents);
          }
          elseif ($sink instanceof StreamInterface) {
            $sink->write($contents);
          }
        }

        return $value;
      },
      function ($reason) use ($request, $options) {
        $this->invokeStats($request, $options, NULL, $reason);
        if ($this->onRejected) {
          call_user_func($this->onRejected, $reason);
        }
        return \GuzzleHttp\Promise\rejection_for($reason);
      }
    );
  }

  /**
   * Adds variadic requests, exceptions, callables, or promises to the queue.
   */
  public function append() {
    foreach (func_get_args() as $value) {
      if (is_array($value)) {
        foreach ($value as $v) {
          $this->append($v);
        }
        return;
      }
      $queue = $this->state->get('media_mpx_test_queue', []);
      if ($value instanceof ResponseInterface
        || $value instanceof \Exception
        || $value instanceof PromiseInterface
        || is_callable($value)
      ) {
        $queue[] = str($value);
      }
      else {
        throw new \InvalidArgumentException('Expected a response or '
          . 'exception. Found ' . \GuzzleHttp\describe_type($value));
      }
    }

    $this->state->set('media_mpx_test_queue', $queue);
  }

  /**
   * Get the last received request.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The last request.
   */
  public function getLastRequest() {
    return $this->lastRequest;
  }

  /**
   * Get the last received request options.
   *
   * @return array
   *   The last options.
   */
  public function getLastOptions() {
    return $this->lastOptions;
  }

  /**
   * Returns the number of remaining items in the queue.
   *
   * @return int
   *   The number of items in the queue.
   */
  public function count() {
    return count($this->state->get('media_mpx_test_queue', []));
  }

  /**
   * Fetch the transfer stats for a request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   * @param array $options
   *   The request options.
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   The response, if it exists.
   * @param mixed $reason
   *   The handler-specific reason data.
   */
  private function invokeStats(
    RequestInterface $request,
    array $options,
    ResponseInterface $response = NULL,
    $reason = NULL
  ) {
    if (isset($options['on_stats'])) {
      $stats = new TransferStats($request, $response, 0, $reason);
      call_user_func($options['on_stats'], $stats);
    }
  }

}
