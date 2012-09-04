<?php

/**
 *  @file
 *  Create a YouTube Stream Wrapper class for the Media/Resource module.
 */

/**
 *  Create an instance like this:
 *  $youtube = new ResourceYouTubeStreamWrapper('youtube://?v=[video-code]');
 */
class MediaMPXStreamWrapper extends MediaReadOnlyStreamWrapper {
  //protected $base_url = 'http://youtube.com/watch';

  function getTarget($f) {
    return FALSE;
  }


  static function getMimeType($uri, $mapping = NULL) {
    return 'video/mpx';
  }


  function getOriginalThumbnailPath() {

    $parts = $this->get_parameters();
    $video = mpx_api_factory()->getVideoByID($parts['v']);

    return $video->defaultThumbnail;
  }

  function getLocalThumbnailPath() {
    $parts = $this->get_parameters();
    $local_path = 'public://media-mpx/' . check_plain($parts['v']) . '.jpg';

    if (!file_exists($local_path)) {
      $response = drupal_http_request($this->getOriginalThumbnailPath());
      if(!empty($response->data)){
        $dirname = drupal_dirname($local_path);
        file_prepare_directory($dirname, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
        file_save_data($response->data, $local_path);
        return $local_path;
      }
      else{
        return '';
      }
    }
    else{
       return $local_path;
    }

  }
}
