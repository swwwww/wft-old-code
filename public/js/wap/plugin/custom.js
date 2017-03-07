$(function() {

	"use strict";

	prettyPrint();
	
});


function loading(text){
	var opts = {
		lines: 13, // The number of lines to draw
		length: 11, // The length of each line
		width: 5, // The line thickness
		radius: 17, // The radius of the inner circle
		corners: 1, // Corner roundness (0..1)
		rotate: 0, // The rotation offset
		color: '#FFF', // #rgb or #rrggbb
		speed: 1, // Rounds per second
		trail: 60, // Afterglow percentage
		shadow: false, // Whether to render a shadow
		hwaccel: false, // Whether to use hardware acceleration
		className: 'spinner', // The CSS class to assign to the spinner
		zIndex: 2e9, // The z-index (defaults to 2000000000)
		top: 'auto', // Top position relative to parent in px
		left: 'auto' // Left position relative to parent in px
	};
	var target = document.createElement("div");
	document.body.appendChild(target);
	var spinner = new Spinner(opts).spin(target);
	iosOverlay({
		text: text ? text : "Loading",
		duration: 2e3,
		spinner: spinner
	});

	return false;
}


function loadingSuccess(text){
	window.setTimeout(function() {
		iosOverlay({
			text: text ? text : "Success!",
			icon: "/images/wap/check.png"
		});
	}, 2000);
	return false;
}


function loadingErr(text){
	iosOverlay({
		text: text ? text : "Error!",
		duration: 2e3,
		icon: "/images/wap/cross.png"
	});
	return false;
}