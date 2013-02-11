$(function() {
	if ($('th.unique').length > 0) {
		sortList = [[1,0],[2,0]];
	} else {
		sortList = [[2,1],[1,0]];
	}
	$('table').tablesorter({
		textExtraction: function(node) {
			return $(node).attr('data-sorter');
		},
		sortList : sortList
	});

	$(window).trigger('resize');
});
