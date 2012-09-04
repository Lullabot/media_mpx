<?php

/**
 * @file media_mpx/includes/themes/media-mpx-video.tpl.php
 *
 * Template file for theme('media_mpx_video').
 *
 * Variables available:
 *  $uri - The uri to the mpx video, such as mpx://v/xsy7x8c9.
 *  $video_id - The unique identifier of the mpx video.
 *  $width - The width to render.
 *  $height - The height to render.
 *  $autoplay - If TRUE, then start the player automatically when displaying.
 *  $fullscreen - Whether to allow fullscreen playback.
 *  $release_url - URL of the media.
 *  $flashplayerProperties
 *  $behaviourProperties
 *  $colorProperties
 *
 * Note that we set the width & height of the outer wrapper manually so that
 * the JS will respect that when resizing later.
 */
?>
<div class="media-mpx-outer-wrapper" id="media-mpx-<?php print $id; ?>" style="width: <?php print $width; ?>px; height: <?php print $height; ?>px;">
  <div class="media-mpx-preview-wrapper" id="<?php print $wrapper_id; ?>">
    <?php //print $output; ?>

    <script type="text/javascript">
      var player = new Player("<?php print $wrapper_id; ?>");
      <?php
      echo $flashplayerProperties . PHP_EOL;
      echo $behaviourProperties . PHP_EOL;
      echo $colorProperties . PHP_EOL;

      ?>
      player.releaseUrl = "<?php print $release_url; ?>";
      player.bind();
    </script>


    <style type="text/css">
      #<?php echo $wrapper_id; ?> {
        float: left;
        height: <?php print $height; ?>px;
        width: <?php print $width; ?>px;
      }
    </style>

  </div>
</div>
