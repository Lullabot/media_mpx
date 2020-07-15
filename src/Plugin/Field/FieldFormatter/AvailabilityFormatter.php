<?php

namespace Drupal\media_mpx\Plugin\Field\FieldFormatter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\TimestampFormatter;
use Drupal\media\MediaInterface;
use Drupal\media_mpx\Plugin\media\Source\Media as MediaSource;
use Drupal\media_mpx\StubMediaObjectTrait;
use Lullabot\Mpx\DataService\DateTime\AvailabilityCalculator;
use Lullabot\Mpx\DataService\DateTime\DateTimeFormatInterface;
use Lullabot\Mpx\DataService\DateTime\NullDateTime;
use Lullabot\Mpx\DataService\Media\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter to surface the current availability of a mpx media entity.
 *
 * @FieldFormatter(
 *   id = "media_mpx_availability",
 *   label = @Translation("mpx availability"),
 *   field_types = {
 *     "timestamp",
 *   }
 * )
 */
class AvailabilityFormatter extends TimestampFormatter {
  use StubMediaObjectTrait;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new TimestampFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $date_format_storage
   *   The date format storage.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, DateFormatterInterface $date_formatter, EntityStorageInterface $date_format_storage, TimeInterface $time) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $date_formatter, $date_format_storage);
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('entity_type.manager')->getStorage('date_format'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $media = $items->getEntity();
    if (!$media instanceof MediaInterface || !$media->getSource() instanceof MediaSource) {
      $elements[0]['#markup'] = $this->t('Not applicable');
    }
    else {
      $mpx_object = $this->getStubMediaObject($media);
      $elements[0]['#markup'] = $this->getAvailabilitySummary($mpx_object);
    }

    return $elements;
  }

  /**
   * Get the availability summary for the given mpx object.
   *
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_object
   *   Mpx object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Availability summary.
   */
  protected function getAvailabilitySummary(Media $mpx_object) {
    $now = \DateTime::createFromFormat('U', $this->time->getCurrentTime());
    $calculator = new AvailabilityCalculator();
    $available_date = $mpx_object->getAvailableDate();
    $expired_date = $mpx_object->getExpirationDate();

    // Video is available.
    if ($calculator->isAvailable($mpx_object, $now)) {
      if (!empty($expired_date) && !$expired_date instanceof NullDateTime) {
        return $this->t('Available until @date', ['@date' => $this->formatDate($expired_date)]);
      }
      return $this->t('Available');
    }
    // Video is upcoming.
    // Note that the upstream library defines isExpired as !isAvailable, which
    // lumps in videos that are upcoming. We need this workaround to
    // specifically identify upcoming.
    elseif (!empty($available_date) && !$available_date instanceof NullDateTime && $now < $available_date->getDateTime()) {
      return $this->t('Upcoming @date', ['@date' => $this->formatDate($available_date)]);
    }

    // Video is neither available nor upcoming, therefore it's expired.
    if (!empty($expired_date) && !$expired_date instanceof NullDateTime) {
      return $this->t('Expired on @date', ['@date' => $this->formatDate($expired_date)]);
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
    $date_format = $this->getSetting('date_format');
    $custom_date_format = '';
    $timezone = $this->getSetting('timezone') ?: NULL;
    $langcode = NULL;

    // If an RFC2822 date format is requested, then the month and day have to
    // be in English. @see http://www.faqs.org/rfcs/rfc2822.html
    if ($date_format === 'custom' && ($custom_date_format = $this->getSetting('custom_date_format')) === 'r') {
      $langcode = 'en';
    }

    return $this->dateFormatter->format($date->format('U'), $date_format, $custom_date_format, $timezone, $langcode);
  }

}
