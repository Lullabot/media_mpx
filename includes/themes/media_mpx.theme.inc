<?php

/**
 * @file media_mpx/includes/themes/media_mpx.theme.inc
 *
 * Theme and preprocess functions for Media: mpx.
 */

/**
 * Preprocess function for theme('media_mpx_video').
 */
function media_mpx_preprocess_media_mpx_video(&$variables) {
  // Build the URL for display.
  $uri = $variables['uri'];
  $wrapper = file_stream_wrapper_get_instance_by_uri($uri);
  $parts = $wrapper->get_parameters();

  $video = mpx_api_factory()->getVideoByID($parts['v']);
  $variables['video'] = $video;
  $variables['query'] = array();
  $variables['release_url'] = $video->getReleaseUrl();


  // @see http://code.google.com/apis/youtube/player_parameters.html
  foreach (array('width', 'height', 'autoplay', 'related', 'hd', 'showsearch', 'modestbranding', 'showinfo', 'version', 'theme', 'fullscreen', 'wmode', 'chromeless') as $option) {
    // Set the option, either from the options array, or from the default value.
    $variables[$option] = isset($variables[$option]) ? $variables[$option] : (isset($variables['options'][$option]) ? $variables['options'][$option] : media_mpx_variable_get($option));
  }

  drupal_add_js('http://resources.vmdev.sbs.com.au/vod/theplatform/core/current/tpPdk.js', 'external');


  // We have to set fullscreen in the url query and as a parameter to the flash.
  $variables['fs'] = $variables['fullscreen'];
  $variables['fullscreen'] = $variables['fullscreen'] ? 'true' : 'false';

  $variables['wrapper_id'] = 'media_mpx_' . $video->id . '_' . $video->id;

  $params = array(
    'backgroundColor'           => '0x131313',
    'controlBackgroundColor'    => '0x131313',
    'controlColor'              => '0xBEBEBE',
    'controlFrameColor'         => '0x545759',
    'controlHoverColor'         => '0xBEBEBE',
    'controlSelectedColor'      => '0xFDDA00',
    'frameColor'                => '0x545759',
    'pageBackgroundColor'       => '0x131313',
    'playProgressColor'         => '0xFDDA00',
    'scrubberColor'             => '0xFDDA00',
    'scrubberFrameColor'        => '0x00CCFF',
    'scrubTrackColor'           => '0xBEBEBE',
    'textBackgroundColor'       => '0x383838',
    'textColor'                 => '0xBEBEBE',
    'loadProgressColor'         => '0xD6D6D4',
    'scale'                     => 'scale',
    'salign'                    => 'tl',
    'bgcolor'                   => '#2a2a2a',
    //'playerURL'                 => "http://{$_SERVER['HTTP_HOST']}{$this->view->baseUrl}/video/{$this->videoId}/{title}",
    //'endCard'                   => 'tpShareCard',
    'emailServiceURL'           => "http://{$_SERVER['HTTP_HOST']}/vod/theplatform/mail.php",
    //'embeddedPlayerHTML'        => '<iframe src="http://'.$_SERVER['HTTP_HOST'].$this->view->baseUrl.'/video/single/'.$this->videoId.'/{title}" width="640" height="396" type="application/x-shockwave-flash" allowFullScreen="true" bgcolor="#131313" />',
    'sharingSiteIds'            => 'facebook,twitter,reddit,digg',
    'previewImageBlurThreshold' => 0, // disables the blurring applied to images with a less dimension to the video player...
    'videoScalingMethod'        => 'resize',
    'layoutUrl' => "http://{$_SERVER['HTTP_HOST']}/vod/theplatform/data/sbs/defaultLayout.xml",

    //'logLevel' => 'debug'
  );
  $pdkCore = "http://{$_SERVER['HTTP_HOST']}/vod/theplatform/";
  /*
  $params['plugins'] = array(
    'type=adcomponent|URL=' . $pdkCore . 'swf/vast.swf|priority=2|hosts=ad.au.doubleclick.net|mimeTypes=video/x-mp4,video/x-flv', // doubleclick ads...
    'type=adcomponent|URL=' . $pdkCore . 'js/plugins/vast.js|priority=3|hosts=ad.doubleclick.net',
    'type=adcomponent|URL=' . $pdkCore . 'swf/inStream.swf|priority=1|host=ad.au.doubleclick.net',
    'type=player|URL='.$pdkCore.'swf/akamaiHD.swf|priority=1|hosts=sbsauvod-f.akamaihd.net',
    'type=text|URL=' . $pdkCore . 'swf/advancedText.swf|priority=1'
  );
  */

  $variables['flashplayerProperties'] = _media_mpx_setFlashPlayerProperties($params);
  $variables['behaviourProperties']  = _media_mpx_setBehaviourProperties($params);
  $variables['colorProperties']     = _media_mpx_setColorProperties($params);

  $variables['width'] = 640;
  $variables['height'] = 396;

  $variables['params'] = $params;

  // Pass the settings to replace the object tag with an iframe.
  $settings = array(
    'media_mpx' => array(
      $variables['wrapper_id'] => array(
        'width' => $variables['width'],
        'height' => $variables['height'],
        'video_id' => $video->id,
        'fullscreen' => $variables['fullscreen'],
        'id' => $variables['wrapper_id'] .'_iframe',
      ),
    ),
  );


  /*
    // Set the version of the mpx api player.
    if ($variables['version']) {
      $variables['query']['version'] = $variables['version'];
      // Note that the fs variable defaults to 1 with the AS3 player.
      if (!$variables['fs']) {
        $variables['query']['fs'] = 0;
      }
    }
    else if ($variables['fs']) {
      // Note that the fs variable defaults to 0 with the AS2 player.
      $variables['query']['fs'] = 1;
    }

    // These options default to 0.
    foreach (array('autoplay', 'hd') as $variable) {
      if ($variables[$variable]) {
        $variables['query'][$variable] = 1;
      }
    }

    if (!$variables['related']) {
      $variables['query']['rel'] = 0;
    }


    // Ensure that we pass the required query variables to the Iframe settings.
    if (!empty($variables['query'])) {
      $settings['media_mpx'][$variables['wrapper_id']]['options'] = $variables['query'];
    }

    drupal_add_js($settings, 'setting');
    drupal_add_js(drupal_get_path('module', 'media_mpx') . '/js/media_mpx.js');
    drupal_add_css(drupal_get_path('module', 'media_mpx') . '/css/media_mpx.css');
    drupal_add_js(drupal_get_path('module', 'media_mpx') . '/js/flash_detect_min.js');

    // The chromeless player requires a different url.
    if ($variables['chromeless']) {
      $variables['url_api'] = 'apiplayer';
      $variables['query']['video_id'] = $variables['video_id'];
    }
    else {
      $variables['url_api'] = 'v/' . $variables['video_id'];
    }

    $variables['url'] = url('http://www.youtube.com/' . $variables['url_api'], array('query' => $variables['query'], 'external' => TRUE, 'https' => TRUE));

    // For users with JavaScript, these object and embed tags will be replaced
    // by an iframe, so that we can support users without Flash.

    $variables['output'] = <<<OUTPUT
      <object width="{$variables['width']}" height="{$variables['height']}">
        <param name="movie" value="{$variables['url']}"></param>
        <param name="allowFullScreen" value="{$variables['fullscreen']}"></param>
        <param name="wmode" value="{$variables['wmode']}" />
        <embed src="{$variables['url']}" type="application/x-shockwave-flash" width="{$variables['width']}" height="{$variables['height']}" allowfullscreen="{$variables['fullscreen']}" wmode="{$variables['wmode']}"></embed>
      </object>
  OUTPUT;
  */

}

