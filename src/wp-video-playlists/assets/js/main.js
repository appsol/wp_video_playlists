/**
 * WP Video Playlists Plugin Main.js

 * @version 0.3.0
 * @param {window} w
 * @param {jQuery} $
 */
(function(w, $) {

    var setup = function setup()
    {
        $(w).resize(function()
            {
                sizeVideoIframe();
            });
        setupEpisodeList();
        // $.getScript('https://www.youtube.com/iframe_api');
        // w.onYouTubeIframeAPIReady = function()
        //     {
        //         setupYouTubePlayer();
        //     };
        // w.videoPlayers = {};
        sizeVideoIframe();
	};

    var setupEpisodeList = function setupEpisodeList()
    {
        $('.video-episodes').jcarousel({
            animation: {
                duration: 800,
                easing: 'linear'
            }
        });
        $('.prev-episode').click(function(e)
        {
            e.preventDefault();
            $('.video-episodes').jcarousel('scroll', '-=1');
        });
        $('.next-episode').click(function(e)
        {
            e.preventDefault();
            $('.video-episodes').jcarousel('scroll', '+=1');
        });

        $('.episode-list a').click(function(e)
        {
            e.preventDefault();
            var id = $(this).data('youtubeId');
            var $player = $(this).parents('.widget,#video_page').find('.video-container');
            /**
             * @todo Use the YouTube iFrame API to manage the video swapping
             * Currently cannot get this to work
             */
            // var player = new YT.Player(iFrame, {
            //     events: {
            //         onReady: function(event)
            //         {
            //             event.target.loadVideoById({
            //                 'videoId': id
            //             });

            //             event.target.playVideo();

            //             location.href = "#" + iFrame.id;
            //         }
            //     }
            // });
            // w.videoPlayers[$player.attr('id')].loadVideoById({
            //     'videoId': id
            // });
            // w.videoPlayers[$player.attr('id')].playVideo();

            var a  = $('<a>', {href: $player.attr('src')})[0];
            var qs = a.search;
            if (qs.indexOf('autoplay') == -1) {
                qs += '&autoplay=1';
            }
            $player.attr('src', 'http://www.youtube.com/embed/' + id + qs);
            location.href = "#" + $(this).parents('.widget,#video_page').attr('id');
        });
    };

    var setupYouTubePlayer = function setupYouTubePlayer()
    {
        $('.video-container').each(function()
        {
            w.videoPlayers[this.id] = new YT.Player(this);
        });
    };

    var sizeVideoIframe = function sizeVideoIframe() {
    	$('.video-container').each(function()
        {
            var width = $(this).attr('width');
            var height = $(this).attr('height');
            if (width !== $(this).parent().width()) {
                $(this).attr('width', $(this).parent().width());
                $(this).attr('height', Math.floor($(this).parent().width() * (height / width)));
            }
        });
    };

    // Initiation point
    $(document).ready(setup());

})(window, jQuery);

