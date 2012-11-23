$.fn.hasAttr = function(name) {
	return this.attr(name) !== undefined;
};

$(function() {
	//scroll scrollable elements
	$('.scrollable').jScrollPane({horizontalDragMaxWidth: 0});


	/* tooltips */
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
				}
				var div = $('<div class="tooltip"/>').addClass(c).append($('<div>').text(title));
				$(target).data('tooltip', div);

				$(target).after(div);
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
