<?php

namespace Drupal\media_mpx\Plugin\Field\FieldFormatter;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media_mpx\Plugin\media\Source\Media as MpxMediaSource;

/**
 * Field formatter to link to the mpx console.
 *
 * @FieldFormatter(
 *   id = "media_mpx_console_link",
 *   label = @Translation("mpx Console Link"),
 *   description = @Translation("Suitable for use on fields storing the mpx guid value, the mpx console link formatter will create a link to the media in the mpx console.")
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class ConsoleLinkFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    if (!($entity = $items->getEntity()) || !$entity instanceof Media) {
      return $element;
    }

    $source_plugin = $entity->getSource();
    if (!$source_plugin instanceof MpxMediaSource) {
      return $element;
    }

    $account = $source_plugin->getAccount();
    $path_parts = explode('/', $account->getMpxId()->getPath());
    $account_id = end($path_parts);
    $options = [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'nofollow',
      ],
    ];

    foreach ($items as $delta => $item) {
      $url = Url::fromUri(
        sprintf('https://console.theplatform.com/%s/media/%s/metadata#information', $account_id, $item->getString()),
        $options
      );
      $element[$delta] = [
        '#type' => 'link',
        '#title' => $item->getString(),
        '#url' => $url,
      ];
    }

    return $element;
  }

}
