var sh = {
  page_url: g.root_url + "/modules/submission_history/global/code/actions.php",
  last_content: null,

  load_history: function(page)
  {
    var params = {
      action: "load_history",
      form_id: $("form_id").value,
      submission_id: $("submission_id").value,
      page: page,
    }
    $("sh__load_history").hide();

    if ($("sh__pagination"))
      $("sh__pagination").hide();

    $("sh__loading").show();

    new Ajax.Request(sh.page_url, {
      parameters: params,
      method: 'post',
      onSuccess: function(transport)
      {
        $("sh__results_div").innerHTML = transport.responseText;
        $("sh__loading").hide();
        if ($("sh__pagination"))
          $("sh__pagination").show();
      },
      onFailure: function(transport)
      {
        $("sh__pagination").show();
        $("sh__loading").hide();
      }
    });
  },

  // called when the user clicks a "View History" link
  view_history_changes: function(history_id)
  {
  if (sh.last_content == null)
      sh.last_content = $("sh__results_div").innerHTML;

    $("sh__loading").show();
    new Ajax.Request(sh.page_url, {
      parameters: {
        action: "view_history_changes",
        form_id: $("form_id").value,
        history_id: history_id
      },
      method: 'post',
      onSuccess: function(transport)
      {
        $("sh__results_div").innerHTML = transport.responseText;
        $("sh__loading").hide();
      }
    });
  },

  back_to_history: function()
  {
    $("sh__results_div").innerHTML = sh.last_content;
    sh.last_content = null;
  },

  restore: function(history_id)
  {
    $("sh__loading").show();
    new Ajax.Request(sh.page_url, {
      parameters: {
        action: "restore",
        form_id: $("form_id").value,
        history_id: history_id
      },
      method: 'post',

      // after reverting, just refresh the page
      onSuccess: function(transport)
      {
        window.location = "edit_submission.php?form_id=" + $("form_id").value + "&submission_id=" + $("submission_id").value;
      }
    });
  },

  clear_submission_log: function()
  {
    $("sh__loading").show();
    new Ajax.Request(sh.page_url, {
      parameters: {
        action: "clear_submission_log",
        form_id: $("form_id").value,
        submission_id: $("submission_id").value
      },
      method: 'post',

      // after reverting, just refresh the page
      onSuccess: function(transport)
      {
        window.location = "edit_submission.php?form_id=" + $("form_id").value + "&submission_id=" + $("submission_id").value;
      }
    });
  }
}
