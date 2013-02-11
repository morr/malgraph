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

	var diff = (new Date() - $('#glider').data('start')) / 1000;
	for (var i in messages) {
		var subMessages = messages[i];
		var message = subMessages[Math.floor(Math.random() * subMessages.length)];
		if (diff >= i) {
			delete messages[i];
			$('#glider p').fadeOut(function() {
				$(this).html(message).fadeIn();
			});
			break;
		}
	}
}

var messages = {
	5: [ 'downloading your data&hellip;', 'performing magic tricks&hellip;', 'reticulating splines&hellip;' ],
	10: [ 'applying final touches&hellip;', 'antialiasing buttons&hellip;' ],
	20: [ 'this shouldn&rsquo;t take much longer.', 'don&rsquo;t panic yet.' ],
	30: [ 'does your list even end?', 'ah, the cable was unplugged.' ],
	40: [ 'your stats will appear any second now.', 'good things come to those who wait.' ]
};

//show glider
function showGlider() {
	$('#glider').fadeIn('slow');
	window.setInterval(animateGlider, 550);
}

//show glider with short delay
var timeout;
function showGliderDelayed() {
	$('#glider').data('start', new Date());
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
