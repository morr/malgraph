//function making profile boxes heights equal
function resizeSections() {
	if ($('.compare-mode').length == 0) {
		return;
	}

	var users = {};
	$('.user').each(function(i, e) {
		users[i] = {'sections': {}};
		$('.section', $(this)).each(function(i2, e2) {
			users[i]['sections'][i2] = $(this);
		});
	});

	//init to 0
	var maxHeights = {};
	for (var j in users[0]['sections']) {
		maxHeights[j] = 0;
	}

	for (var i in users) {
		var u = users[i];
		for (var j in u['sections']) {
			var s = u['sections'][j];
			var h = s.css('height', 'auto').height();
			if (h > maxHeights[j]) {
				maxHeights[j] = h;
			}
		}
	}
	for (var i in users) {
		var u = users[i];
		for (var j in u['sections']) {
			var s = u['sections'][j];
			s.css('height', maxHeights[j] + 'px');
		}
	}
}
$(window).resize(resizeSections);
resizeSections();

function toggleWithinSections(targets, hideSiblings) {
	var data = {};
	targets.each(function(i, e) {
		data[i] = {};
		var target = $(this);
		var section = $(target).parents('.section');
		var visible = data[i]['visible'] = target.is(':visible');
		var oldHeight = section.height();
		//simulate what will be done by animating
		section.css('height', 'auto');
		if (!visible) {
			if (hideSiblings) {
				data[i]['siblings'] = [];
				target.siblings().each(function() {
					data[i]['siblings'].push($(this).is(':visible'));
				}).hide();
			}
			target.show();
		} else {
			target.hide();
		}
		var newHeight = data[i]['height'] = section.height();
		section.css('height', oldHeight + 'px');
		//restore original state
		if (!visible) {
			if (hideSiblings) {
				target.siblings().each(function(j, e) {
					var sibling = $(this);
					if (data[i]['siblings'][j]) {
						sibling.show();
					} else {
						sibling.hide();
					}
				});
			}
			target.hide();
		} else {
			target.show();
		}
	});

	var maxHeight = 0;
	for (var i in data) {
		if (data[i]['height'] > maxHeight) {
			maxHeight = data[i]['height'];
		}
	}

	targets.each(function(i, e) {
		var target = $(this);
		var section = $(target).parents('.section');
		section.animate({'height': maxHeight + 'px'}, {queue: false});

		if (!data[i]['visible']) {
			if (hideSiblings) {
				target.siblings().slideUp('slow');
			}
			target.slideDown('slow');
		} else {
			target.slideUp('slow');
		}
	});
}
