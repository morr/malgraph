$(function() {

	function resized() {
		//add to height of the table content all the space below the footer
		$('div.table').css('max-height', '0');
		var wh = $(window).height();
		var mh = $('body').outerHeight(true);
		var ch = $('#main').height();
		var sh = $('div.table').eq(0).prev().outerHeight(true);
		var nh = wh - mh + ch - sh;
		if (nh < 450 - sh) {
			nh = 450 - sh;
		}

		$('div.table').css('max-height', nh + 'px');
		$('div.table').jScrollPane();
	}
	$(window).resize(resized);
	resized();
});
