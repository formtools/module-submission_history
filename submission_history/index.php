<?php

require_once("../../global/library.php");
ft_init_module_page();

if (isset($_POST["update_activity_tracking"]))
  list($g_success, $g_message) = sh_update_activity_tracking($_POST);
else if (isset($_GET["clear_logs"]))
  list($g_success, $g_message) = sh_clear_form_logs($_GET["clear_logs"]);
else if (isset($_POST["clear_all_logs"]))
  list($g_success, $g_message) = sh_clear_all_form_logs();

$module_settings = ft_get_module_settings();
$history_table_info = sh_get_history_table_info();
$all_forms = ft_get_forms();

$forms    = array();
$form_ids = array();
foreach ($all_forms as $form_info)
{
  if ($form_info["is_complete"] == "no")
    continue;

  $form_id = $form_info["form_id"];
  $form_ids[] = $form_id;

  // once the history tables have been created, there should ALWAYS be a key here
  if (array_key_exists("{$g_table_prefix}form_{$form_id}_history", $history_table_info))
  {
    $form_info["history_table_size"] = round($history_table_info["{$g_table_prefix}form_{$form_id}_history"]["size"]);
    $form_info["history_table_rows"] = $history_table_info["{$g_table_prefix}form_{$form_id}_history"]["rows"];
    $form_info["num_deleted_submissions"] = sh_get_num_deleted_submissions($form_id);
  }

  $forms[] = $form_info;
}
$form_ids_str = implode(",", $form_ids);

// ------------------------------------------------------------------------------------------------

$page_vars = array();
$page_vars["forms"] = $forms;
$page_vars["module_settings"] = $module_settings;
$page_vars["configured_form_ids"] = explode(",", $module_settings["tracked_form_ids"]);
$page_vars["head_js"] =<<< EOF
var page_ns = {
  url:      "{$g_root_url}/modules/submission_history/global/code/actions.php",
  form_ids: [$form_ids_str],
  history_tables_created: false,

  create_history_tables: function() {
    // if the history table was created, just reload the page - it will automatically pick that info
    // up and show the appropriate data
    if (page_ns.history_tables_created) {
      window.location = "index.php";
      return;
    }
    if (!page_ns.form_ids.length) {
      ft.display_message("ft_message", false, "{$L["notify_forms_not_setup"]}");
      return;
    }

    ft.display_message("ft_message", 1, "{$L["notify_create_history_tables"]}<br /><br />");
    $("#create_history_table").attr("disabled", "disabled");
    page_ns.create_table(page_ns.form_ids[0]);
  },

  create_table: function(form_id) {
    $.ajax({
      url: page_ns.url,
      data: {
        action:  "create_history_table",
        form_id: form_id
      },
      dataType: "json",
      type:     "POST",
      success: page_ns.create_history_table_result
    });
  },

  create_history_table_result: function(info) {
    var existing_message = $("#ft_message_inner div").html();
    if (info.success == "1") {
      $("#ft_message_inner div").html(existing_message + "&bull; {$L["phrase_history_table_created_for"]} <b>" + info.form_name + "</b><br />");
    } else {
      $("#ft_message_inner div").html(existing_message + "&bull; {$L["phrase_problem_create_tables"]} <b>" + info.form_name + "</b><br />");
    }

    // find the next form ID
    var found_index = null;
    var form_id     = parseInt(info.form_id);
    for (var i=0; i<page_ns.form_ids.length; i++) {
      if (parseInt(page_ns.form_ids[i]) == form_id) {
        found_index = i;
        break;
      }
    }

    if (found_index == (page_ns.form_ids.length - 1)) {
      $.ajax({
        url: page_ns.url,
        type: "post",
        data: {
          action:       "finalize_setup",
          form_ids_str: page_ns.form_ids.toString()
        },
        success: page_ns.finalize_setup
      });
    } else {
      page_ns.create_table(page_ns.form_ids[found_index+1]);
    }
  },

  finalize_setup: function(transport) {
    page_ns.history_tables_created = true;
    var existing_message = $("#ft_message_inner div").html();
    $("#ft_message_inner div").html(existing_message + "<br />{$L["notify_history_tables_created"]}");
    $("#create_history_table").val("Continue").attr("disabled", "")
  },

  clear_logs: function(form_id) {
    if (confirm("{$L["confirm_clear_form_logs"]}")) {
      window.location = "?clear_logs=" + form_id;
    }
  }
}

EOF;

ft_display_module_page("templates/index.tpl", $page_vars);
