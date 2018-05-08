<?php

namespace Drupal\media_mpx\Element;

use Drupal\Core\Render\Element\RenderElement;
use Drupal\Core\Template\Attribute;

/**
 * Providers an element design for embedding iframes.
 *
 * @RenderElement("media_mpx_iframe")
 */
class MediaMpxIframe extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'media_mpx_iframe',
      '#url' => '',
      '#attributes' => [],
      '#pre_render' => [
        [static::class, 'preRenderInlineFrameEmbed'],
      ],
    ];
  }

  /**
   * Transform the render element structure into a renderable one.
   *
   * @param array $element
   *   An element array before being processed.
   *
   * @return array
   *   The processed and renderable element.
   */
  public static function preRenderInlineFrameEmbed(array $element) {
    if (is_array($element['#attributes'])) {
      $element['#attributes'] = new Attribute($element['#attributes']);
    }

    return $element;
  }

}
