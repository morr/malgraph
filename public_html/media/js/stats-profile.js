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
});