function theme_media_mpx_field_formatter_styles($variables) {
  debug_print_backtrace();
  $element = $variables['element'];
  $style = $variables['style'];
  $variables['file'] = $element['#item'];
  $variables['uri'] = $variables['file']['uri'];
  $variables['style_name'] = $style['name'];
  return theme('media_mpx_embed', $variables);
}

/**
 * Preview for Styles UI.
 */
function theme_media_mpx_preview_style($variables) {
  debug_print_backtrace();
  $variables['uri'] = media_mpx_variable_get('preview_uri');
  $variables['field_type'] = 'file';
  $variables['object'] = file_uri_to_object($variables['uri']);

  return theme('styles', $variables);
}

/**
 * NOTE: Deprecated with Styles version 2.
 */
function theme_media_mpx_styles($variables) {
  debug_print_backtrace();
  $style = $variables['style'];
  $variables['file'] = $variables['object'];
  $variables['uri'] = $variables['object']->uri;
  $variables['style_name'] = $style['name'];
  return theme('media_mpx_embed', $variables);
}

/**
 * @todo: get this working
 *
 * This code is for embedding videos in WYSIYWG areas, not currently working.
 * NOTE: Deprecated with Styles version 2.
 */
function theme_media_mpx_embed($variables) {
  $preset_name = $variables['preset_name'];
  $preset = styles_containers_available_styles('file', 'media_mpx', $preset_name);
  $output = '';
  if (!empty($preset)) {
    // Build the URL for display.
    $uri = $variables['uri'];
    $wrapper = file_stream_wrapper_get_instance_by_uri($uri);
    $parts = $wrapper->get_parameters();

    $fullscreen_value = $autoplay = 'false';
    $in_browser = $thumbnail = FALSE;

    foreach ($preset['effects'] as $effect) {
      switch ($effect['name']) {
        case 'autoplay':
          $autoplay = $effect['data']['autoplay'] ? 'true' : 'false';
          break;
        case 'resize':
          $width = $effect['data']['width'];
          $height = $effect['data']['height'];
          break;
        case 'fullscreen':
          $fullscreen_value = $effect['data']['fullscreen'] ? 'true' : 'false';
          break;
        case 'thumbnail':
          $thumbnail = $effect['data']['thumbnail'];
      }
    }
    if (isset($variables['object']->override)) {
      $override = $variables['object']->override;
      if (isset($override['width'])) {
        $width = $override['width'];
      }
      if (isset($override['height'])) {
        $height = $override['height'];
      }
      if (isset($override['wysiwyg'])) {
        $thumbnail = TRUE;
      }
      if (isset($override['browser']) && $override['browser']) {
        $in_browser = TRUE;
        $thumbnail = TRUE;
      }
    }
    $width = isset($width) ? $width : media_mpx_variable_get('width');
    $height = isset($height) ? $height : media_mpx_variable_get('height');
    $video_id = check_plain($parts['v']);
    if ($thumbnail) {
      // @todo Clean this up.
      $image_variables = array(
        'path' => $wrapper->getOriginalThumbnailPath(),
        'alt' => $variables['alt'],
        'title' => $variables['title'],
        'getsize' => FALSE,
      );
      if (isset($preset['image_style'])) {
        $image_variables['path'] = $wrapper->getLocalThumbnailPath();
        $image_variables['style_name'] = $preset['image_style'];
        $output = theme('image_style', $image_variables);
      }
      else {
        // We need to add this style attribute here so that it doesn't get lost
        // If you resize a video in a node, save it, edit it, but don't adjust
        // the sizing of the video while editing, the size will revert to the
        // default.  Adding the specific size here retains the original resizing
        $WYSIWYG = isset($variables['object']->override['style']) ? $variables['object']->override['style'] : '';
        $image_variables['attributes'] = array('width' => $width, 'height' => $height, 'style' => $WYSIWYG);
        $output = theme('image', $image_variables);
      }
      if ($in_browser) {
        // Add an overlay that says 'YouTube' to media library browser thumbnails.
        $output .= '<span />';
      }
    }
    else {
      $output = theme('media_mpx_video', array('uri' => $uri, 'width' => $width, 'height' => $height, 'autoplay' => ($autoplay == 'true' ? TRUE : NULL), 'fullscreen' => ($fullscreen_value == 'true' ? TRUE : NULL)));
    }
  }
  return $output;
}

