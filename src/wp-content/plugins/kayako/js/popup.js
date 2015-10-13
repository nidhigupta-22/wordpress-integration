var popupStatus = 0;

function loadPopup() {

	if (popupStatus == 0) {
		jQuery("#backgroundPopup").css({
			"opacity": "0.7"
		});
		jQuery("#backgroundPopup").fadeIn("slow");
		jQuery("#popupContact").fadeIn("slow");
		popupStatus = 1;
	}
}


function disablePopup() {

	if (popupStatus == 1) {
		jQuery("#backgroundPopup").fadeOut("slow");
		jQuery("#popupContact").fadeOut("slow");
		popupStatus = 0;
	}
}


function centerPopup() {

	var windowWidth = document.documentElement.clientWidth;
	var windowHeight = document.documentElement.clientHeight;
	var popupHeight = jQuery("#popupContact").height();
	var popupWidth = jQuery("#popupContact").width();


	jQuery("#popupContact").css({
		"position": "fixed",
		"top":      (windowHeight / 2) - (popupHeight / 2),
		"left":     windowWidth / 2 - (popupWidth / 2)
	});


	jQuery("#backgroundPopup").css({
		"height": windowHeight
	});

}
