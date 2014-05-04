/**
 * Appsol Video Player Plugin Main.js
 * @version 0.2.1
 * @param {window} w
 * @param {jQuery} $
 */
(function(w, $) {
    var setup = function setup() {
	if (typeof w.sizeVideoIframe() != 'undefined');
	    w.sizeVideoIframe();
	
	$('.video-episodes').jcarousel({
	    animation: {
		duration: 800,
		easing: 'linear'
	    }
	});
	$('.prev-episode').jcarouselControl({
	    target: '-=1'
	});
	$('.next-episode').jcarouselControl({
	    target: '+=1'
	});
    };
    $('.episode-list a').click(function(e) {
	e.preventDefault();
	var id = this.id;
	$(this).parents('.widget_appsol_video_player').find('.video-container').attr('src', 'http://www.youtube.com/embed/' + id + '?fs=1&autoplay=1');
	location.href = "#" + $(this).parents('.widget_appsol_video_player').attr('id');
    });
    $('#playlists .video-link').click(function(e) {
	e.preventDefault();
	var id = this.id;
	$(this).parents('#video_page').find('.video-container').attr('src', 'http://www.youtube.com/embed/' + id + '?fs=1&autoplay=1');
	location.href = "#video_page";
    });

    w.sizeVideoIframe = function sizeVideoIframe() {
	var iframe = $('.video-player iframe');
	if (iframe.length) {
	    var width = $(iframe).attr('width');
	    var height = $(iframe).attr('height');
	    if (width !== $(iframe).parent().width()) {
		$(iframe).attr('width', $(iframe).parent().width());
		$(iframe).attr('height', Math.floor($(iframe).parent().width() * (height / width)));
	    }
	}

    };

    // Initiation point
    $(document).ready(setup());

})(window, jQuery);

