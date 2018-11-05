<?php

namespace Drupal\media_mpx\Element;

/**
 * Provides a render element for wrapping media iframes.
 *
 * @RenderElement("media_mpx_iframe_wrapper")
 */
class MediaMpxIframeWrapper extends MediaMpxIframe {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $info['#theme'] = 'media_mpx_iframe_wrapper';
    return $info;
  }

}
