<?php

namespace Drupal\media_mpx\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\TimestampFormatter;
use Drupal\media\MediaInterface;
use Drupal\media_mpx\AvailabilitySummary;
use Drupal\media_mpx\Plugin\media\Source\Media as MediaSource;
use Drupal\media_mpx\StubMediaObjectTrait;
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
   * @var \Drupal\media_mpx\AvailabilitySummary
   */
  protected $availabilitySummary;

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
   * @param \Drupal\media_mpx\AvailabilitySummary $availability_summary
   *   Utility class to help with the availability summary.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, DateFormatterInterface $date_formatter, EntityStorageInterface $date_format_storage, AvailabilitySummary $availability_summary) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $date_formatter, $date_format_storage);
    $this->availabilitySummary = $availability_summary;
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
      $container->get('media_mpx.availability_summary')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $media = $items->getEntity();
    if (!$media instanceof MediaInterface || !$media->getSource() instanceof MediaSource) {
      $elements[0]['#markup'] = $this->t('Not applicable');
      return $elements;
    }

    $mpx_object = $this->getStubMediaObject($media);
    $elements[0]['#markup'] = $this->availabilitySummary
      ->setDateFormat($this->getSetting('date_format'))
      ->setCustomDateFormat($this->getSetting('custom_date_format'))
      ->setTimezone($this->getSetting('timezone'))
      ->getAvailabilitySummary($mpx_object);
    return $elements;
  }

}
