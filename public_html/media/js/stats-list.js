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

	$('#filter-status').change(function() { $('#filter-form').submit() });
	$('#filter-title').keyup(function() { $('#filter-form').submit() });
	$('#filter-clear').click(function(e) {
		e.preventDefault();
		$('#filter-title').val('');
		$('#filter-status').val('');
		$('#filter-form').submit();
	});
	$('#filter-form').submit(function(e) {
		e.preventDefault();
		$('.list').each(function() {
			var filterTitle = $('#filter-title').val().toLowerCase();
			var filterStatus = $('#filter-status').val();
			var visible = 0;
			var total = 0;
			$(this).find('table tbody tr').each(function(i, e) {
				var show = true;
				show &= (filterTitle == '' || $(this).find('.title').text().toLowerCase().indexOf(filterTitle) >= 0);
				show &= (filterStatus == '' || $(this).find('.status-' + filterStatus).length > 0);
				$(this).css('display', show ? 'table-row' : 'none');
				visible += show;
				total += 1;
			});
			$(this).find('.filter-hint-shown').text(visible);
			$(this).find('.filter-hint-total').text(total);
			$(this).find('table').toggle(visible > 0);
			$(this).find('.filter-hint').toggle(visible < total);
		});
	});

	$('#filter-title').focus();
});
