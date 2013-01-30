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

//show glider with short delay
var timeout;
function showGliderDelayed() {
	timeout = window.setTimeout(showGlider, 550);
}

//attach glider showing to an event to an element
function attachGlider(elems, event) {
	elems.each(function() {
		var target = $(this);
		target.bind(event, function(e, data) {
			if (!(e.type == 'click' && e.which == 2)) { //supress showing glider on middle mouse button click
				showGliderDelayed();
			}
			return true;
		});
	});
}

$(function() {
	//fix some weird history issues on some browsers
	$(window).load(function() {
		if (timeout) {
			window.clearTimeout(timeout);
		}
	});

	attachGlider($('a.waitable, button.waitable'), 'click');
	attachGlider($('form.waitable'), 'submit');
});
