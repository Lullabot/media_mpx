<?php

namespace Drupal\media_mpx\DateTime;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media_mpx\StubMediaObjectTrait;
use Lullabot\Mpx\DataService\DateTime\AvailabilityCalculator;
use Lullabot\Mpx\DataService\DateTime\DateTimeFormatInterface;
use Lullabot\Mpx\DataService\DateTime\NullDateTime;
use Lullabot\Mpx\DataService\Media\Media;

/**
 * Summarize a videos availability.
 */
class AvailabilitySummary {
  use StringTranslationTrait;
  use StubMediaObjectTrait;

  /**
   * Date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Drupal date format to use for formatting dates.
   *
   * Defaults to the short date format.
   *
   * @var string
   */
  protected $dateFormat = 'short';

  /**
   * Custom date format string.
   *
   * If $this->dateFormat is 'custom', a custom date format should be set.
   *
   * @var string
   */
  protected $customDateFormat = '';

  /**
   * Alternate timezone to use when formatting dates.
   *
   * @var string
   */
  protected $timezone;

  /**
   * AvailabilitySummary constructor.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Date time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   */
  public function __construct(TimeInterface $time, DateFormatterInterface $date_formatter) {
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Set the Drupal date format to use for formatting dates.
   *
   * @param string $date_format
   *   Drupal date format.
   *
   * @return \Drupal\media_mpx\DateTime\AvailabilitySummary
   *   Fluent API return.
   */
  public function setDateFormat(string $date_format): AvailabilitySummary {
    $this->dateFormat = $date_format;
    return $this;
  }

  /**
   * Set a custom date format string.
   *
   * @param string $custom_date_format
   *   Custom date format string.
   *
   * @return \Drupal\media_mpx\DateTime\AvailabilitySummary
   *   Fluent API return.
   */
  public function setCustomDateFormat(string $custom_date_format): AvailabilitySummary {
    $this->customDateFormat = $custom_date_format;
    return $this;
  }

  /**
   * Set the timezone to use when formatting dates.
   *
   * @param string $timezone
   *   A timezone.
   *
   * @return \Drupal\media_mpx\DateTime\AvailabilitySummary
   *   Fluent API return.
   */
  public function setTimezone(string $timezone): AvailabilitySummary {
    $this->timezone = !empty($timezone) ? $timezone : NULL;
    return $this;
  }

  /**
   * Get the availability summary for the given media.
   *
   * If a date format is given, the date will be included in the summary when
   * applicable, otherwise, only a summary keyword will be returned.
   *
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_object
   *   Mpx media object.
   * @param bool $include_date
   *   Indicate whether to include the date. The date can further be customized
   *   using the setDateFormat, setTimezone, and setCustomDateFormat setters.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Availability summary.
   */
  public function getAvailabilitySummary(Media $mpx_object, $include_date = TRUE): TranslatableMarkup {
    $now = \DateTime::createFromFormat('U', $this->time->getCurrentTime());
    $calculator = new AvailabilityCalculator();
    $available_date = $mpx_object->getAvailableDate();
    $expired_date = $mpx_object->getExpirationDate();

    if ($calculator->isAvailable($mpx_object, $now)) {
      return $this->getAvailableSummary($expired_date, $include_date);
    }
    // Video is upcoming. Note that the upstream library defines isExpired as
    // !isAvailable, which lumps in videos that are upcoming. We need this
    // workaround to specifically identify upcoming.
    elseif (!empty($available_date) && !$available_date instanceof NullDateTime && $now < $available_date->getDateTime()) {
      return $this->getUpcomingSummary($available_date, $include_date);
    }

    // Video is neither available nor upcoming, therefore it's expired.
    return $this->getExpiredSummary($expired_date, $include_date);
  }

  /**
   * Get a summary for when a video is available.
   *
   * @param mixed $expired_date
   *   Expiration date of a video.
   * @param bool $include_date
   *   Whether to include the date, or simply a short summary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Summary text.
   */
  public function getAvailableSummary($expired_date, bool $include_date = TRUE): TranslatableMarkup {
    if ($include_date && !empty($expired_date) && !$expired_date instanceof NullDateTime) {
      return $this->t('Available until @date', [
        '@date' => $this->formatDate($expired_date),
      ]);
    }
    return $this->t('Available');
  }

  /**
   * Get a summary for when a video is upcoming.
   *
   * @param mixed $available_date
   *   Available date of a video.
   * @param bool $include_date
   *   Whether to include the date, or simply a short summary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Summary text.
   */
  public function getUpcomingSummary($available_date, bool $include_date = TRUE): TranslatableMarkup {
    if ($include_date && !empty($available_date) && !$available_date instanceof NullDateTime) {
      return $this->t('Upcoming @date', [
        '@date' => $this->formatDate($available_date),
      ]);
    }
    return $this->t('Upcoming');
  }

  /**
   * Get a summary for when the video is expired.
   *
   * @param mixed $expired_date
   *   Expiration date of a video.
   * @param bool $include_date
   *   Whether to include the date, or simply a short summary.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Summary text.
   */
  public function getExpiredSummary($expired_date, bool $include_date = TRUE) {
    if ($include_date && !empty($expired_date) && !$expired_date instanceof NullDateTime) {
      return $this->t('Expired on @date', [
        '@date' => $this->formatDate($expired_date),
      ]);
    }
    return $this->t('Expired');
  }

  /**
   * Format the given mpx date according to the field formatter settings.
   *
   * @param \Lullabot\Mpx\DataService\DateTime\DateTimeFormatInterface $date
   *   An mpx date object.
   *
   * @return string
   *   Formatted date according to this field formatters settings.
   */
  protected function formatDate(DateTimeFormatInterface $date) {
    $langcode = NULL;

    // If an RFC2822 date format is requested, then the month and day have to
    // be in English. @see http://www.faqs.org/rfcs/rfc2822.html
    if ($this->dateFormat === 'custom' && $this->customDateFormat === 'r') {
      $langcode = 'en';
    }

    return $this->dateFormatter->format($date->format('U'), $this->dateFormat, $this->customDateFormat, $this->timezone, $langcode);
  }

}

