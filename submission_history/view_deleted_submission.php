<?php

require_once("../../global/library.php");
ft_init_module_page();

$module_settings = ft_get_module_settings("", "submission_history");
$form_id    = ft_load_module_field("submission_history", "form_id", "form_id");
$history_id = ft_load_module_field("submission_history", "history_id", "history_id");
$page       = ft_load_module_field("submission_history", "page", "page", 1);
$search     = ft_load_module_field("submission_history", "search", "search");

if (empty($form_id) || empty($history_id))
{
  header("location: index.php");
  exit;
}

$fields = sh_get_history_item($form_id, $history_id);

$clean_fields = array();
while (list($col_name, $value) = each($fields))
{
  // ignore any special fields, or the last_modified_date
  if (in_array($col_name, array("sh___change_date", "sh___change_type", "sh___change_account_type",
    "sh___change_account_id", "sh___changed_fields", "sh___history_id", "is_finalized")))
    continue;

  $field_title = ft_get_field_title_by_field_col($form_id, $col_name);
  $clean_fields[$field_title] = $value;
}

// get the previous and next history IDs for the nav
$info = array(
  "history_id" => $history_id,
  "search"     => $search
);
$previous_history_id = sh_get_previous_deleted_submission($form_id, $info);
$next_history_id     = sh_get_next_deleted_submission($form_id, $info);


$deleted_submissions = sh_get_deleted_submissions($form_id, $page, $search);

// ------------------------------------------------------------------------------------------------

$page_vars = array();
$page_vars["pagination"] = ft_get_page_nav(count($deleted_submissions["results"]), $module_settings["num_deleted_submissions_per_page"], $page);
$page_vars["num_results"] = $deleted_submissions["num_results"];
$page_vars["deleted_submissions"] = $deleted_submissions["results"];
$page_vars["module_settings"] = $module_settings;
$page_vars["fields"] = $clean_fields;
$page_vars["search"] = $search;
$page_vars["history_id"]          = $history_id;
$page_vars["previous_history_id"] = $previous_history_id;
$page_vars["next_history_id"]     = $next_history_id;


ft_display_module_page("templates/view_deleted_submission.tpl", $page_vars);