function _media_mpx_setFlashPlayerProperties($params) {
  $content = '';

  // set the player fp.bgcolor
  if (isset($params['fpBgcolor']) && !empty($params['fpBgcolor'])) {
    $content .= 'player.fp.bgcolor = "' . $params['fpBgcolor'] . '";';
    $content .= "\n";
  }

  // set the player fp.wmode
  if (isset($params['fpWmode']) && !empty($params['fpWmode'])) {
    $content .= 'player.fp.wmode = "' . $params['fpWmode'] . '";';
  } else {
    $content .= 'player.fp.wmode = "opaque";';
  }
  $content .= "\n";

  // set the player fp.allowFullScreen
  if (isset($params['fpAllowFullScreen']) && !empty($params['fpAllowFullScreen'])) {
    $content .= 'player.fp.allowFullScreen = "' . $params['fpAllowFullScreen'] . '";';
  } else {
    $content .= 'player.fp.allowFullScreen="true";';
  }
  $content .= "\n";

  // set the scale
  if (isset($params['scale']) && !empty($params['scale'])) {
    $content .= 'player.fp.scale = "' . $params['scale'] . '";';
  } else {
    $content .= 'player.fp.scale="scale";';
  }
  $content .= "\n";
  $content .= 'player.fa.scale="scale";';

  // set the scale
  if (isset($params['bgcolor']) && !empty($params['bgcolor'])) {
    $content .= 'player.fp.bgcolor = "' . $params['bgcolor'] . '";';
  } else {
    $content .= 'player.fp.bgcolor="#2a2a2a";';
  }
  $content .= "\n";

  // set the scale
  if (isset($params['salign']) && !empty($params['salign'])) {
    $content .= 'player.fp.salign = "' . $params['salign'] . '";';
  } else {
    $content .= 'player.fp.salign="tl";';
  }
  $content .= "\n";

  return $content;
}

