<?php
/**
 * VideoPlaylistWidget
 * 
 * @package wp_video_playlists
 * @author Stuart Laverick
 */
namespace VideoPlaylists;

defined('ABSPATH') or die( 'No script kiddies please!' );

 class VideoPlaylistWidget extends \WP_Widget
 {

  /**
  * Constructor
  *
  * @return void
  * @author Stuart Laverick
  */
  function __construct()
  {
    parent::__construct(
        'video_playlist',
        __('Video Playlist', 'wp_video_playlists'),
        ['description' => __('Shows a video player and video title list from a YouTube or other playlist or channel.')]
    );
  }

  /**
   * Display the form for the widget settings
   *
   * @return void
   * @author Stuart Laverick
   **/
  function form($instance)
  {
        $defaults = array('title' => 'Latest Video');
        $instance = wp_parse_args((array) $instance, array(
            'title' => 'Latest Video',
            'thumbs' => 0,
            'channel' => '',
            'playlists' => [],
            'playlist' => '',
            'video_width' => 370,
            'video_height' => 208,
            'home_only' => ''));
        $thumbcount = $instance['thumbs'];
        ?>
        <p><label for="<?php echo $this->get_field_id("title"); ?>"><?php _e('Title'); ?>:</label>
            <input id="<?php echo $this->get_field_id("title"); ?>"
                   name="<?php echo $this->get_field_name("title"); ?>"
                   value="<?php echo $instance['title'] ?>" class="widefat" /></p>
        <?php if ($instance['message']): ?>
        <p class="message"><?php echo $instance['message']; ?></p>
        <?php endif; ?>
        <p><label for="<?php echo $this->get_field_id("youtube_user"); ?>"><?php _e('YouTube User'); ?>:</label>
            <input id="<?php echo $this->get_field_id("youtube_user"); ?>"
                   name="<?php echo $this->get_field_name("youtube_user"); ?>"
                   value="<?php echo $instance['youtube_user'] ?>" class="widefat" /></p>
        <p><label for="<?php echo $this->get_field_id("channel_id"); ?>"><?php _e('YouTube Channel'); ?>:</label>
            <input id="<?php echo $this->get_field_id("channel_id"); ?>"
                   name="<?php echo $this->get_field_name("channel_id"); ?>"
                   value="<?php echo $instance['channel_id'] ?>" class="widefat" /></p>
        <p><label for="<?php echo $this->get_field_id("playlist"); ?>"><?php _e('Playlist'); ?>:</label>
            <select id="<?php echo $this->get_field_id("playlist"); ?>"
                   name="<?php echo $this->get_field_name("playlist"); ?>">
                <option value="0"><?php _e('No Playlist'); ?></option>
                <?php foreach ($instance['playlists'] as $playlist): ?>
                    <?php $selected = $playlist['id'] == $instance['playlist'] ? ' selected="selected"' : ''; ?>
                    <option value="<?php echo $playlist['id']; ?>"<?php echo $selected; ?>><?php echo $playlist['snippet']['title'] ?></option>
                <?php endforeach; ?>
            </select></p>
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

    /**
     * Update the settings for this instance of the widget
     *
     * @return Array the updated settings array
     * @author Stuart Laverick
     **/
    function update($new_instance, $old_instance)
    {
        $vp = VideoPlaylists::getInstance();
        $vp->deleteTransients($old_instance);

        $instance = $old_instance;
        $instance['message'] = '';

        $instance['title'] = $new_instance['title'];
        // Only show this on the home page?
        $instance['home_only'] = isset($new_instance['home_only'])? 'yes' : 'no';
        $instance['channel_id'] = strip_tags($new_instance['channel_id']);
        $instance['youtube_user'] = strip_tags($new_instance['youtube_user']);
        if ($instance['youtube_user']) {
          if(!$instance['channel_id'] = $vp->getYouTubeUserChannelId($instance['youtube_user'])) {
            $instance['channel_id'] = '';
            $instance['message'] = 'Could not find the Channel for that user';
          }
        }
        if (!$instance['playlists'] = $vp->getYouTubeChannelPlaylists($instance['channel_id'])) {
          $instance['playlists'] = [];
          $instance['message'] = 'No Playlists Found for that Channel';
        }
        $instance['playlist'] = strip_tags($new_instance['playlist']);
        $instance['thumbs'] = intval(strip_tags($new_instance['thumbs']));
        $instance['video_width'] = intval(strip_tags($new_instance['video_width']));
        $instance['video_height'] = intval(strip_tags($new_instance['video_height']));
        if (empty($instance['video_height'])) {
            $instance['video_height'] = floor($instance['video_width'] * 0.6);
        }

        return $instance;
    }

    /**
     * Display the widget
     *
     * @return void
     * @author Stuart Laverick
     **/
    function widget($args, $instance)
    {
        extract($args);
        // Only show on the Home page?
        if ($instance['home_only'] == 'yes' && !is_front_page()) {
            return;
        }
        $title = apply_filters('widget_title', $instance['title']);
        $vp = VideoPlaylists::getInstance();
        $videos = [];
        if ($instance['playlist']) {
            $videos = $vp->getYouTubePlaylistVideos($instance['playlist'], $instance['thumbs'] + 1);
        } else {
            $videos = $vp->getYouTubeChannelVideos($instance['channel_id'], $instance['thumbs'] + 1);
        }
        if (!empty($videos)) {
            $latestVideo = array_shift($videos);

            ob_start();
            ?>
            <div class="bd">
                <?php echo $vp->getPlayerHtml($widget_id, $latestVideo, $instance['video_height'], $instance['video_width']); ?>
            </div>
            <?php if ($instance['thumbs'] && count($videos)): ?>
                <div class="ft previous-episodes">
                    <h4><?php _e('Previous Episodes'); ?></h4>
                    <div class="video-episodes">
                <?php echo $vp->getEpisodeListHtml($videos, $instance['thumbs']); ?>
                    </div>
                    <a href="#" class="control prev-episode">prev</a>
                    <a href="#" class="control next-episode">next</a>
                </div>
            <?php endif; ?>
            <?php
            $output = ob_get_contents();
            ob_end_clean();
        }
        echo $before_widget;
        if ($title) {
            echo $before_title . $title . $after_title;
        }
        echo $output;
        echo $after_widget;
        return true;
    }
}