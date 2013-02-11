$(function() {
	$('.more-trigger').click(function(e) {
		var target = $(this).parents('.section').find('.wrapper-more');
		toggleMoreWrappers(target, [], false);
		e.preventDefault();
	});
});