function _media_mpx_setBehaviourProperties($params) {
  $content = '';

  // set the player logLevel

  $content .= 'player.logLevel = "none";';
  $content .= "\n";

  // set allowFullScreen param
  if (isset($params['allowFullScreen']) && !empty($params['allowFullScreen'])) {
    $content .= 'player.allowFullScreen = "' . $params['allowFullScreen'] . '";';
  } else {
    $content .= 'player.allowFullScreen = "true";';
  }
  $content .= "\n";

  if (isset($params['autoPlay']) && $params['autoPlay'] == false) {
    $content .= 'player.autoPlay = "false";';
  } else {
    $content .= 'player.autoPlay = "true";';
  }

  //$content .= 'player.autoLoad = "true";';
  //$content .= 'player.linkUrl = "http://www.sbs.com.au";';
  //$content .= 'player.showControls = "true";';

  // set any plugins
  // e.g. player.plugin0="type=adcomponent|URL=../../pdk/swf/SMIL.swf|priority=3";
  if (isset($params['plugins']) && !empty($params['plugins'])) {
    foreach ($params['plugins'] as $key => $plugin) {
      $pluginID = "plugin{$key}";
      $content .= 'player.' . $pluginID . ' = "' . $plugin . '";';
      $content .= "\n";
    }
  }

  // set the playerUrl
  if (isset($params['playerURL']) && !empty($params['playerURL'])) {
    $content .= 'player.playerURL = "' . $params['playerURL'] . '";';
    $content .= "\n";
  }

  // set overlayImageURL
  if (isset($params['overlayImageURL']) && !empty($params['overlayImageURL'])) {
    $content .= 'player.overlayImageURL = "' . addslashes($params['overlayImageURL']) . '";';
    $content .= "\n";
  }

  // set previewImageURL
  if (isset($params['previewImageURL']) && !empty($params['previewImageURL'])) {
    $content .= 'player.previewImageURL = "' . addslashes($params['previewImageURL']) . '";';
    $content .= "\n";
  }

  // set previewImageBlurThreshold - number value (0, 1 or in-between e.g. 0.75)
  if (isset($params['previewImageBlurThreshold'])) {
    $content .= 'player.previewImageBlurThreshold = "' . addslashes($params['previewImageBlurThreshold']) . '";';
    $content .= "\n";
  }

  // set email service url
  // e.g. player.emailServiceURL = "http://player.theplatform.com/ps/mail";
  if (isset($params['emailServiceURL']) && !empty($params['emailServiceURL'])) {
    $content .= 'player.emailServiceURL = "' . $params['emailServiceURL'] . '";';
    $content .= "\n";
  }

  // set embedded player html
  if (isset($params['embeddedPlayerHTML']) && !empty($params['embeddedPlayerHTML'])) {
    $content .= 'player.embeddedPlayerHTML = "' . addslashes($params['embeddedPlayerHTML']) . '";';
    $content .= "\n";
  }

  // set the layoutUrl (sets up player controls, etc...)
  if (isset($params['layoutUrl']) && !empty($params['layoutUrl'])) {
    $content .= 'player.layoutUrl = "' . $params['layoutUrl'] . '";';
    $content .= "\n";
  }

  // set the video scaling method
  if (isset($params['videoScalingMethod']) && !empty($params['videoScalingMethod'])) {
    $content .= 'player.videoScalingMethod = "' . $params['videoScalingMethod'] . '";';
    $content .= "\n";
  }

  // set the related items url feed
  if (isset($params['relatedItemsURL']) && !empty($params['relatedItemsURL'])) {
    $content .= 'player.relatedItemsURL = "' . $params['relatedItemsURL'] . '";';
    $content .= "\n";
  }

  // set the endCard
  if (isset($params['endCard']) && !empty($params['endCard'])) {
    $content .= 'player.endCard = "' . $params['endCard'] . '";';
    $content .= "\n";
  }

  // set the expandVideo
  if (isset($params['expandVideo']) && !empty($params['expandVideo'])) {
    $content .= 'player.expandVideo = "' . $params['expandVideo'] . '";';
    $content .= "\n";
  }

  // set the sharingSiteIds
  if (isset($params['sharingSiteIds']) && !empty($params['sharingSiteIds'])) {
    $content .= 'player.sharingSiteIds = "' . $params['sharingSiteIds'] . '";';
    $content .= "\n";
  }

  return $content;
}

