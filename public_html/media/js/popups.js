function showPopup(target) {
	target.data('orig-parent', target.parent());
	$('#popup-wrapper').append(target).show();
	$(target).css('display', 'inline-block');
	$(target).fadeIn('fast');
	$('#dim').fadeIn('fast');
}

function hidePopup(target) {
	$(target).fadeOut('fast', function() {
		$('#popup-wrapper').hide();
		target.data('orig-parent').append(target);
	});
	$('#dim').fadeOut('fast');
}

function togglePopup(target) {
	if ($(target).is(':visible')) {
		hidePopup(target);
	} else {
		showPopup(target);
	}
}

$(function() {
	$('body').append($('<div id="popup-wrapper"></div>'));
	$('.popup .title').prepend($('<a href="#" class="close">&times;</a>'));
	$('.popup .cancel.btn, .popup .title .close').click(function(e) {
		e.preventDefault();
		hidePopup($(this).parents('.popup'));
	});
});

