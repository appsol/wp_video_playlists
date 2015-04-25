<?php
/**
 * Plugin Name: WP Video Playlists
 * Plugin URI: http://www.appropriatesolutions.co.uk/
 * Description: Displays videos from YouTube and other providers playlists or channels
 * Version: 0.8.0
 * Author: Stuart Laverick
 * Author URI: http://www.appropriatesolutions.co.uk/
 * Text Domain: Optional. wp_video_playlists
 * License: GPL2
 *
 * @package wp_video_playlists
 */
/*  Copyright 2015  Stuart Laverick  (email : stuart@appropriatesolutions.co.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
namespace VideoPlaylists;

defined('ABSPATH') or die( 'No script kiddies please!' );

require_once 'vendor/autoload.php';
require_once 'videoplaylist_widget.php';
require_once 'videoplaylists_options.php';

class VideoPlaylists
{

    /**
     * Singleton class instance
     *
     * @var object VideoPlaylists
     **/
    private static $instance = null;

    /**
     * Holds the lst error message from the API or empty if none
     *
     * @var string
     **/
    public $lastError;

    /**
     * Constructor for VideoPlaylists
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function __construct()
    {
        add_action("widgets_init", [$this, 'register']);
        add_shortcode('video_playlist', [$this, 'shortcodeHandler']);

        if (is_admin()) {
            $optionsPage = new VideoPlaylistsOptions();
        } else {
            add_action( 'wp_enqueue_scripts', [$this, 'actionEnqueueAssets']);
        }
    }

    /**
     * Creates or returns an instance of this class
     *
     * @return A single instance of this class
     * @author Stuart Laverick
     **/
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Register the Widget
     * The sidebar occurs within the list of posts, allowing 
     * placement of adverts, promotions and similar
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function register()
    {
        register_widget('VideoPlaylists\VideoPlaylistWidget');
    }

    /**
     * Load any scripts and styles needed in the page
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function actionEnqueueAssets()
    {
        $options = get_option( 'videoplaylists' );
        if (!empty($options['load_css'])) {
            wp_register_style('wp-video-playlists', plugin_dir_url(__FILE__) . 'assets/css/player.css');
            wp_enqueue_style('wp-video-playlists');
        }

        if (!empty($options['load_js'])) {
            wp_enqueue_script('jcarousel', 'https://cdnjs.cloudflare.com/ajax/libs/jcarousel/0.3.3/jquery.jcarousel.min.js', ['jquery'], '0.3.3', true);
            wp_enqueue_script('wp-video-playlists', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jcarousel'], '0.3.0', true);
        }
    }

    /**
     * Handler for shortcode calls
     *
     * Options:
     * youtube_user => String: the YouTube Channel user name
     * playlist => String: the YouTube Playlist ID
     * height => Integer: the initial height of the player
     * width => Integer: the initial width of the player
     *
     * @return string HTML of the player
     * @author Stuart Laverick
     **/
    public function shortcodeHandler($attributes)
    {
        extract(shortcode_atts(array(
            'youtube_user' => 'blank',
            'playlist' => 'blank',
            'height' => 700,
            'width' => 426
                        ), $attributes));

        if ($youtube_user != 'blank') {
            return $this->channelPage($youtube_user, $playlist, $height, $width);
        }

        if ($playlist == 'blank'){
            return $this->playlistPage($playlist, $height, $width);
        }

        return false;
    }

    /**
     * Generate the HTML for the player container
     *
     * @return String The html for the player container
     * @author Stuart Laverick
     **/
    public function getPlayerHtml($player_id, $video, $height, $width, $description = false, $start = false)
    {
        ob_start();
        ?>
        <div class="video-player">
            <?php echo $this->getYouTubePlayerHtml($player_id, $video, $height, $width); ?>
        </div>
        <?php if ($description): ?>
            <div class="video-description">
                <p><?php echo $video['snippet']['description']; ?></p>
            </div>
        <?php endif;

        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Generate the HTML for the iframe player
     *
     * @return String The html to show a YouTube player
     * @author Stuart Laverick
     **/
    public function getYouTubePlayerHtml($player_id, $video, $height, $width)
    {
        $html = ['<iframe id="video_container_' . $player_id . '" class="video-container"'];
        $html[] = 'src="http://www.youtube.com/embed/' . $video['videoId'] . '?wmode=transparent&origin=' . get_site_url() . '"';
        $html[] = 'frameborder="0"';
        $html[] = 'enablejsapi=1';
        $html[] = 'autohide=1';
        $html[] = 'width="' . $width . '"';
        $html[] = 'height="' . $height . '"></iframe>';
        return implode(' ', $html);
    }

    public function playlistPage($playlist, $height, $width)
    {
        global $post;
        $start_video = isset($_GET['start']) ?
                json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/videos/' . $_GET['start'] . '?v=2&alt=jsonc')) : null;
        // Use the cached transient if available
        $output = $start_video ? false : get_transient('youtube_page_' . $post->ID);
        // No transient so build new cache
        if ($output == false) {
            $playlist = json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/playlists/' . $playlist . '?v=2&orderby=position&max-results=5&alt=jsonc'));

            $latest_video = ($start_video) ? $start_video->data : $playlist->data->items[0]->video;
            // Load all the display content into a buffer
            ob_start();
            ?>
            <div id="video_page">
                <?php $this->showPlayer($post->ID, $latest_video, $height, $width, true, !empty($start_video)); ?>
                <div id="playlists" class="playlists accordion">
                    <?php echo $this->showPlaylist($playlist); ?>
                </div>
            </div>
            <?php
            $output = ob_get_contents();
            ob_end_clean();
            set_transient('youtube_page_' . $post->ID, $output, 60 * 60);
        }
        return $output;
    }

    public function channelPage($user, $playlist, $height, $width)
    {
        global $post;
        $start_video = isset($_GET['start']) ?
                json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/videos/' . $_GET['start'] . '?v=2&alt=jsonc')) : null;
        $video_feed = json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/users/' . $user . '/uploads?&v=2&max-results=1&alt=jsonc'));
        
        $playlists_feed = json_decode(file_get_contents('https://gdata.youtube.com/feeds/api/users/' . $user . '/playlists?&v=2&alt=jsonc'));
        
        $latest_video = ($start_video) ? $start_video->data : $video_feed->data->items[0]->video;
        $playlists = $playlists_feed->data->items;

        ob_start();
        ?>
        <div id="video_page">
            <?php $this->showPlayer($post->ID, $latest_video, $height, $width, true, !empty($start_video)); ?>
            <div id="playlists" class="playlists accordion">
                <?php foreach ($playlists as $playlist): ?>
                    <?php echo $this->showPlaylist($playlist); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }



    public function showPlaylist($playlist)
    {
        if (!isset($playlist->data->items)) {
            $playlist = json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/playlists/' . $playlist->id . '?v=2&orderby=position&alt=jsonc'));
        }
        $videos = $playlist->data->items;
        $playlist = $playlist->data;

        ob_start();
        ?>
        <div class="playlist accordion-group">
            <div class="row-fluid playlist-inner accordion-heading">
                <div class="span6 playlist-meta">
                    <h3><?php echo $playlist->title; ?></h3>
                    <p><?php echo $playlist->description ?></p>
                </div>
                <div class="span6 latest-video-meta">
                    <h3><?php _e('Latest Episode'); ?></h3>
                    <div class="media main-panel">
                        <div class="pull-left thumb-container">
                            <span id="<?php echo $videos[0]->video->id; ?>" class="video-thumbnail video-link"><span></span>
                                <img src="<?php echo $videos[0]->video->thumbnail->sqDefault; ?>" alt="<?php echo $videos[$offset]->video->title; ?>"/>
                            </span></div>
                        <div class="video-meta media-body">
                            <h4 class="episode-title media-heading"><?php echo $videos[0]->video->title; ?></h4>
                            <p class="duration"><?php
                                $mins = $videos[0]->video->duration / 60;
                                $seconds = $videos[0]->video->duration % 60;
                                $duration = sprintf('%02d', $mins) . ':' . sprintf('%02d', $seconds);
                                echo $duration;
                                ?></p>
                            <?php if (count($videos) > 1): ?>
                                <a class="accordion-toggle see-more btn-small" data-toggle="collapse" data-parent="#playlists" href="#playlist_<?php echo $playlist->id; ?>"><span>Show Previous Episodes</span></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="playlist_<?php echo $playlist->id; ?>" class="row-fluid previously-on-container accordion-body collapse">
                <ul class="videos-list thumbnails accordion-inner">
                    <?php
                    for ($offset = 1; $offset < count($videos); $offset++) {
                        $mins = $videos[$offset]->video->duration / 60;
                        $seconds = $videos[$offset]->video->duration % 60;
                        $duration = sprintf('%02d', $mins) . ':' . sprintf('%02d', $seconds);
                        ?>
                        <li class="span6 <?php echo ($offset % 2 == 0 ? 'even' : 'odd'); ?>">
                            <div class="thumbnail row-fluid">
                                <div id="<?php echo $videos[$offset]->video->id; ?>" class="video-thumbnail video-link span6"><span><span class="duration"><?php echo $duration; ?></span></span>
                                    <img src="<?php echo $videos[$offset]->video->thumbnail->sqDefault; ?>" alt="<?php echo $videos[$offset]->video->title; ?>"/>
                                </div>
                                <div class="video-meta span6">
                                    <h4 class="episode-title"><?php echo $videos[$offset]->video->title; ?></h4>
                                    <button type="button" id="<?php echo $videos[$offset]->video->id; ?>" class="btn-small video-link play-button">Play</button>
                                </div>
                            </div>
                        </li>
                    <?php } ?></ul>
            </div>
        </div>
        <?php

        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Returns the html list of episodes as linked images
     *
     * @return String the html of the list
     * @author Stuart Laverick
     **/
    public function getEpisodeListHtml($items, $max = 5)
    {
        $max = $max > count($items)? count($items) : $max;
        $html = ['<ul class="episode-list">'];
        for ($i = 0; $i < $max; $i++){
            $html[] = '<li class="video-episode">';
            $html[] = $this->getYouTubeVideoItemLink($items[$i]);
            $html[] = '</li>';
        }
        $html[] = '</ul>';
        return implode("\n", $html);
    }

    /**
     * Returns an image link for the YouTube video
     *
     * @return String the html for the video link
     * @author Stuart Laverick
     **/
    public function getYouTubeVideoItemLink($item, $thumbnail = 'default', $titleMaxWords = 12)
    {
        $html = ['<a id="' . $item['videoId'] . '" data-youtube-id="' . $item['videoId'] . '" href="#' . $item['videoId'] . '">'];
        $html[] = '<img src="' . $item['snippet']['thumbnails'][$thumbnail]['url'] . '" alt="' . $item['snippet']['title'] . '"/>';
        $html[] = '<span class="video-title">' . wp_trim_words($item['snippet']['title'], $titleMaxWords) . '</span>';
        $html[] = '</a>';
        return implode("\n", $html);
    }

    /**
     * Returns an authenticated Google API client
     *
     * @return Google_Client the authenticated client
     * @author Stuart Laverick
     **/
    private function getYouTubeClient()
    {
        $options = get_option( 'videoplaylists' );
        
        try {
            if (!isset($options['youtube_simple_key'])) {
                throw new \Exception("Google API Client Key missing", 1);
            }
            $client = new \Google_Client();
            $client->setDeveloperKey($options['youtube_simple_key']);
            $youtube = new \Google_Service_YouTube($client);
        }
        catch(\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log($this->lastError);
            return false;
        }
        return $youtube;
    }

    /**
     * Fetches the YouTube channel ID for a given user
     *
     * @param youtubeUser String YouTube user name
     * @return String YouTube Channel ID
     * @author Stuart Laverick
     **/
    public function getYouTubeUserChannelId($youtubeUser)
    {
        if(!$channelList = $this->getTransient('listChannels-forUsername-' . $youtubeUser)) {
            try {
                $yt = $this->getYouTubeClient();
                $channelList = $yt->channels->listChannels('id',['forUsername' => $youtubeUser]);
            }
            catch (\Exception $e) {
                $this->lastError = $e->getMessage();
                error_log($this->lastError);
                return false;
            }

            $this->setTransient('listChannels-forUsername-' . $youtubeUser, $channelList);
        }
        $channel = array_pop($channelList['items']);

        return $channel? $channel['id'] : false;
    }

    /**
     * Fetches a collection of playlists for a given channel
     *
     * @param channelId String YouTube Channel ID
     * @return Array Collection of YouTube Playlist Items
     * @author Stuart Laverick
     **/
    public function getYouTubeChannelPlaylists($channelId)
    {
        if(!$playList = $this->getTransient('listPlaylists-channelId-' . $channelId)) {
            try {
                $yt = $this->getYouTubeClient();
                $params = ['channelId' => $channelId, 'maxResults' => 50];
                $playList = $yt->playlists->listPlaylists('id,snippet', $params);
            }
            catch (\Exception $e) {
                $this->lastError = $e->getMessage();
                error_log($this->lastError);
                return false;
            }
            $this->setTransient('listPlaylists-channelId-' . $channelId, $playList);
        }

        return $playList['items'];
    }

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function getYouTubeChannelVideos($channelId, $maxResults = 10)
    {
        if ($maxResults > 50) {
            $maxResults = 50;
        }
        if(!$channelItems = $this->getTransient('listSearch-channelId-' . $channelId . '-max-' . $maxResults)) {
            try {
                $yt = $this->getYouTubeClient();
                $params = ['channelId' => $channelId, 'type' => 'video', 'order' => 'date', 'maxResults' => $maxResults];
                $channelItems = $yt->search->listSearch('id,snippet', $params);
            }
            catch (\Exception $e) {
                $this->lastError = $e->getMessage();
                error_log($this->lastError);
                return false;
            }
            $this->setTransient('listSearch-channelId-' . $channelId . '-max-' . $maxResults, $channelItems);
        }

        // Return the list in a consistent format
        $videos = [];
        foreach ($channelItems['items'] as $key => $item) {
            $videos[$key] = ['videoId' => $item['id']['videoId'], 'snippet' => $item['snippet']];
        }

        return $videos;
    }

    /**
     * Fetches a collection of items for a given playlist
     *
     * @param playlistId String YouTube Playlist ID
     * @param maxResults Int Maximum number of results to fetch
     * @return Array Collection of YouTube Video Items
     * @author Stuart Laverick
     **/
    public function getYouTubePlaylistVideos($playlistId, $maxResults = 10)
    {
        if ($maxResults > 50) {
            $maxResults = 50;
        }
        if(!$playlistItems = $this->getTransient('listPlaylistItems-playlistId-' . $playlistId . '-max-' . $maxResults)) {
            try {
                $yt = $this->getYouTubeClient();
                $params = ['playlistId' => $playlistId, 'maxResults' => $maxResults];
                $playlistItems = $yt->playlistItems->listPlaylistItems('id,snippet', $params);
            }
            catch (\Exception $e) {
                $this->lastError = $e->getMessage();
                error_log($this->lastError);
                return false;
            }
            $this->setTransient('listPlaylistItems-playlistId-' . $playlistId . '-max-' . $maxResults, $playlistItems);
        }
        // Return the list in a consistent format
        $videos = [];
        foreach ($playlistItems['items'] as $key => $item) {
            $videos[$key] = ['videoId' => $item['snippet']['resourceId']['videoId'], 'snippet' => $item['snippet']];
        }

        return $videos;
    }

    /**
     * Returns the transient for the given name, or false if not exists
     *
     * @return Mixed the transient data or false
     * @author Stuart Laverick
     **/
    private function getTransient($name)
    {
        $this->lastError = '';
        return false;
        return get_transient(substr($name, 0, 45));
    }

    /**
     * Sets the transient for the given name
     *
     * @return Mixed the transient data or false
     * @author Stuart Laverick
     **/
    private function setTransient($name, $data)
    {
        return set_transient(substr($name, 0, 45), $data, 60 * 60);
    }

    /**
     * Delete the transients related to a given widget
     *
     * @return true
     * @author Stuart Laverick
     **/
    public function deleteTransients($options)
    {
        if (!empty($options['channel'])) {
            delete_transient(substr('listChannels-forUsername-' . $options['channel'], 0, 45));
        }
        if (!empty($options['channel_id'])) {
            delete_transient(substr('listPlaylists-channelId-' . $options['channel_id'], 0, 45));
            delete_transient(substr('listSearch-channelId-' . $options['channel_id'] . '-max-' . ($options['thumbs'] + 1), 0, 45));
        }
        if (!empty($options['playlist'])) {
            delete_transient(substr('listPlaylistItems-playlistId-' . $options['playlist'] . '-max-' . ($options['thumbs'] + 1), 0, 45));
        }
        return true;
    }
}

$videoplaylists = VideoPlaylists::getInstance();
