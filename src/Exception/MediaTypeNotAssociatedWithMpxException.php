<?php

namespace Drupal\media_mpx\Exception;

/**
 * Thrown when an mpx specific operation is attempted on a no-mpx media type.
 *
 * @package Drupal\media_mpx\Exception
 */
class MediaTypeNotAssociatedWithMpxException extends \LogicException {}
