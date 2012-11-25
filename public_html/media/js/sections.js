/*
 * function instantly making profile boxes heights equal
 */
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
$(function() {
	$(window).resize(resizeSections);
	resizeSections();
});

/*
 * function for profile boxes height animation
 *        - for showing and hiding something within sections when they need to stay the same height.
 */
function toggleWithinSections(targets, hideSiblings) {
	//make sure to toggle all sections even if there is no target element within them.
	var section = $(targets).eq(0).parents('.section');
	var sections = $('.' + section.attr('class').replace(/\s+/g, '.'));
	//^ this approach requires the parent section class to be usable for matching all sibling sections!

	var data = {};
	sections.each(function(i, e) {
		data[i] = {};
		var target = $(targets, $(this));
		var section = $(this);//$(target).parents('.section');
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
		//get the height we're gonna animate soon
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

	//calculate maximum height.
	var maxHeight = 0;
	for (var i in data) {
		if (data[i]['height'] > maxHeight) {
			maxHeight = data[i]['height'];
		}
	}

	sections.each(function(i, e) {
		var section = $(this);
		section.animate({'height': maxHeight + 'px'}, {queue: false});
	});

	targets.each(function(i, e) {
		var target = $(this);
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
