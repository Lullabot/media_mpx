<?php

namespace Drupal\media_mpx\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Media mpx event subscriber.
 */
class MediaMpxSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * After creating a media type, create its source field storage and instance.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Response event.
   */
  public function onPostRowSave(MigratePostRowSaveEvent $event) {
    if ($event->getMigration()->getSourcePlugin()->getPluginId() !== 'media_mpx_type') {
      return;
    }

    $media_type_id = $event->getRow()->getDestinationProperty('id');
    $media_type = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type_id);
    /** @var \Drupal\media\MediaSourceInterface $media_source */
    $media_source = $media_type->getSource();

    // First create the field storage if it does not exist.
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('media');
    if (!isset($fields[$media_source->getConfiguration()['source_field']])) {
      \Drupal::entityTypeManager()->getStorage('field_storage_config')
        ->create([
          'entity_type' => 'media',
          'field_name' => $media_source->getConfiguration()['source_field'],
          'type' => 'string',
          'cardinality' => 1,
          'settings' => [
            'max_length' => 255,
          ],
          'langcode' => 'en',
          'translatable' => 'false',
        ])->save();
    }

    // Then create the field instance if if does not exist.
    if (empty($media_source->getSourceFieldDefinition($media_type))) {
      /** @var \Drupal\field\FieldConfigInterface $source_field */
      $source_field = $media_source->createSourceField($media_type);
      $source_field->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_ROW_SAVE => ['onPostRowSave'],
    ];
  }

}
