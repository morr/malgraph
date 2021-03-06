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
		var am = $(e.target).parents('.sub-stats').attr('data-am');
		toggleMoreWrappers($(this).parents('.sub-stats-wrapper').find('.wrapper-more'), {'sender': sender, 'am': am});
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
			//text = (diff / 86400).toFixed(1) + ' days ago';
			text = 'update in progress';
		}
		text += ' (' + $(this).text() + ')';
		$(this).text(text);
	});

	$('table tbody tr').click(function(e) {
		e.preventDefault();
		var subType = $(this).attr('data-sub-type');
		var am = $(e.target).parents('.sub-stats').attr('data-am');
		toggleMoreWrappers($(this).parents('.sub-stats-wrapper').find('.wrapper-more'), {'sender': 'sub-type', 'sub-type': subType, 'am': am});
	});
	$('table').tablesorter({
		sortList: [[1,1],[2,1]]
	});
});
