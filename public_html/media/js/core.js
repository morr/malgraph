$.get('/media/js/glider.js');
$.get('/media/js/sections.js');

$(function() {
	//focus search input
	$('input').focus();

	//scroll scrollable elements
	$('.scrollable').jScrollPane({horizontalDragMaxWidth: 0});
});
