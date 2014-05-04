<?php
/*
  Plugin Name: Video Player
  Plugin URI: http://www.appropriatesolutions.co.uk/
  Description: Displays the latest video and optionally recent videos from a playlist.
  Author: Tim Ward and Stuart Laverick
  Version: 0.7
  Author URI: http://www.appropriatesolutions.co.uk/
 */

class appsolVideoPlayer extends WP_Widget {
    
    public static $title_max_words = 10;

    function __construct() {
        parent::__construct(
                'appsol_video_player', 'Video Player', array('description' => __('Displays the latest video from a YouTube Playlist.')));
    }

    function init() {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            register_widget('appsolVideoPlayer');
            add_shortcode('appsolvideo', array('appsolVideoPlayer', 'shortcodeHandler'));
            if (!is_admin()) {
                wp_enqueue_style('video-player', plugins_url('css/player.css', __FILE__));
                wp_enqueue_script('jcarousel-core', plugins_url('js/jquery.jcarousel-core.js', __FILE__), array('jquery'), '', true);
                wp_enqueue_script('jcarousel-control', plugins_url('js/jquery.jcarousel-control.js', __FILE__), array('jcarousel-core'), '', true);
                wp_enqueue_script('video-player', plugins_url('js/main.js', __FILE__), array('jcarousel-core', 'jcarousel-control'), '', true);
            }
        }
    }

    function form($instance) {
        $defaults = array('title' => 'Latest Video');
        $instance = wp_parse_args((array) $instance, array(
            'title' => 'Latest Video',
            'thumbs' => 0,
            'channel' => '',
            'playlist' => '',
            'video_width' => 370,
            'video_height' => 208,
            'home_only' => ''));
        $thumbcount = $instance['thumbs'];
        ?>
        <p><label for="<?php echo $this->get_field_id("title"); ?>"><?php _e('Title'); ?>:</label>
            <input id="<?php echo $this->get_field_id("title"); ?>"
                   name="<?php echo $this->get_field_name("title"); ?>"
                   value="<?php echo $instance['title'] ?>" /></p>
        <p><label for="<?php echo $this->get_field_id("channel"); ?>"><?php _e('Channel ID'); ?>:</label>
            <input id="<?php echo $this->get_field_id("channel"); ?>"
                   name="<?php echo $this->get_field_name("channel"); ?>"
                   value="<?php echo $instance['channel'] ?>" /></p>
        <p><label for="<?php echo $this->get_field_id("playlist"); ?>"><?php _e('Playlist ID'); ?>:</label>
            <input id="<?php echo $this->get_field_id("playlist"); ?>"
                   name="<?php echo $this->get_field_name("playlist"); ?>"
                   value="<?php echo $instance['playlist'] ?>" /></p>
        <p><label for="<?php echo $this->get_field_id("thumbs"); ?>"><?php _e('Show Older Videos Thumbnails'); ?>:</label>
            <select name="<?php echo $this->get_field_name("thumbs"); ?>" id="<?php echo $this->get_field_name("thumbs"); ?>">
                <option value="0"><?php _e('No Thumbs'); ?></option>
                <?php for ($i = 1; $i < 11; $i++): ?>
                    <?php $selected = $thumbcount == $i ? ' selected="selected"' : ''; ?>
                    <option value="<?php echo $i; ?>"<?php echo $selected; ?>><?php echo $i ?></option>
                <?php endfor; ?>
            </select></p>
        <p><label for="<?php echo $this->get_field_id("video_width"); ?>"><?php _e('Player Width'); ?>:</label>
            <input id="<?php echo $this->get_field_id("video_width"); ?>"
                   name="<?php echo $this->get_field_name("video_width"); ?>"
                   value="<?php echo $instance['video_width'] ?>">px</p>
        <p><label for="<?php echo $this->get_field_id("video_height"); ?>"><?php _e('Player Height'); ?>:</label>
            <input id="<?php echo $this->get_field_id("video_height"); ?>"
                   name="<?php echo $this->get_field_name("video_height"); ?>"
                   value="<?php echo $instance['video_height'] ?>">px</p>
        <p>
            <input class="checkbox" id="<?php echo $this->get_field_id('home_only'); ?>" name="<?php echo $this->get_field_name('home_only'); ?>" type="checkbox" value="yes" <?php if (esc_attr($instance['home_only']) == 'yes') echo 'checked="checked"'; ?> />
            <label for="<?php echo $this->get_field_id('home_only'); ?>"><?php _e('Display on Home page only'); ?></label>
        </p>
        <?php
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = $new_instance['title'];

        // Only show this on the home page?
        $instance['home_only'] = strip_tags($new_instance['home_only']);
        $instance['channel'] = strip_tags($new_instance['channel']);
        $instance['playlist'] = strip_tags($new_instance['playlist']);
        $instance['thumbs'] = intval(strip_tags($new_instance['thumbs']));
        $instance['video_width'] = intval(strip_tags($new_instance['video_width']));
        $instance['video_height'] = intval(strip_tags($new_instance['video_height']));

        delete_transient('youtube_player_' . $this->id);
        return $instance;
    }

    function widget($args, $instance) {
        extract($args);
        // Only show on the Home page?
        if ($instance['home_only'] == 'yes' && !is_front_page())
            return;
        $title = apply_filters('widget_title', $instance['title']);
        // Use the cached transient if available
        $output = get_transient('youtube_player_' . $widget_id);
        // No transient so build new cache
        if ($output == false) {
            $channel_id = $instance['channel'];
            $playlist_id = $instance['playlist'];
            $thumbs = $instance['thumbs'];
            $max = $thumbs ? $thumbs : 1;
            if (!$channel_id && !$playlist_id)
                return;
            if ($playlist_id) {
                $result = json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/playlists/' . $playlist_id . '?v=2&orderby=position&max-results=' . $max . '&alt=jsonc'));
            } else {
                $result = json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/users/' . $channel_id . '/uploads?&v=2&max-results=' . $max . '&alt=jsonc'));
            }

            // Load all the display content into a buffer
            ob_start();
            $latest_video = isset($result->data->items[0]->video) ? $result->data->items[0]->video : $result->data->items[0];
            ?>
            <div class="bd">
                <?php $this->showPlayer($widget_id, $latest_video, $instance['video_height'], $instance['video_width']); ?>
            </div>
            <?php if ($thumbs): ?>
                <div class="ft">
                    <h4><?php _e('Previous Episodes'); ?></h4>
                    <?php $this->showPreviousEpisodes($result->data->items, $thumbs); ?>
                </div>
            <?php endif; ?>
            <?php
            $output = ob_get_contents();
            ob_end_clean();
            set_transient('youtube_player_' . $widget_id, $output, 60 * 60);
        }
        echo $before_widget;
        if ($title)
            echo $before_title . $title . $after_title;
        echo $output;
        echo $after_widget;
        return true;
    }

    public function shortcodeHandler($attributes) {

        extract(shortcode_atts(array(
            'youtube_user' => 'blank',
            'playlist' => 'blank',
            'height' => 700,
            'width' => 426
                        ), $attributes));
        if ($youtube_user == 'blank' && $playlist == 'blank')
            return false;
        if ($youtube_user != 'blank')
            self::channelPage($youtube_user, $playlist, $height, $width);
        else
            self::playlistPage($playlist, $height, $width);
    }

    public function playlistPage($playlist, $height, $width) {
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
                <?php self::showPlayer($post->ID, $latest_video, $height, $width, true, !empty($start_video)); ?>
                <div id="playlists" class="playlists accordion">
                    <?php self::showPlaylist($playlist); ?>
                </div>
            </div>
            <?php
            $output = ob_get_contents();
            ob_end_clean();
            set_transient('youtube_page_' . $post->ID, $output, 60 * 60);
        }
        echo $output;
    }

    public function channelPage($user, $playlist, $height, $width) {
        global $post;
        $start_video = isset($_GET['start']) ?
                json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/videos/' . $_GET['start'] . '?v=2&alt=jsonc')) : null;
        $video_feed = json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/users/' . $user . '/uploads?&v=2&max-results=1&alt=jsonc'));
        
        $playlists_feed = json_decode(file_get_contents('https://gdata.youtube.com/feeds/api/users/' . $user . '/playlists?&v=2&alt=jsonc'));
        
        $latest_video = ($start_video) ? $start_video->data : $video_feed->data->items[0]->video;
        $playlists = $playlists_feed->data->items;
        ?>
        <div id="video_page">
            <?php self::showPlayer($post->ID, $latest_video, $height, $width, true, !empty($start_video)); ?>
            <div id="playlists" class="playlists accordion">
                <?php foreach ($playlists as $playlist): ?>
                    <?php self::showPlaylist($playlist); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function showPlayer($player_id, $video, $height, $width, $desc = false, $start = false) {
        ?>
        <div id="video_player_container_<?php echo $player_id; ?>" class="video-player">
            <iframe class="video-container" src="http://www.youtube.com/embed/<?php echo $video->id; ?>?wmode=transparent<?php echo ($start) ? '&autoplay=1' : '' ?>" frameborder="0" allowfullscreen="" width="<?php echo $width; ?>" height="<?php echo $height; ?>"></iframe>
            <a class="video-link" href="http://www.youtube.com/watch?v=<?php echo $video->id; ?>">
                Watch <?php echo $video->title; ?>
            </a>
        </div>
        <?php if ($desc): ?>
            <div class="video-description">
                <p><?php echo $video->description; ?></p>
            </div>
            <?php
        endif;
    }

    public function showPlaylist($playlist) {
        if (!isset($playlist->data->items))
            $playlist = json_decode(file_get_contents('http://gdata.youtube.com/feeds/api/playlists/' . $playlist->id . '?v=2&orderby=position&alt=jsonc'));
        $videos = $playlist->data->items;
        $playlist = $playlist->data;
        ?>
        <div class="playlist accordion-group">
            <div class="row-fluid playlist-inner accordion-heading">
                <div class="span6 playlist-meta">
                    <h3><?php echo $playlist->title; ?></h3>
                    <p><?php echo $playlist->description ?></p>
                </div>
                <div class="span6 latest-video-meta">
                    <h3>Latest Episode</h3>
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
                    for ($offset = 1; $offset < count($videos); $offset++):
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
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    public function showPreviousEpisodes($items, $max = 5) {
        ?>
        <div class="video-episodes">
            <ul class="episode-list">
                <?php for ($i = 1; $i < $max; $i++): ?>
                    <?php $item = isset($items[$i]->video) ? $items[$i]->video : $items[$i]; ?>
                    <li class="video-episode">
                        <a id="<?php echo $item->id; ?>" href="/video?start=<?php echo $item->id; ?>">
                            <img src="<?php echo $item->thumbnail->sqDefault; ?>" alt="<?php echo $item->title; ?>"/>
                            <span class="video-title"><?php echo wp_trim_words($item->title, appsolVideoPlayer::$title_max_words); ?></span></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
        <a href="#" class="control prev-episode">prev</a>
        <a href="#" class="control next-episode">next</a>
        <?php
    }

}

add_action("widgets_init", array('appsolVideoPlayer', 'init'));