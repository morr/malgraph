$(function() {
	//trigger section resizing also when images finish loading
	$('img').one('load', function() {
		$(window).trigger('resize');
	}).each(function() {
		if (this.complete) {
			$(this).trigger('load');
		}
	});
});
