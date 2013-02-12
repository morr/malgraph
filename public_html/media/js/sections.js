function toggleMoreWrappers(targets, data, ajax) {
	if (typeof ajax == 'undefined') {
		ajax = true;
	}
	var url = '/ajax/ajax';

	$(targets).each(function(i) {
		var target = $(this);
		var realData = $.extend({}, data);
		realData['u'] = target.parents('.user').attr('data-user-name');
		if (!realData['am']) {
			realData['am'] = target.parents('body').attr('data-am');
		}

		var uniqueId = JSON.stringify(realData);
		if (target.data('unique-id') == uniqueId) {
			if (target.is(':visible')) {
				target.stop(true, true).slideUp('fast');
			} else {
				target.stop(true, true).slideDown();
			}
			return;
		}

		$('body').css('min-height', $('body').height() + 'px');
		var resetHeight = function() { $('body').css('min-height', 'auto'); }

		target.data('unique-id', uniqueId);
		target.slideUp('fast', function() {
			if (ajax) {
				$.get(url, realData, function(response) {
					target.html(response);
					target.stop(true, true).slideDown(resetHeight);
				});
			} else {
				target.stop(true, true).slideDown(resetHeight);
			}
		});
	});
}
$(function() {
	$('.wrapper-more').on('click', '.close', function(e) {
		e.preventDefault();
		var target = $(this).parents('.users').find('.wrapper-more');
		if ($(target).hasClass('singular')) {
			target = $(this).parents('.wrapper-more');
		}
		target.stop(true, true).slideUp('fast');
	});
});

