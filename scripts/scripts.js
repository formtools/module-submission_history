var sh = {
	page_url: g.root_url + "/modules/submission_history/code/actions.php",
	last_content: null,

	load_history: function (page) {
		var data = {
			action: "load_history",
			form_id: $("#form_id").val(),
			submission_id: $("#submission_id").val(),
			page: page,
		};
		$("#sh__load_history").hide();

		if ($("#sh__pagination").length > 0) {
			$("#sh__pagination").hide();
		}
		$("#sh__loading").show();

		$.ajax({
			url: sh.page_url,
			data: data,
			type: 'POST',
			success: function (response) {
				$("#sh__results_div").html(response);
				$("#sh__loading").hide();
				if ($("sh__pagination").length > 0) {
					$("sh__pagination").show();
				}
			},
			error: function (a, b, c) {
				$("#sh__pagination").show();
				$("#sh__loading").hide();
			}
		});
	},

	// called when the user clicks a "View History" link
	view_history_changes: function (history_id) {
		if (sh.last_content == null) {
			sh.last_content = $("#sh__results_div").html();
		}

		$("#sh__loading").show();
		$.ajax({
			url: sh.page_url,
			data: {
				action: "view_history_changes",
				form_id: $("#form_id").val(),
				history_id: history_id
			},
			type: "POST",
			success: function (response) {
				//console.log(response);
				$("#sh__results_div").html(response);
				$("#sh__loading").hide();
			}
		});
	},

	back_to_history: function () {
		$("#sh__results_div").html(sh.last_content);
		sh.last_content = null;
	},

	restore: function (history_id) {
		$("#sh__loading").show();
		$.ajax({
			url: sh.page_url,
			data: {
				action: "restore",
				form_id: $("#form_id").val(),
				history_id: history_id
			},
			type: "POST",

			// after reverting, just refresh the page
			success: function (transport) {
				window.location = "edit_submission.php?form_id=" + $("#form_id").val() + "&submission_id=" + $("#submission_id").val();
			}
		});
	},

	clear_submission_log: function () {
		$("#sh__loading").show();
		$.ajax({
			url: sh.page_url,
			data: {
				action: "clear_submission_log",
				form_id: $("#form_id").val(),
				submission_id: $("#submission_id").val()
			},
			type: "POST",

			// after reverting, just refresh the page
			success: function (response) {
				window.location = "edit_submission.php?form_id=" + $("#form_id").val() + "&submission_id=" + $("#submission_id").val();
			}
		});
	}
}
