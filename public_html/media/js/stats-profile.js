$(function() {
	//trigger section resizing also when images finish loading
	$('img').one('load', function() {
		$(window).trigger('resize');
	}).each(function() {
		if (this.complete) {
			$(this).trigger('load');
		}
	});

	$('.franchises, .mismatched-eps').click(function(e) {
		var sender = $(this).attr('class');
		var am = $(this).parents('.sub-stats').attr('data-am');
		toggleMoreWrappers($('.sub-stats[data-am=\'' + am + '\'] .wrapper-more'), {'sender': sender, 'am': am});
		e.preventDefault();
	});

	$('.updated').each(function() {
		var now = new Date();
		var then = new Date($(this).attr('data-date'));
		var diff = now - then;
		diff /= 1000.0;
		var text = '';
		if (diff < 300) {
			text = 'just now';
		} else if (diff < 3600) {
			text = (diff / 60).toFixed(0) + ' minutes ago';
		} else if (diff < 86400) {
			text = (diff / 3600).toFixed(1) + ' hours ago';
		} else {
			text = (diff / 86400).toFixed(1) + ' days ago';
		}
		text += ' (' + $(this).text() + ')';
		$(this).text(text);
	});
});
