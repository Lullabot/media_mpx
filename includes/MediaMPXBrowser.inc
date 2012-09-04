<?php

/**
 * @file
 * Definition of MediaYouTubeBrowser.
 */

/**
 * Media browser plugin for displaying a specific view and display.
 */
class MediaMPXBrowser extends MediaBrowserPlugin {
  /**
   * Implements MediaBrowserPluginInterface::access().
   */
  public function access($account = NULL) {
    // @todo Replace with file_access().
    return media_access('edit', $account);
  }

  /**
   * Implements MediaBrowserPlugin::view().
   */
  public function view() {
    $build['form'] = drupal_get_form('media_mpx_add', $this->params['types'], $this->params['multiselect']);
    return $build;
  }
}
