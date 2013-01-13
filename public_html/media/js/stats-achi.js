function resizeUsers() {
	if ($('.compare-mode').length == 0) {
		return;
	}

	var users = $('.user');
	$('.user').css('height', 'auto');
	$('.user').css('height', $('.users').height()  + 'px');
	$('.users').css('height', 'auto');
}
$(function() {
	$('.section').css('height', 'auto');
	$(window).resize(resizeUsers);
	resizeUsers();

	//trigger user resizing also when images finish loading
	$('img').one('load', function() {
		$(window).trigger('resize');
	}).each(function() {
		if (this.complete) {
			resizeUsers();
		}
	});
});

