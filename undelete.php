<?php

require_once("../../global/library.php");
ft_init_module_page();

$module_settings = ft_get_module_settings("", "submission_history");
$form_id = ft_load_module_field("submission_history", "form_id", "form_id");
$page    = ft_load_module_field("submission_history", "page", "page", 1);
$search  = ft_load_module_field("submission_history", "search", "search");

if (empty($form_id))
{
  header("location: index.php");
  exit;
}

if (isset($_POST["undelete"]))
{
  list($g_success, $g_message) = sh_undelete_submission($form_id, $_POST["history_id"]);
}


$deleted_submissions = sh_get_deleted_submissions($form_id, $page, $search);

// ------------------------------------------------------------------------------------------------

$page_vars = array();
$page_vars["pagination"] = ft_get_page_nav($deleted_submissions["num_results"], $module_settings["num_deleted_submissions_per_page"], $page);
$page_vars["num_results"] = $deleted_submissions["num_results"];
$page_vars["deleted_submissions"] = $deleted_submissions["results"];
$page_vars["module_settings"] = $module_settings;
$page_vars["search"] = $search;

ft_display_module_page("templates/undelete.tpl", $page_vars);