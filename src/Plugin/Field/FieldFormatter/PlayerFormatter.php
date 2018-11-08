<?php

namespace Drupal\media_mpx\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\media_mpx\DataObjectFactoryCreator;
use Drupal\media_mpx\MpxLogger;
use Drupal\media_mpx\Plugin\media\Source\Media;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Uri;
use Lullabot\Mpx\DataService\ObjectListQuery;
use Lullabot\Mpx\DataService\Player\Player;
use Lullabot\Mpx\DataService\Sort;
use Lullabot\Mpx\Service\Player\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Lullabot\Mpx\DataService\Media\Media as MpxMedia;
use Drupal\media\Entity\Media as DrupalMedia;

/**
 * Field formatter for an mpx player.
 *
 * @todo This needs to only attach to mpx media types.
 *
 * @FieldFormatter(
 *   id = "media_mpx_video",
 *   label = @Translation("mpx Video player"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class PlayerFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The creator used to load player factories.
   *
   * @var \Drupal\media_mpx\DataObjectFactoryCreator
   */
  protected $dataObjectFactoryCreator;

  /**
   * The logger for mpx errors.
   *
   * @var \Drupal\media_mpx\MpxLogger
   */
  protected $mpxLogger;

  /**
   * The system messenger for error reporting.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a PlayerFormatter object.
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
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\media_mpx\DataObjectFactoryCreator $data_object_factory_creator
   *   The creator of mpx data factories.
   * @param \Drupal\media_mpx\MpxLogger $mpx_logger
   *   The logger for mpx errors.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The system messenger for error reporting.
   */
  public function __construct(string $plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, string $label, string $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, DataObjectFactoryCreator $data_object_factory_creator, MpxLogger $mpx_logger, MessengerInterface $messenger) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->dataObjectFactoryCreator = $data_object_factory_creator;
    $this->mpxLogger = $mpx_logger;
    $this->messenger = $messenger;
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
      $container->get('entity_type.manager'),
      $container->get('media_mpx.data_object_factory_creator'),
      $container->get('media_mpx.exception_logger'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    /** @var \Drupal\media\Entity\Media $entity */
    $entity = $items->getEntity();
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $source_plugin */
    $source_plugin = $entity->getSource();

    // @todo Cache this.
    $factory = $this->dataObjectFactoryCreator->forObjectType($source_plugin->getAccount()->getUserEntity(), 'Player Data Service', 'Player', '1.6');

    try {
      $player = $factory->load(new Uri($this->getSetting('player')))->wait();
    }
    catch (TransferException $e) {
      // If we can't load a player, we can't render any elements.
      $this->mpxLogger->logException($e);
      return $element;
    }
    $this->renderIframes($items, $player, $element);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    // This method is called multiple times during a single request, so we have
    // a basic static cache as the results are slow to fetch.
    static $options = [];

    if (empty($options)) {
      try {
        $options = $this->fetchPlayerOptions();
      }
      catch (TransferException $e) {
        $this->mpxLogger->logException($e);
        $this->messenger->addError($this->t('An unexpected error occurred. The full error has been logged. %error',
          [
            '%error' => $e->getMessage(),
          ])
        );

        return [];
      }
    }

    $elements['player'] = [
      '#type' => 'select',
      '#title' => $this->t('mpx Player'),
      '#description' => $this->t('Select the mpx player to use for playing videos.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('player'),
    ];

    $elements['auto_play'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto play'),
      '#description' => $this->t('Will automatically begin playback for the video on page load. See <a href="@url">mpx documentation</a> for details.', ['@url' => 'https://docs.theplatform.com/help/player-player-autoplay']),
      '#default_value' => $this->getSetting('auto_play'),
    ];

    $elements['play_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Play all'),
      '#description' => $this->t('Turn on playlist auto-advance for the player. See <a href="@url">mpx documentation</a> for more details.', ['@url' => 'https://docs.theplatform.com/help/player-player-playall']),
      '#default_value' => $this->getSetting('play_all'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    // @todo Somehow cache the player title so we show that instead of the ID.
    $summary[] = $this->t('mpx Player: @title', ['@title' => $this->getSetting('player')]);
    $summary[] = $this->t('Auto play: @auto_play', ['@auto_play' => $this->getSetting('auto_play') ? 'true' : 'false']);
    $summary[] = $this->t('Play all : @play_all', ['@play_all' => $this->getSetting('play_all') ? 'true' : 'false']);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'player' => '',
      'auto_play' => FALSE,
      'play_all' => FALSE,
    ];
  }

  /**
   * Build the array of available players.
   *
   * @return array
   *   The array of player options.
   */
  protected function fetchPlayerOptions(): array {
    $options = [];
    $bundle = $this->fieldDefinition->getTargetBundle();
    /** @var \Drupal\media\Entity\MediaType $type */
    $type = $this->entityTypeManager->getStorage('media_type')->load($bundle);
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $source_plugin */
    $source_plugin = $type->getSource();

    $factory = $this->dataObjectFactoryCreator->forObjectType($source_plugin->getAccount()
      ->getUserEntity(), 'Player Data Service', 'Player', '1.6');
    $query = new ObjectListQuery();
    $sort = new Sort();
    $sort->addSort('title');
    $query->setSort($sort);

    /** @var \Lullabot\Mpx\DataService\Player\Player[] $results */
    $results = $factory->select($query, $source_plugin->getAccount());

    foreach ($results as $player) {
      if (!$player->getDisabled()) {
        $options[(string) $player->getId()] = $player->getTitle();
      }
    }
    return $options;
  }

  /**
   * Render the player iframes for this element.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The items to render.
   * @param \Lullabot\Mpx\DataService\Player\Player $player
   *   The player to render the items with.
   * @param array &$element
   *   The render array.
   */
  protected function renderIframes(FieldItemListInterface $items, Player $player, array &$element) {
    /** @var \Drupal\media\Entity\Media $entity */
    $entity = $items->getEntity();
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $source_plugin */
    $source_plugin = $entity->getSource();
    foreach ($items as $delta => $item) {
      $element[$delta] = $this->buildWrapper($entity, $source_plugin, $player);
    }
  }

  /**
   * Builds the render array for the wrapper.
   *
   * @param \Drupal\media\Entity\Media $entity
   *   The media entity.
   * @param \Drupal\media_mpx\Plugin\media\Source\Media $source_plugin
   *   The mpx source plugin.
   * @param \Lullabot\Mpx\DataService\Player\Player $player
   *   The mpx player.
   *
   * @return array|null
   *   The render array or null on an error.
   */
  private function buildWrapper(DrupalMedia $entity, Media $source_plugin, Player $player) {
    try {
      /** @var \Lullabot\Mpx\DataService\Media\Media $mpx_media */
      $mpx_media = $source_plugin->getMpxObject($entity);
    }
    catch (TransferException $e) {
      // If this media item is missing, continue on to the next element.
      $this->mpxLogger->logException($e);
      return NULL;
    }

    $element = [
      '#type' => 'media_mpx_iframe_wrapper',
      '#attributes' => [
        'class' => [
          'mpx-iframe-wrapper',
        ],
      ],
      '#meta' => $this->buildMeta($entity, $mpx_media, $player),
      '#content' => $this->buildPlayer($source_plugin, $player, $mpx_media),
      '#entity' => $entity,
      '#mpx_media' => $mpx_media,
    ];
    $this->addMediaFileDetails($element, $mpx_media);

    return $element;
  }

  /**
   * Adds schema.org metadata from the first MediaFile.
   *
   * @param array $element
   *   The individual element to insert the metadata into.
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx media object.
   */
  private function addMediaFileDetails(array &$element, MpxMedia $mpx_media) {
    $mpx_media_files = $mpx_media->getContent();

    if (isset($mpx_media_files[0])) {
      $mpx_media_file = $mpx_media_files[0];

      // We want to recalculate the duration so seconds roll over into minutes
      // and hours.
      // @see https://php.net/manual/en/dateinterval.format.php#113204
      $interval = new \DateInterval('PT' . round($mpx_media_file->getDuration()) . 'S');
      $from = new \DateTime();
      $to = clone $from;
      $to->add($interval);
      $diff = $from->diff($to);
      foreach ($diff as $k => $v) {
        $interval->$k = $v;
      }

      $element['#meta']['duration'] = ($interval)->format('PT%hH%iM%sS');
      $element['#meta']['height'] = $mpx_media_file->getHeight();
      $element['#meta']['width'] = $mpx_media_file->getWidth();
    }
  }

  /**
   * Builds the render array of the media player.
   *
   * @param \Drupal\media_mpx\Plugin\media\Source\Media $source_plugin
   *   The media source plugin.
   * @param \Lullabot\Mpx\DataService\Player\Player $player
   *   The media player.
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The media item.
   *
   * @return array
   *   The render array.
   */
  private function buildPlayer(Media $source_plugin, Player $player, MpxMedia $mpx_media) {
    $url = new Url($source_plugin->getAccount(), $player, $mpx_media);
    $url->setAutoplay((bool) $this->getSetting('auto_play'));
    $url->setPlayAll((bool) $this->getSetting('play_all'));
    return [
      '#type' => 'media_mpx_iframe',
      '#url' => (string) ($this->buildUrl($source_plugin, $mpx_media, $player)),
      '#attributes' => [
        'class' => [
          'mpx-player',
          'mpx-player-account--' . $source_plugin->getAccount()->id(),
        ],
      ],
    ];
  }

  /**
   * Build the URL for a player iframe.
   *
   * @param \Drupal\media_mpx\Plugin\media\Source\Media $source_plugin
   *   The source plugin associated with the media.
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx media object.
   * @param \Lullabot\Mpx\DataService\Player\Player $player
   *   The player to build the URL for.
   *
   * @return \Lullabot\Mpx\Service\Player\Url
   *   The player URL.
   */
  private function buildUrl(Media $source_plugin, MpxMedia $mpx_media, Player $player): Url {
    $url = new Url($source_plugin->getAccount(), $player, $mpx_media);
    return $url;
  }

  /**
   * Build the metadata keys for schema.org tags.
   *
   * @param \Drupal\media\Entity\Media $entity
   *   The media entity being rendered.
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_media
   *   The mpx media object.
   * @param \Lullabot\Mpx\DataService\Player\Player $player
   *   The player being rendered.
   *
   * @return array
   *   An array of schema.org data.
   */
  private function buildMeta(DrupalMedia $entity, MpxMedia $mpx_media, Player $player): array {
    /** @var \Drupal\media_mpx\Plugin\media\Source\Media $source_plugin */
    $source_plugin = $entity->getSource();
    $url = $this->buildUrl($source_plugin, $mpx_media, $player);
    $url->setEmbed(TRUE);
    return [
      'name' => $entity->label(),
      'thumbnailUrl' => file_create_url($source_plugin->getMetadata($entity, 'thumbnail_uri')),
      'description' => $mpx_media->getDescription(),
      'uploadDate' => $mpx_media->getAvailableDate()->format(DATE_ISO8601),
      'embedUrl' => (string) $url,
    ];
  }

}
