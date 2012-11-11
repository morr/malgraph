$(function() {
	function animateGlider() {
		var pos = $('#glider .target').css('background-position');
		var matches = pos.match(/(-?\d+)/g);
		var left = matches[0];
		var top = matches[1];
		left -= 96;
		if (left <= 96 * -4) {
			left = 0;
		}
		console.log(left);
		$('#glider .target').css('background-position', left + 'px ' + top + 'px');
	}
	function showGlider() {
		$('#glider').fadeIn('slow');
		window.setInterval(animateGlider, 550);
	}

	var timeout;
	function showGliderDelayed() {
		timeout = window.setTimeout(showGlider, 550);
	}
	$(window).unload(function() {
		window.clearTimeout(timeout);
	});

	$('a.waitable, button.waitable').click(showGliderDelayed);
	$('form.waitable').submit(showGliderDelayed);

	$('input').focus();
});
