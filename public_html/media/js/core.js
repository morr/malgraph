/*
 * glider-related functions
 */
	//animate glider - change background position every x ms
	function animateGlider() {
		var pos = $('#glider .target').css('background-position');
		var matches = pos.match(/(-?\d+)/g);
		var left = matches[0];
		var top = matches[1];
		left -= 96;
		if (left <= 96 * -4) {
			left = 0;
		}
		$('#glider .target').css('background-position', left + 'px ' + top + 'px');
	}

	//show glider
	function showGlider() {
		$('#glider').fadeIn('slow');
		window.setInterval(animateGlider, 550);
	}

	$(function() {
		//show glider with short delay
		var timeout;
		function showGliderDelayed() {
			timeout = window.setTimeout(showGlider, 550);
		}

		//fix some weird history issues on some browsers
		$(window).load(function() {
			if (timeout) {
				window.clearTimeout(timeout);
			}
		});

		function attachGlider(elems, event) {
			elems.each(function() {
				var target = $(this);
				target.bind(event, function(e, data) {
					if (data == 'fire') {
						return true;
					}
					showGliderDelayed();
					taget.trigger(e, ['fire']);
					return false;
				});
			});
		}

		attachGlider($('a.waitable, button.waitable'), 'click');
		attachGlider($('form.waitable'), 'submit');
	});

$(function() {
	//focus search input
	$('input').focus();

	//scroll scrollable elements
	$('.scrollable').jScrollPane({horizontalDragMaxWidth: 0});
});
