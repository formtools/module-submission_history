<?php

/**
 * Our installation function. Assigns all the appropriate hooks and sets the default settings.
 */
function submission_history__install()
{
  global $g_table_prefix;

  // these hook shadow the core functions so that any time the form tables change, the history table columns
  // are also updated accordingly
  ft_register_hook("code", "submission_history", "end", "ft_add_form_fields", "sh_hook_add_form_fields");
  ft_register_hook("code", "submission_history", "end", "ft_delete_form_fields", "sh_hook_delete_form_fields");
  ft_register_hook("code", "submission_history", "end", "ft_update_form_database_tab", "sh_hook_update_form_database_tab");
  ft_register_hook("code", "submission_history", "end", "ft_finalize_form", "sh_hook_finalize_form");
  ft_register_hook("code", "submission_history", "start", "ft_delete_form", "sh_hook_delete_form");

  // submissions
  ft_register_hook("code", "submission_history", "end", "ft_create_blank_submission", "sh_hook_create_blank_submission");
  ft_register_hook("code", "submission_history", "end", "ft_process_form", "sh_hook_process_form");
  ft_register_hook("code", "submission_history", "start", "ft_delete_submission", "sh_hook_delete_submission");
  ft_register_hook("code", "submission_history", "start", "ft_delete_submissions", "sh_hook_delete_submissions");
  ft_register_hook("code", "submission_history", "end", "ft_update_submission", "sh_hook_update_submission");
  ft_register_hook("code", "submission_history", "start", "ft_update_submission", "sh_hook_update_submission_init");
  ft_register_hook("code", "submission_history", "end", "ft_delete_file_submission", "sh_hook_delete_file_submission");

  // display the submission history on the administrator's Edit Submission page
  ft_register_hook("template", "submission_history", "admin_edit_submission_bottom", "", "sh_hook_display_submission_changelog");
  ft_register_hook("code", "submission_history", "main", "ft_display_page", "sh_hook_include_module_resources");


  // our create table query
  $queries = array();
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('history_tables_created', 'no', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('track_new_forms', 'yes', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('tracked_form_ids', '', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('history_max_record_size', '50', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('table_max_record_size', '', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('days_until_auto_delete', '', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('num_per_page', '10', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('date_format', 'M jS, Y g:i A', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('auto_load_on_edit_submission', 'no', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('page_label', 'Submission History', 'submission_history')";
  $queries[] = "INSERT INTO {$g_table_prefix}settings (setting_name, setting_value, module) VALUES ('num_deleted_submissions_per_page', '10', 'submission_history')";

  $has_problem = false;
  foreach ($queries as $query)
  {
    $result = @mysql_query($query);
  }

  return array(true, "");
}


/**
 * Our uninstallation function.
 *
 * @param $module_id
 */
function submission_history__uninstall($module_id)
{
  global $g_table_prefix;

  // delete all settings
  $query = mysql_query("DELETE FROM {$g_table_prefix}settings WHERE module = 'submission_history'");

  // delete all history tables
  $forms = ft_get_forms();
  foreach ($forms as $form_info)
  {
    if ($form_info["is_complete"] == "no")
      continue;

    $form_id = $form_info["form_id"];
    @mysql_query("DROP TABLE {$g_table_prefix}form_{$form_id}_history");
  }

  return array(true, "");
}
