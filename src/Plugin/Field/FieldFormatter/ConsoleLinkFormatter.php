<?php

namespace Drupal\media_mpx\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media_mpx\Entity\Account;
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

    if (!$this->appliesTo($items)) {
      return $element;
    }

    /** @var \Drupal\media_mpx\Entity\Account $account */
    $account = $items->getEntity()->getSource()->getAccount();

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#type' => 'link',
        '#title' => $item->getString(),
        '#url' => $this->getUrl($item, $account),
      ];
    }

    return $element;
  }

  /**
   * Does this field formatter apply to the given field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   *
   * @return bool
   *   TRUE if this field formatter applies, otherwise FALSE.
   */
  protected function appliesTo(FieldItemListInterface $items) {
    if (!($entity = $items->getEntity()) || !$entity instanceof Media) {
      return FALSE;
    }

    $source_plugin = $entity->getSource();
    if (!$source_plugin instanceof MpxMediaSource) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Creates a link to the mpx console for the given field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   An individual field item to be rendered. Assumed to contain the mpx guid.
   * @param \Drupal\media_mpx\Entity\Account $account
   *   Mpx account assigned to the media bundle for this field.
   *
   * @return \Drupal\Core\Url
   *   Url to the mpx console for this field item.
   */
  protected function getUrl(FieldItemInterface $item, Account $account) {
    $path_parts = explode('/', $account->getMpxId()->getPath());
    $account_id = end($path_parts);
    $options = [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'nofollow',
      ],
    ];
    return Url::fromUri(
      sprintf('https://console.theplatform.com/%s/media/%s/metadata#information', $account_id, $item->getString()),
      $options
    );
  }

}
