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

// fluid menu position
/*$(function() {
	var menu = $('#menu');
	var content = $('#content');
	if (menu.length == 0) {
		return;
	}
	var y0 = 10;
	var y1 = menu.offset().top;
	var x_delta = content.offset().left - menu.offset().left;
	menu.css('position', 'fixed');
	$(window).bind('resize scroll', function(e) {
		var y2 = $(window).scrollTop();
		if (y2 < y1 - y0) {
			menu.css('top', (y1 - y2) + 'px');
		} else {
			menu.css('top', y0 + 'px');
		}
		menu.css('left', (content.offset().left - $(window).scrollLeft() - x_delta) + 'px');
	});
});*/

function toggleMoreDiv(targets, uniqueId, showCallback) {
	$(targets).each(function() {
		var target = $(this);
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
		showCallback(function() {
			target.stop(true, true).slideDown('slow');
		});
	});
}
$(function() {
	$('.wrapper-more .close').live('click', function(e) {
		e.preventDefault();
		var target = $(this).parents('.wrapper-more');
		target.stop(true, true).slideUp('fast');
	});
});
