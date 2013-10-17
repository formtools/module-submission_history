<?php

/**
 * Our installation function. Assigns all the appropriate hooks and sets the default settings.
 */
function submission_history__install()
{
  global $g_table_prefix;

  sh_register_hooks();

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

/**
 * Our upgrade function.
 *
 * @param array $old_version
 * @param array $new_version
 */
function submission_history__update($old_version_info, $new_version_info)
{
  $old_version_date = date("Ymd", ft_convert_datetime_to_timestamp($old_version_info["module_date"]));

  if ($old_version_date < 20110731)
  {
    // somehow, I introduced a bug that indicated the history tables weren't created. Since we're upgrading, we're
    // going to assume that they are
    ft_set_settings(array("history_tables_created" => "yes"), "submission_history");
  }

  ft_unregister_module_hooks("submission_history");
  sh_register_hooks();

  return array(true, "");
}



