//make profile boxes heights equal
function resizeSections() {
	if ($('.compare-mode').length == 0) {
		return;
	}
	//init to 0
	var sections = {};
	$('.section', $('.user').eq(0)).each(function(i, e) {
		sections[i] = 0;
	});

	//get max height for each section
	$('.user').each(function() {
		$('.section', $(this)).each(function(i, e) {
			//restore original height before getting actual height.
			//we do this because image can load long after the page
			//was loaded, and therefore change section height.
			$(this).css('height', 'auto');
			//get actual height and update maximum height
			var h = $(this).height();
			if (h > sections[i]) {
				sections[i] = h;
			}
		});
	});

	//set max height for each section
	$('.user').each(function() {
		$('.section', $(this)).each(function(i, e) {
			$(this).css('height', sections[i] + 'px');
		});
	});
}
$(window).resize(resizeSections);
resizeSections();

