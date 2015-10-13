// Fire upon document ready
jQuery(document).ready(function($) {


	$(".post_reply").click(function() {
		var ticketID = $(this).attr('kayako_post_reply');
		centerPopup();

		loadPopup();
		$('#placeTicketInfoContainer').html("<div class='kayako_big_loader' align='center'></div>");

		var params = {
			'action':   'ticketPostDiv',
			'ticketID': ticketID

		};

		// Fire the AJAX request and wait for JSON
		$.post(ajaxurl, params, function(response) {
			$('#placeTicketInfoContainer').html(response.divcontainer);
			$('#place_ticket_ID').val(ticketID);

		}, 'json');

		return false;

	});


	$('#Reply').live('click', function() {
		$('#click').slideToggle("fast");
	});


	$('#displayTicketData').live('click', function() {
		var kayakodisplayid = $(this).attr('kayako-display-id');
		$('#kayako-display-id' + kayakodisplayid).slideToggle("fast");
	});


	$('.form_reply_content').live('submit', function() {

		$('.kayako-loader').show();
		$('.kayako-submit').hide();


		// Gather the data
		var form = this;
		var post_contents = $(form).find('[name="replyTicketContent"]').val();
		var ticketid = $("#place_ticket_ID").val();

		// Format the AJAX request
		var params = {
			'action':        'post_reply_action',
			'post_contents': post_contents,
			'ticketid':      ticketid
		};

		$.post(ajaxurl, params, function(response) {

			if (response.status == 200) {
				$('.kayako-loader').hide();
				$('.kayako-submit').show();
				$('#put_display_message').html('<div>&nbsp;</div><div align="center" class="frontend_successmessage">Your Ticket Post has been submitted successfully !</div>');
				$(form).find('[name="replyTicketContent"]').val(" ");

			}
			else {
				$('#put_display_message').html('<div>&nbsp;</div><div align="center" class="frontend_errormessage">Sorry there is some issue occurs while creating a ticket post ! </div>');
			}

		}, 'json');

		// Prevent further browsing.
		return false;

	});


	$('.submit_ticket_properties').live('submit', function() {

		$('.kayako_loader_ticketSubmit').show();
		$('.kayako-submit2').hide();

		// Gather the data
		var form = this;
		var ticketid = $(form).find('[name="post_ticketID"]').val();
		var ticketstatusid = $(form).find('[name="ticketstatusID"]').val();
		var ticketPriorityid = $(form).find('[name="ticketPriorityID"]').val();


		// Format the AJAX request
		var params = {
			'action':           'update_ticket_properties',
			'ticketid':         ticketid,
			'ticketstatusid':   ticketstatusid,
			'ticketPriorityid': ticketPriorityid
		};

		$.post(ajaxurl, params, function(response) {

			if (response.status == 200 && response.statusMessage == 'success') {
				$('.kayako_loader_ticketSubmit').hide();
				$('.kayako-submit2').show();

				$('#put_display_message').html('<div>&nbsp;</div><div align="center" class="frontend_successmessage">Your Ticket has updated successfully !</div>');
				$(form).find('[name="replyTicketContent"]').val(" ");

			}
			else {
				$('#put_display_message').html('<div>&nbsp;</div><div align="center" class="frontend_errormessage">Sorry there is some issue occurs while creating a ticket post ! </div>');
			}

		}, 'json');

		// Prevent further browsing.
		return false;

	});


	$("#popupContactClose").click(function() {
		disablePopup();
	});


	$("#backgroundPopup").click(function() {
		disablePopup();
	});


	$(document).keypress(function(e) {
		if (e.keyCode == 27 && popupStatus == 1) {
			disablePopup();
		}
	});


});

