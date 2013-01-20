$.fn.hasAttr = function(name) {
	return this.attr(name) !== undefined;
};

// scroll scrollable elements
$(function() {
	$('.scrollable').jScrollPane({horizontalDragMaxWidth: 0});
});

// tooltips
$(function() {
	/* timeouts to prevent flickering */
	function stopTooltipRemoval(target) {
		var timeout = $(target).data('tooltip-hide-timeout');
		window.clearTimeout(timeout);
		$(target).data('tooltip-hide-timeout', null);
	}

	function startTooltipRemoval(target) {
		var div = $(target).data('tooltip');
		if (!div) {
			return;
		}
		var timeout = window.setTimeout(function() {
			div.fadeOut('fast', function() {
				$(this).remove();
			});
			$(target).data('tooltip-hide-timeout', null);
		}, 50);
		$(target).data('tooltip-hide-timeout', timeout);
	}

	$('.tooltipable').each(function() {
		$(this).mouseenter(function() {
			var target = $(this);

			if ($(target).data('tooltip-show-timeout')) {
				return;
			}
			if ($(target).data('tooltip-hide-timeout')) {
				stopTooltipRemoval(target);
				return;
			}

			var delay = $(target).hasAttr('data-delay') ? $(target).attr('data-delay') : 300;
			var title = $(target).hasAttr('title') ? $(target).attr('title') : $(target).attr('data-title');
			var posMy = $(target).hasAttr('data-position-my') ? $(target).attr('data-position-my') : 'center top';
			var posAt = $(target).hasAttr('data-position-at') ? $(target).attr('data-position-at') : 'center bottom';

			var timeout = window.setTimeout(function() {
				var length = title.length;
				if (length < 30) {
					c = 'short';
				} else if (length < 70) {
					c = 'medium';
				} else if (length < 110) {
					c = 'big';
				} else {
					c = 'big';
				}
				var div = $('<div class="tooltip"/>').addClass(c).append($('<div>').text(title));
				$(target).data('tooltip', div);

				if ($(target).is('th') || $(target).is('td')) {
					$(target).append(div);
				} else {
					$(target).after(div);
				}
				$(div).position({of: $(target), my: posMy, at: posAt, collision: 'fit fit'})
					.mouseenter(function() { stopTooltipRemoval(target); })
					.mouseleave(function() { startTooltipRemoval(target); })
					.hide()
					.fadeIn('fast');
				$(target).data('tooltip-show-timeout', null);
			}, delay);

			$(target).data('tooltip-show-timeout', timeout);

		}).mouseleave(function() {
			var target = $(this);

			window.clearTimeout($(target).data('tooltip-show-timeout'));
			$(target).data('tooltip-show-timeout', null);
			startTooltipRemoval($(target));
		} );
	});
});

function toggleMoreWrappers(targets, data, ajax) {
	if (typeof ajax == 'undefined') {
		ajax = true;
	}
	var url = '/ajax/ajax';
	$(targets).each(function() {
		var target = $(this);
		data['u'] = target.parents('.section').attr('data-user-name');
		data['am'] = target.parents('body').attr('data-am');
		var uniqueId = JSON.stringify(data);
		if (target.data('unique-id') == uniqueId) {
			if (target.is(':visible')) {
				target.stop(true, true).slideUp('fast');
			} else {
				target.stop(true, true).slideDown('slow');
			}
			return;
		}
		target.data('unique-id', uniqueId);
		target.hide();
		if (ajax) {
			$.get(url, data, function(response) {
				target.html(response);
				target.stop(true, true).slideDown('slow');
			});
		} else {
			target.stop(true, true).slideDown('slow');
		}
	});
}
$(function() {
	$('.wrapper-more .close').live('click', function(e) {
		e.preventDefault();
		var target = $(this).parents('.wrapper-more');
		target.stop(true, true).slideUp('fast');
	});
});
