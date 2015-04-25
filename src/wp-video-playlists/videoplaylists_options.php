<?php
/**
 * VideoPlaylistOptions
 * 
 * @package wp_video_playlists
 * @author Stuart Laverick
 */
namespace VideoPlaylists;

defined('ABSPATH') or die( 'No script kiddies please!' );

class VideoPlaylistsOptions
{
    /**
     * Holds the values to be used in the fields callbacks
     *
     * @var string
     **/
    private $options;

    /**
     * Constructor
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'addPluginPage' ) );
        add_action( 'admin_init', array( $this, 'pageInit' ) );
    }

    /**
     * Adds the Settings menu menu item
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function addPluginPage()
    {
        // This page will be under "Settings"
        add_options_page(
            'Video Playlists Options', 
            'Video Playlists', 
            'manage_options', 
            'videoplaylists-admin', 
            array( $this, 'createAdminPage' )
        );
    }

    /**
     * Callback for options page
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function createAdminPage()
    {
        // Set class property
        $this->options = get_option( 'videoplaylists' );
        ?>
        <div class="wrap">
            <h2>Video Playlists Settings</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'videoplaylists_option_group' );   
                do_settings_sections( 'videoplaylists-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function pageInit()
    {
        register_setting(
            'videoplaylists_option_group', // Option group
            'videoplaylists', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'video_sources', // ID
            'Video Source Details', // Title
            array( $this, 'printVideoSourcesInfo' ), // Callback
            'videoplaylists-setting-admin' // Page
        );

        add_settings_field(
            'youtube_simple_key', // ID
            'YouTube Simple API Key', // Title 
            array( $this, 'youtubeSimpleKeyCallback' ), // Callback
            'videoplaylists-setting-admin', // Page
            'video_sources' // Section
        );

        add_settings_field(
            'load_css', // ID
            'Load Plugin CSS', // Title 
            array( $this, 'loadCssCallback' ), // Callback
            'videoplaylists-setting-admin', // Page
            'video_sources' // Section
        );

        add_settings_field(
            'load_js', // ID
            'Load Plugin Javascript', // Title 
            array( $this, 'loadJsCallback' ), // Callback
            'videoplaylists-setting-admin', // Page
            'video_sources' // Section
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     * @return void
     * @author Stuart Laverick
     **/
    public function sanitize($input)
    {
        $new_input = array();

        if( isset( $input['youtube_simple_key'] ) ) {
            $new_input['youtube_simple_key'] = sanitize_text_field( $input['youtube_simple_key'] );
        }

        if( isset( $input['load_css'] ) && $input['load_css'] === 'yes' ) {
            $new_input['load_css'] = 'yes';
        }

        if( isset( $input['load_js'] ) && $input['load_js'] === 'yes' ) {
            $new_input['load_js'] = 'yes';
        }

        return $new_input;
    }

    /**
     * Print the section text for the Video Sources section
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function printVideoSourcesInfo()
    {
        print "Enter your API keys and Authentication details";
    }

    /**
     * Prints the input field for youtube_simple_key
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function youtubeSimpleKeyCallback()
    {
        printf(
            '<input type="text" id="youtube_simple_key" name="videoplaylists[youtube_simple_key]" value="%s" />',
            isset( $this->options['youtube_simple_key'] ) ? esc_attr( $this->options['youtube_simple_key']) : ''
        );
    }

    /**
     * Prints the checkbox field for load_css
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function loadCssCallback()
    {
        printf(
            '<input type="checkbox" id="load_css" name="videoplaylists[load_css]" value="yes" %s/>',
            isset( $this->options['load_css'] ) ? 'checked ' : ''
        );
    }

    /**
     * Prints the checkbox field for load_js
     *
     * @return void
     * @author Stuart Laverick
     **/
    public function loadJsCallback()
    {
        printf(
            '<input type="checkbox" id="load_js" name="videoplaylists[load_js]" value="yes" %s/>',
            isset( $this->options['load_js'] ) ? 'checked ' : ''
        );
    }
}