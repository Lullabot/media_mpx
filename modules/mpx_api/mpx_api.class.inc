<?php

class MpxApi {

  private $basepath, $hostname, $allfeed, $mpxid;

  function __construct($parameters = array()){
    $this->basepath = $parameters['basepath'];
    $this->hostname = $parameters['hostname'];
    $this->allfeed = $parameters['allfeed'];
    $this->mpxid = $parameters['mpxid'];
  }

  function getVideoByID($id, $options = array(), $refresh = FALSE){
    if(($cache = cache_get('mpxApi:id:'.$id)) && ($refresh == false)){
      return new MpxVideo($cache->data);
    }
    else{
      $response = drupal_http_request(url('http://'.$this->hostname.'/'.$this->basepath.''.$this->mpxid.'/'.$this->allfeed.'/'.$id, array('query' => $options)));

      $data = json_decode($response->data);

      $video = new MpxVideo($data);

      // Cache the raw JSON data, we may do more dynamic after-request constructs with the MpxVideo object in the future.
      cache_set('mpxApi:id:'.$video->id, $data);
      return $video;
    }

  }

  function getVideos($ids, $options = array(), $refresh = FALSE){
    if(!is_array($ids) && is_numeric($ids)){
      return $this->getVideoByID($ids, $options, $refresh);
    }

    // TODO perhaps remove $ids from array where we have them in cache, before we send the request
    // Not sure what speed improvement this would actually give, the API is pretty quick!

    // MPX API allows a multi ID request for efficiency. See the implode().
    $response = drupal_http_request(url('http://'.$this->hostname.'/'.$this->basepath.'/video_feed/f/'.$this->mpxid.'/'.$this->allfeed.'/'.implode(',', $ids), array('query' => $options)));


    return $this->_processVideosResult($response);

  }

  // Must have valid 'q' parameter in $options array.

  function searchVideos($options){
    $response = drupal_http_request(url('http://'.$this->hostname.'/'.$this->basepath.'/video_feed/f/'.$this->mpxid.'/'.$this->allfeed, array('query' => $options)));

    return $this->_processVideosResult($response);
  }

  protected function _processVideosResult($response){
    $videos = array();

    $data = json_decode($response->data);
    foreach($data->entries AS $videoData){
      $videoObj = new MpxVideo($videoData);
      $videos[] = $videoObj;

      // Cache the raw JSON data, we may do more dynamic after-request constructs with the MpxVideo object in the future.
      cache_set('mpxApi:id:'.$videoObj->id, $videoData);
    }
    return $videos;
  }

}


class MpxVideo {

  public $id;
  public $idUrl;

  public $defaultThumbnail, $pubDate, $availableDate, $expirationDate, $description, $title, $author;
  public $programName, $thumbnails, $content, $metrics, $categories;

  public function __construct($data){
    $this->id = $this->parseVideoId($data->id);
    $this->idUrl = $data->id;

    $this->categories = $data->{'media$categories'};
    $this->content = $data->{'media$content'};
    $this->thumbnails = $data->{'media$thumbnails'};
    $this->metrics = $data->{'plmedia$metrics'};
    $this->defaultThumbnail = $data->{'plmedia$defaultThumbnailUrl'};

    //print_r($data);
    if(isset($data->{'pl1$programName'})){
      $this->programName = $data->{'pl1$programName'};
    }

    $this->pubDate = $data->pubDate;
    $this->title = $data->title;
    $this->author = $data->author;
    $this->description = $data->description;
    $this->availableDate = $data->{'media$availableDate'};
    $this->expirationDate = $data->{'media$expirationDate'};
  }

  public function parseVideoId($idUrl){
    $matches = array();
    preg_match('@Media/([0-9]+)@', $idUrl, $matches);
    return $matches[1];
  }

  public function getReleaseUrl(){
    $currentBitrate = 0;
    $releaseUrl = '';
    if (isset($this->content) && !empty($this->content)) {

      // set the releaseUrl to use the one with the highest bitrate...
      foreach ($this->content as $key => $media) {
        if ($currentBitrate < $media->{'plfile$bitrate'}) {

          // to get the multi-bitrate controls to come up, we need to a 'mbr=true' flag to the releaseUrl...
          // temporarily taken out in case it conflicts with the switch to HTML5 compliant player...
          $releaseUrl     = $media->{'plfile$url'};
          $currentBitrate = $media->{'plfile$bitrate'};

        }
      }
      return $releaseUrl;
    }
  }


}