function _media_mpx_setColorProperties($params) {
  $content = '';

  // set the player backgroundColor
  if (isset($params['backgroundColor']) && !empty($params['backgroundColor'])) {
    $content .= 'player.backgroundColor = "' . $params['backgroundColor'] . '";';
    $content .= "\n";
  }

  // set the player control backgroundColor
  if (isset($params['controlBackgroundColor']) && !empty($params['controlBackgroundColor'])) {
    $content .= 'player.controlBackgroundColor = "' . $params['controlBackgroundColor'] . '";';
    $content .= "\n";
  }

  // set the player control Color
  if (isset($params['controlColor']) && !empty($params['controlColor'])) {
    $content .= 'player.controlColor = "' . $params['controlColor'] . '";';
    $content .= "\n";
  }

  // set the player control FrameColor
  if (isset($params['controlFrameColor']) && !empty($params['controlFrameColor'])) {
    $content .= 'player.controlFrameColor = "' . $params['controlFrameColor'] . '";';
    $content .= "\n";
  }

  // set the player control HoverColor
  if (isset($params['controlHoverColor']) && !empty($params['controlHoverColor'])) {
    $content .= 'player.controlHoverColor = "' . $params['controlHoverColor'] . '";';
    $content .= "\n";
  }

  // set the player control SelectedColor
  if (isset($params['controlSelectedColor']) && !empty($params['controlSelectedColor'])) {
    $content .= 'player.controlSelectedColor = "' . $params['controlSelectedColor'] . '";';
    $content .= "\n";
  }

  // set the player frameColor
  if (isset($params['frameColor']) && !empty($params['frameColor'])) {
    $content .= 'player.frameColor = "' . $params['frameColor'] . '";';
    $content .= "\n";
  }

  // set the player page Background Color
  if (isset($params['pageBackgroundColor']) && !empty($params['pageBackgroundColor'])) {
    $content .= 'player.pageBackgroundColor = "' . $params['pageBackgroundColor'] . '";';
    $content .= "\n";
  }

  // set the player play Progress Color
  if (isset($params['playProgressColor']) && !empty($params['playProgressColor'])) {
    $content .= 'player.playProgressColor = "' . $params['playProgressColor'] . '";';
    $content .= "\n";
  }

  // set the player scrubber color
  if (isset($params['scrubberColor']) && !empty($params['scrubberColor'])) {
    $content .= 'player.scrubberColor = "' . $params['scrubberColor'] . '";';
    $content .= "\n";
  }

  // set the player scrubber frame color
  if (isset($params['scrubberFrameColor']) && !empty($params['scrubberFrameColor'])) {
    $content .= 'player.scrubberFrameColor = "' . $params['scrubberFrameColor'] . '";';
    $content .= "\n";
  }

  // set the player scrub track color
  if (isset($params['scrubTrackColor']) && !empty($params['scrubTrackColor'])) {
    $content .= 'player.scrubTrackColor = "' . $params['scrubTrackColor'] . '";';
    $content .= "\n";
  }

  // set the player text background color
  if (isset($params['textBackgroundColor']) && !empty($params['textBackgroundColor'])) {
    $content .= 'player.textBackgroundColor = "' . $params['textBackgroundColor'] . '";';
    $content .= "\n";
  }

  // set the player text color
  if (isset($params['textColor']) && !empty($params['textColor'])) {
    $content .= 'player.textColor = "' . $params['textColor'] . '";';
    $content .= "\n";
  }

  // set the player load progress color
  if (isset($params['loadProgressColor']) && !empty($params['loadProgressColor'])) {
    $content .= 'player.loadProgressColor = "' . $params['loadProgressColor'] . '";';
    $content .= "\n";
  }

  return $content;
}
