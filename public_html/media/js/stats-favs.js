$(function() {
	$('.genres .more-trigger').click(function(e) {
		e.preventDefault();
		toggleMoreWrappers($('.genres .wrapper-more'), {'sender': 'genre', 'genre': $(this).attr('data-id')});
	});

	$.tablesorter.addWidget({
		id: 'ord',
		format: function(table) {
			for (var i = 0; i < table.tBodies[0].rows.length; i ++) {
				$('tbody tr:eq(' + i + ') td:first', table).text(i + 1);
			}
		}
	});

	$('table').tablesorter({
		headers: { 0: { sorter: false }, 5: { sorter: 'percent' } },
		widgets: ['ord'],
		sortList: [[4,1]]
	});
});

