$.fn.hasAttr = function(name) {
	return this.attr(name) !== undefined;
};

// scroll scrollable elements
$(function() {
	$('.scrollable').jScrollPane({horizontalDragMaxWidth: 0, autoReinitialise: true});
});
