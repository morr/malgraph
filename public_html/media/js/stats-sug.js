$(function() {
	$('.missing tr').each(function() {
		var num1 = 8;
		var num2 = 5;

		var tr = $(this);
		var ul = tr.find('ul');
		var doCollapse = false;
		ul.each(function() {
			var li = $(this).children('li');
			if (li.length > num1) {
				doCollapse = true;
			}
		});

		if (doCollapse) {
			ul.each(function() {
				var ul2 = $('<ul class="expand"/>');
				$(this).find('li').each(function(i) {
					if (i > num2) {
						ul2.append($(this));
					}
				});
				ul2.insertAfter($(this)).hide();
			});
			var newTr = $('<tr><td colspan="2"/></tr>');
			var link = $('<a class="more" href="#">(more)</a>').click(function(e) {
				e.preventDefault();
				tr.find('.expand').slideDown(function() {
					link.slideUp();
				});
			});
			newTr.insertAfter(tr).find('td').append(link);
		}
	});

	$('.recs .header .more').click(function(e) {
		toggleMoreWrappers($(this).parents('tbody').find('.wrapper-more'), [], false);
		$(this).parents('tr').toggleClass('active');
		e.preventDefault();
	});

	$('.wrapper-more').on('click', '.close', function(e) {
		$(this).parents('tbody').find('tr.header').toggleClass('active');
	});
});

