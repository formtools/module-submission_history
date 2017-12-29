<?php

require_once("../../global/library.php");

use FormTools\Core;
use FormTools\Forms;
use FormTools\Modules;
use FormTools\Modules\SubmissionHistory\Code;

$module = Modules::initModulePage("admin");
$root_url = Core::getRootUrl();
$L = $module->getLangStrings();

$success = true;
$message = "";
if (isset($_POST["update_activity_tracking"])) {
    list($success, $message) = Code::updateActivityTracking($_POST, $L);
} else if (isset($_GET["clear_logs"])) {
    list($success, $message) = Code::clearFormLogs($_GET["clear_logs"], $L);
} else if (isset($_POST["clear_all_logs"])) {
    list($success, $message) = Code::clearAllFormLogs($L);
}

$module_settings = $module->getSettings();
$history_table_info = Code::getHistoryTableInfo();
$all_forms = Forms::searchForms(array("status" => ""));

$forms = array();
$form_ids = array();
$table_prefix = Core::getDbTablePrefix();

foreach ($all_forms as $form_info) {
    if ($form_info["is_complete"] == "no") {
        continue;
    }

    $form_id = $form_info["form_id"];
    $form_ids[] = $form_id;

    // once the history tables have been created, there should ALWAYS be a key here
    if (array_key_exists("{$table_prefix}form_{$form_id}_history", $history_table_info)) {
        $form_info["history_table_size"] = round($history_table_info["{$table_prefix}form_{$form_id}_history"]["size"]);
        $form_info["history_table_rows"] = $history_table_info["{$table_prefix}form_{$form_id}_history"]["rows"];
        $form_info["num_deleted_submissions"] = Code::getNumDeletedSubmissions($form_id);
    }

    $forms[] = $form_info;
}
$form_ids_str = implode(",", $form_ids);

$page_vars = array(
    "g_success" => $success,
    "g_message" => $message,
    "forms" => $forms,
    "module_settings" => $module_settings,
    "configured_form_ids" => explode(",", $module_settings["tracked_form_ids"])
);

$page_vars["head_js"] = <<< END
var page_ns = {
  url:      "{$root_url}/modules/submission_history/code/actions.php",
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
END;

$module->displayPage("templates/index.tpl", $page_vars);
