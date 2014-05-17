<?php


/**
 * This function creates a history table for an existing form. The new table contains the same fields as the original form
 * but also include 6 new fields:
 *    sh___history_id  - the new primary key, with auto-increment
 *    sh___change_date - the date when the change took place
 *    sh___change_type - ENUM: "new", "update", "delete", "restore", "undelete", "original", "submission"
 *    sh___change_account_type - "admin", "client", "submission" (submission = compatibility with Submission Accounts module)
 *    sh___change_account_id   - the admin or client ID, or the submission ID
 *    sh___changes_fields - a comma delimited list of fields that have changed
 *
 * The sh___ prefix attempts to reduce the likelihood of conflicts with the existing column names. The new table also may the
 * columns ordered differently to the source tab.
 */
function sh_create_history_table($form_id)
{
  global $g_table_prefix, $g_field_sizes;

  // this returns all fields in the database table except for is_finalized
  $fields = ft_get_form_fields($form_id);

  $query = "
    CREATE TABLE {$g_table_prefix}form_{$form_id}_history (
      sh___history_id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
      PRIMARY KEY(sh___history_id),
      sh___change_date DATETIME NOT NULL,
      sh___change_type ENUM ('new','update','delete','undelete','restore','original','submission'),
      sh___change_account_type ENUM ('admin','client','unknown'),
      sh___change_account_id MEDIUMINT,
      sh___changed_fields MEDIUMTEXT,
      submission_id MEDIUMINT NOT NULL,
      submission_date DATETIME NOT NULL,
      last_modified_date DATETIME NOT NULL,
      ip_address VARCHAR(15),
        ";

  foreach ($fields as $field)
  {
    // don't add system fields (submission ID, date & IP address)
    if ($field["is_system_field"] == "yes")
      continue;

    $new_field_size_sql = $g_field_sizes[$field["field_size"]]["sql"];
    $query .= "{$field['col_name']} {$new_field_size_sql},\n";
  }

  $query .= "is_finalized ENUM('yes','no') default 'yes') DEFAULT CHARSET=utf8";

  $result = mysql_query($query);
  if (!$result)
  {
    return array(false, "There was an error creating the form history table: <b>$query</b>");
  }

  return array(true, "The account history table has been created.");
}


/**
 * This is called after initially creating the form history table. It populates the history table with the existing
 * submission data.
 */
function sh_populate_history_table($form_id)
{
  global $g_table_prefix;

  $query = mysql_query("SELECT * FROM {$g_table_prefix}form_{$form_id} ORDER BY submission_id");

  if (!$query)
    return array(false, "failed_select_query", "");

  // construct the custom table column list
  $fields = ft_get_form_fields($form_id);
  $columns = array();
  foreach ($fields as $field)
    $columns[] = $field["col_name"];

  $column_str = join(", ", $columns);

  // import the data
  $error = "";
  while ($row = mysql_fetch_assoc($query))
  {
    $submission_date = $row["submission_date"];
    $insert_qry = "
      INSERT INTO {$g_table_prefix}form_{$form_id}_history
        (sh___change_date, sh___change_type, sh___change_account_type, sh___change_account_id, $column_str, is_finalized)
      VALUES ('$submission_date', 'new', 'unknown', NULL,
        ";

    foreach ($fields as $field)
    {
      $column_name = $field["col_name"];
      $insert_qry .= "'" . ft_sanitize($row[$column_name]) . "', ";
    }

    $insert_qry .= "'{$row["is_finalized"]}')";
    if (!mysql_query($insert_qry))
    {
      $error = mysql_error();
      return array(false, "failed_insertion_query", $error);
    }
  }

  return array(true, "", "");
}


/**
 * Returns the last row for a submission.
 */
function sh_get_last_submission_history_row($form_id, $submission_id)
{
  global $g_table_prefix;

  $query = mysql_query("
    SELECT *
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  submission_id = $submission_id
    ORDER BY sh___history_id DESC
    LIMIT 1
    ");

  return mysql_fetch_assoc($query);
}


/**
 * This function returns a little info about all history tables: the total size of the table (in KB)
 * and the number of rows. This is really just to give the user an idea of how large the logs are getting.
 *
 * @return array a hash of table names to table data (size and rows)
 */
function sh_get_history_table_info()
{
  global $g_table_prefix;

  $query = mysql_query("SHOW TABLE STATUS");

  $tables = array();
  while ($row = mysql_fetch_assoc($query))
  {
    if (preg_match("/{$g_table_prefix}form_(\d+)_history/", $row['Name'], $matches))
    {
      $total_size = ($row["Data_length"] + $row[ "Index_length" ]) / 1024;
      $tables[$row['Name']] = array(
        "size" => sprintf("%.2f", $total_size),
        "rows" => $row['Rows']
      );
    }
  }

  return $tables;
}


function sh_update_activity_tracking($info)
{
  global $L;

  $settings = array("tracked_form_ids" => implode(",", $info["tracked_form_ids"]));
  ft_set_module_settings($settings);

  return array(true, $L["notify_activity_tracking_updated"]);
}


/**
 * This is a general function to add a new row to a form's history table. This is called when adding,
 * updating or deleting a submission.
 *
 * @param integer $form_id
 * @param integer $submission_id
 * @param string $change_type "new", "delete", "update", "undelete" - self-explanatory
 *                            "restore"    - when the admin restores an older version from the history
 *                            "original"   - fringe case. When the user updates a record that wasn't in the DB
 *                                           before. It creates two history items: the "original" and the new
 *                                           updated version.
 *                            "submission" - updated by the Submission Accounts module
 * @param array $data contains the latest & greatest submission data to be added to the history table
 */
function sh_add_history_row($form_id, $submission_id, $change_type, $data)
{
  global $g_table_prefix, $g_sh_debug;

  if (!sh_is_tracking_form($form_id))
    return;

  $now = ft_get_current_datetime();
  list($account_type, $account_id) = sh_get_current_account_info();

  $col_values = array(
    "sh___change_date" => $now,
    "sh___change_type" => $change_type,
    "sh___change_account_type" => $account_type,
    "sh___change_account_id" => $account_id
  );

  while (list($col, $value) = each($data))
  {
    // ignore any special fields, or the last_modified_date
    if (in_array($col, array("sh___change_date", "sh___change_type", "sh___change_account_type",
        "sh___change_account_id", "sh___changed_fields", "sh___history_id")))
      continue;

    $col_values[$col] = $value;
  }
  reset($data);

  // if this is an "update" or "restore" record, we're interested in what fields just changed.
  // "new" ones are brand new, ergo all/no fields have changed (depending on how you look at it)
  // and "delete"'s are merely removing them, not changing the content
  $changed_fields = array();
  if ($change_type == "update" || $change_type == "restore" || $change_type == "submission")
  {
    $last_history_row_data = sh_get_last_submission_history_row($form_id, $submission_id);

    // if there IS no last history row data, we're not interested in what fields have changed, since
    // it will look like ALL have. Instead, we just log the new history with blank for that field
    if (!empty($last_history_row_data))
    {
      while (list($col, $value) = each($data))
      {
        // ignore any special fields
        if (in_array($col, array("sh___change_date", "sh___change_type", "sh___change_account_type",
            "sh___change_account_id", "sh___changed_fields", "sh___history_id", "last_modified_date")))
          continue;

        if ($last_history_row_data[$col] != $value)
          $changed_fields[] = $col;
      }

      // at this juncture, if $changed_fields is empty, then a user just clicked "Update" on the page without
      // actually changing anything. Don't bother logging this in the submission history.
      if (empty($changed_fields))
        return;

      reset($data);
    }
  }

  $col_values["sh___changed_fields"] = implode(",", $changed_fields);
  $cols = array();
  $vals = array();
  while (list($col, $val) = each($col_values))
  {
    $cols[] = $col;
    $vals[] = "'" . ft_sanitize($val) . "'";
  }
  $col_str = implode(", ", $cols);
  $val_str = implode(", ", $vals);

  $query = "INSERT INTO {$g_table_prefix}form_{$form_id}_history ($col_str) VALUES ($val_str)";
  $result = mysql_query($query) or die(mysql_error());

  // should never occur, but leave it be for the first release or two
  if (!$result)
  {
    echo "Problem with this query in sh_add_history_row(): " . $query . ",\n<br /> error: " . mysql_error();
    exit;
  }

  // clean up the logs
  sh_history_cleanup($form_id, $submission_id);
}


function sh_generate_history_list($info)
{
  global $g_table_prefix, $g_root_dir, $g_smarty, $g_smarty_use_sub_dirs, $LANG;

  $submission_id = $info["submission_id"];
  $form_id       = $info["form_id"];

  $module_settings = ft_get_module_settings("", "submission_history");
  $page         = (isset($info["page"])) ? $info["page"] : 1;
  $num_per_page = $module_settings["num_per_page"];
  $date_format  = $module_settings["date_format"];

  $first_item = ($page - 1) * $num_per_page;
  $limit_clause = ($num_per_page == "all") ? "" : "LIMIT $first_item, $num_per_page";

  $query = mysql_query("
    SELECT *
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  submission_id = $submission_id
    ORDER BY sh___history_id DESC
    $limit_clause
  ");

  $count_query = mysql_query("
    SELECT count(*) as c
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  submission_id = $submission_id
  ");
  $count_result = mysql_fetch_assoc($count_query);
  $total_results = $count_result["c"];

  $results = array();
  $account_ids = array();

  $cached_field_cols_to_titles = array();
  while ($row = mysql_fetch_assoc($query))
  {
    if (!in_array($row["sh___change_account_id"], $account_ids))
      $account_ids[] = $row["sh___change_account_id"];

    $row["sh___change_date"] = date($date_format, ft_convert_datetime_to_timestamp($row["sh___change_date"]));
    $row["changed_fields"]     = "";
    $row["num_changed_fields"] = 0;
    if (!empty($row["sh___changed_fields"]))
    {
      $changed_fields = explode(",", $row["sh___changed_fields"]);
      $changed_fields_arr = array();
      foreach ($changed_fields as $col_name)
      {
        if (array_key_exists($col_name, $cached_field_cols_to_titles))
          $changed_fields_arr[] = $cached_field_cols_to_titles[$col_name];
        else
          $changed_fields_arr[] = ft_get_field_title_by_field_col($form_id, $col_name);
      }
      $row["changed_fields"] = $changed_fields_arr;
      $row["num_changed_fields"] = count($row["changed_fields"]);
    }

    $results[] = $row;
  }

  $L = ft_get_module_lang_file_contents("submission_history");

  if (empty($results))
    return $L["notify_no_history"];

  $client_info = array();
  if (!empty($account_ids))
  {
    $account_id_str = implode(",", $account_ids);
    $client_query = mysql_query("
      SELECT *
      FROM   {$g_table_prefix}accounts
      WHERE  account_id IN ($account_id_str)
    ");

    while ($row = mysql_fetch_assoc($client_query))
      $client_info[$row["account_id"]] = $row;
  }

  $g_smarty->template_dir = "$g_root_dir/themes/default";
  $g_smarty->compile_dir  = "$g_root_dir/themes/default/cache";
  $g_smarty->use_sub_dirs = $g_smarty_use_sub_dirs;

  $g_smarty->assign("L", $L);
  $g_smarty->assign("LANG", $LANG);
  $g_smarty->assign("results", $results);
  $g_smarty->assign("client_info", $client_info);
  $g_smarty->assign("module_settings", $module_settings);

  $pagination_html = ($num_per_page == "all") ? "" : sh_get_dhtml_page_nav($total_results, $num_per_page, $page);
  $g_smarty->assign("pagination", $pagination_html);

  return $g_smarty->fetch("$g_root_dir/modules/submission_history/templates/list_history.tpl");
}


function sh_update_settings($info)
{
  global $g_table_prefix, $L;

  $settings = array(
    "track_new_forms"              => (isset($info["track_new_forms"]) ? $info["track_new_forms"] : "no"),
    "page_label"                   => $info["page_label"],
    "history_max_record_size"      => $info["history_max_record_size"],
    "table_max_record_size"        => $info["table_max_record_size"],
    "days_until_auto_delete"       => $info["days_until_auto_delete"],
    "auto_load_on_edit_submission" => (isset($info["auto_load_on_edit_submission"]) ? $info["auto_load_on_edit_submission"] : "no"),
    "num_per_page"                 => $info["num_per_page"],
    "date_format"                  => $info["date_format"]
  );

  ft_set_module_settings($settings);

  return array(true, $L["notify_settings_updated"]);
}


/**
 * Called in sh_add_history_row(). This ensures that the logs are kept in check, depending on
 * the settings for size & row count specified by the administrator.
 *
 * @param $form_id
 * @param $submission_id
 */
function sh_history_cleanup($form_id, $submission_id)
{
  global $g_table_prefix;

  $module_settings = ft_get_module_settings("", "submission_history");

  if (!empty($module_settings["days_until_auto_delete"]))
  {
    $days = $module_settings["days_until_auto_delete"];
    if (is_numeric($days))
    {
      @mysql_query("
        DELETE FROM {$g_table_prefix}form_{$form_id}_history
        WHERE submission_id = $submission_id AND
              DATE_SUB(curdate(), INTERVAL $days DAY) < sh___change_date
        ");
    }
  }

  // history_max_record_size - delete all but the last N most recent items for this submission
  if (!empty($module_settings["history_max_record_size"]))
  {
    $history_max_record_size = $module_settings["history_max_record_size"];
    $final_item_query = @mysql_query("
      SELECT sh___history_id
      FROM {$g_table_prefix}form_{$form_id}_history
      WHERE submission_id = $submission_id
      ORDER BY sh___history_id DESC
      LIMIT $history_max_record_size, 1
    ");

    $result = mysql_fetch_assoc($final_item_query);
    if (!empty($result))
    {
      $history_id = $result["sh___history_id"];
      @mysql_query("
        DELETE FROM {$g_table_prefix}form_{$form_id}_history
        WHERE submission_id = $submission_id AND
              sh___history_id <= $history_id
      ");
    }
  }

  // table max num records
  if (!empty($module_settings["table_max_record_size"]))
  {
    $table_max_record_size = $module_settings["table_max_record_size"];
    $count_query = @mysql_query("
      SELECT sh___history_id
      FROM {$g_table_prefix}form_{$form_id}_history
      ORDER BY sh___history_id DESC
      LIMIT $table_max_record_size, 1
    ");

    $result = mysql_fetch_assoc($count_query);
    if (!empty($result))
    {
      $history_id = $result["sh___history_id"];
      @mysql_query("
        DELETE FROM {$g_table_prefix}form_{$form_id}_history
        WHERE sh___history_id <= $history_id
      ");
    }
  }
}


function sh_get_history_item($form_id, $history_id)
{
  global $g_table_prefix;

  $form_id    = ft_sanitize($form_id);
  $history_id = ft_sanitize($history_id);
  $query = mysql_query("SELECT * FROM {$g_table_prefix}form_{$form_id}_history WHERE sh___history_id = $history_id");

  return mysql_fetch_assoc($query);
}


function sh_get_previous_history_item($form_id, $history_id)
{
  global $g_table_prefix;

  $form_id    = ft_sanitize($form_id);
  $history_id = ft_sanitize($history_id);
  $submission_id = sh_get_history_submission_id($form_id, $history_id);

  $query = mysql_query("
    SELECT *
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  sh___history_id < $history_id AND
           submission_id = $submission_id
    ORDER BY sh___history_id DESC
    LIMIT 1
  ");

  return mysql_fetch_assoc($query);
}


function sh_get_next_history_item($form_id, $history_id)
{
  global $g_table_prefix;

  $form_id    = ft_sanitize($form_id);
  $history_id = ft_sanitize($history_id);
  $submission_id = sh_get_history_submission_id($form_id, $history_id);

  $query = mysql_query("
    SELECT *
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  sh___history_id > $history_id AND
           submission_id = $submission_id
    ORDER BY sh___history_id ASC
    LIMIT 1
  ");

  return mysql_fetch_assoc($query);
}


/**
 * Generates the HTML to display for a history item. This also figures out which (if any) history IDs
 * to link to in the previous & next links.
 *
 * @param integer $history_id
 */
function sh_generate_change_list($form_id, $history_id)
{
  global $g_table_prefix, $g_root_dir, $g_smarty_use_sub_dirs, $LANG;

  $history_info = sh_get_history_item($form_id, $history_id);
  $changed_fields = explode(",", $history_info["sh___changed_fields"]);
  $changed_fields[] = "last_modified_date";
  $submission_id = $history_info["submission_id"];

  $previous_history_item_info = sh_get_previous_history_item($form_id, $history_id);
  $next_history_item_info     = sh_get_next_history_item($form_id, $history_id);
  $submission_info = ft_get_submission($form_id, $submission_id);

  $fields = array();
  while (list($col, $value) = each($history_info))
  {
    // ignore any special fields
    if (in_array($col, array("sh___change_date", "sh___change_type", "sh___change_account_type",
        "sh___change_account_id", "sh___changed_fields", "sh___history_id", "is_finalized")))
      continue;

    $curr_field_info = array(
      "has_changed"     => false,
      "field_name"      => ft_get_field_title_by_field_col($form_id, $col),
      "new_value"       => $history_info[$col],
      "previous_value"  => $previous_history_item_info[$col],
      "col_name"        => $col
    );

    foreach ($submission_info as $field_info)
    {
      if ($field_info["col_name"] == $col)
      {
        $curr_field_info["field_id"]      = $field_info["field_id"];
        $curr_field_info["field_type_id"] = $field_info["field_type_id"];
        $full_field_info = ft_get_form_field($field_info["field_id"], array("include_field_settings" => true));

        $curr_field_info = array_merge($curr_field_info, $full_field_info);
        break;
      }
    }
    if (in_array($col, $changed_fields))
      $curr_field_info["has_changed"] = true;

    $fields[] = $curr_field_info;
  }

  $module_settings = ft_get_module_settings("", "submission_history");
  $change_date = date($module_settings["date_format"], ft_convert_datetime_to_timestamp($history_info["sh___change_date"]));
  $previous_history_id = (isset($previous_history_item_info["sh___history_id"])) ? $previous_history_item_info["sh___history_id"] : "";
  $next_history_id     = (isset($next_history_item_info["sh___history_id"])) ? $next_history_item_info["sh___history_id"] : "";

  $smarty = new Smarty();
  $smarty->template_dir = "$g_root_dir/themes/default";
  $smarty->compile_dir  = "$g_root_dir/themes/default/cache/";
  $smarty->use_sub_dirs = $g_smarty_use_sub_dirs;
  $smarty->assign("LANG", $LANG);
  $smarty->assign("L", ft_get_module_lang_file_contents("submission_history"));
  $smarty->assign("fields", $fields);
  $smarty->assign("item", $history_info);
  $smarty->assign("change_date", $change_date);
  $smarty->assign("previous_history_id", $previous_history_id);
  $smarty->assign("next_history_id", $next_history_id);
  $smarty->assign("has_previous_entry", !empty($previous_history_item_info));

  // some values for the field type
  $field_types = ft_get_field_types(true);
  $smarty->assign("form_id", $form_id);
  $smarty->assign("submission_id", $submission_id);
  $smarty->assign("field_types", $field_types);
  $smarty->assign("context", "submission_history_module");

  return $smarty->fetch("$g_root_dir/modules/submission_history/templates/view_change_history.tpl");
}


/**
 * This restores the actual submission in the database to a version from the history table.
 * By and large this is just a question of copying the data over, but if any files are referenced
 * that no longer exist, those values will be emptied.
 *
 * @param integer $form_id
 * @param integer $history_id
 */
function sh_restore_submission($form_id, $history_id)
{
  global $g_table_prefix, $g_root_dir, $g_smarty_use_sub_dirs, $LANG;

  $history_info = sh_get_history_item($form_id, $history_id);
  $submission_id = $history_info["submission_id"];

  $pairs = array();
  while (list($col, $value) = each($history_info))
  {
    // ignore any special fields, or the last_modified_date
    if (in_array($col, array("sh___change_date", "sh___change_type", "sh___change_account_type",
        "sh___change_account_id", "sh___changed_fields", "sh___history_id", "last_modified_date")))
      continue;

    $field_info = ft_get_form_field_by_colname($form_id, $col);

    // if this is a file, check the file still exists
    if ($field_info["field_type"] == "file")
    {
      $field_info = ft_get_form_field($field_info["field_id"]);
      $extended_field_info = ft_get_extended_field_settings($field_id);
      $file_upload_dir = $extended_field_info["file_upload_dir"];

      if (!is_file("$file_upload_dir/$value"))
        $value = "";
    }

    $pairs[] = "$col = '" . ft_sanitize($value) . "'";
  }

  // this should always run, but wrap it in an if-statement, just in case
  if (!empty($pairs))
  {
    $pairs_str = implode(",\n", $pairs);

    $query = mysql_query("
      UPDATE {$g_table_prefix}form_{$form_id}
      SET    $pairs_str
      WHERE  submission_id = $submission_id
    ");

    // now create a new history row and set it as as "restore"
    $submission_info = ft_get_submission_info($form_id, $submission_id);
    sh_add_history_row($form_id, $submission_id, "restore", $submission_info);
  }

  return true;
}


function sh_clear_submission_log($form_id, $submission_id)
{
  global $g_table_prefix;

  $query = mysql_query("
    DELETE FROM {$g_table_prefix}form_{$form_id}_history
    WHERE  submission_id = $submission_id
  ");

  return true;
}


function sh_clear_form_logs($form_id)
{
  global $g_table_prefix, $L;

  $query = mysql_query("DELETE FROM {$g_table_prefix}form_{$form_id}_history");
  return array(true, $L["notify_form_logs_deleted"]);
}


function sh_clear_all_form_logs()
{
  global $g_table_prefix, $L;

  $forms = ft_get_forms();
  foreach ($forms as $form_info)
  {
    if ($form_info["is_complete"] == "no")
      continue;

    $form_id = $form_info["form_id"];
    $query = mysql_query("DELETE FROM {$g_table_prefix}form_{$form_id}_history");
  }

  return array(true, $L["notify_all_form_logs_deleted"]);
}


function sh_get_num_deleted_submissions($form_id)
{
  global $g_table_prefix;

  // first, grab all the deleted records
  $query = mysql_query("
    SELECT submission_id, sh___history_id h1
    FROM   {$g_table_prefix}form_{$form_id}_history h1
    WHERE  h1.sh___change_type = 'delete' AND
      h1.sh___history_id = (
             SELECT h2.sh___history_id
             FROM   {$g_table_prefix}form_{$form_id}_history h2
             WHERE h2.submission_id = h1.submission_id
             ORDER BY h2.sh___history_id DESC
             LIMIT 1
           )
    ORDER BY h1.sh___history_id DESC
  ");

  // now loop through them all, and get the list of history_ids for ONLY the last deleted version of a file
  $history_ids = array();
  $logged_submission_ids = array();
  while ($row = mysql_fetch_assoc($query))
  {
    if (in_array($row["submission_id"], $logged_submission_ids))
      continue;

    $logged_submission_ids[] = $row["submission_id"];
    $history_ids[] = $row["sh___history_id"];
  }

  return count($history_ids);
}


/**
 * Returns a (server-side) paginated list of all submissions in the history table, marked as deleted. The
 * UI provides a simple, basic UI to browse deleted submissions and undelete them. Since a single submission
 * may be deleted multiple times, this function only returns submissions that have their LAST entry marked as
 * deleted.
 *
 * @param integer $form_id
 * @param integer $page
 * @param string $search - a string to search any database field
 * @return array
 */
function sh_get_deleted_submissions($form_id, $page = 1, $search = "")
{
  global $g_table_prefix, $L;

  $module_settings = ft_get_module_settings("", "submission_history");
  $per_page = $module_settings["num_deleted_submissions_per_page"];

  // determine the LIMIT clause
  $limit_clause = "";
  $first_item = ($page - 1) * $per_page;
  $limit_clause = "LIMIT $first_item, $per_page";

  $search = ft_sanitize($search);

  $search_clause = "";
  if (!empty($search))
  {
    $history_table_col_names = sh_get_history_table_col_names($form_id);

    // remove all the Submission History-specific tables so we can do a clean comparison
    array_splice($history_table_col_names, array_search("sh___history_id", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_date", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_type", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_account_type", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_account_id", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___changed_fields", $history_table_col_names), 1);

    foreach ($history_table_col_names as $col_name)
      $search_clauses[] = "$col_name LIKE '%$search%'";

    $search_clause = "AND (" . implode(" OR ", $search_clauses) . ")";
  }

  // first, grab all log entries marked as deleted that DON'T have any newer entries
  $query = mysql_query("
    SELECT *
    FROM   {$g_table_prefix}form_{$form_id}_history h1
    WHERE  h1.sh___change_type = 'delete' AND
           h1.sh___history_id = (
             SELECT h2.sh___history_id
             FROM   {$g_table_prefix}form_{$form_id}_history h2
             WHERE h2.submission_id = h1.submission_id
             ORDER BY h2.sh___history_id DESC
             LIMIT 1
           )
    ORDER BY sh___history_id DESC
  ");


  // now loop through them all, and get the list of history_ids for ONLY the last deleted version of a file
  $history_ids = array();
  $logged_submission_ids = array();
  while ($row = mysql_fetch_assoc($query))
  {
    if (in_array($row["submission_id"], $logged_submission_ids))
      continue;

    $logged_submission_ids[] = $row["submission_id"];
    $history_ids[] = $row["sh___history_id"];
  }

  $history_id_str = implode(",", $history_ids);

  // now do our main query, including the searches, etc
  if (empty($history_ids))
  {
    $return_hash["results"]     = array();
    $return_hash["num_results"] = 0;
  }
  else
  {
    $query = mysql_query("
      SELECT *
      FROM   {$g_table_prefix}form_{$form_id}_history
      WHERE  sh___change_type = 'delete' AND
             sh___history_id IN ($history_id_str)
             $search_clause
      ORDER BY sh___history_id DESC
             $limit_clause
    ");

    $info = array();
    while ($row = mysql_fetch_assoc($query))
      $info[] = $row;

    $count_result = mysql_query("
      SELECT count(*) as c
      FROM   {$g_table_prefix}form_{$form_id}_history
      WHERE  sh___change_type = 'delete'
    ");
    $count_hash = mysql_fetch_assoc($count_result);

    $return_hash["results"]     = $info;
    $return_hash["num_results"] = $count_hash["c"];
  }

  return $return_hash;
}


function sh_get_previous_deleted_submission($form_id, $info)
{
  global $g_table_prefix;

  $history_id = $info["history_id"];

  $search_clause = "";
  if (!empty($info["search"]))
  {
    $search = ft_sanitize($info["search"]);
    $history_table_col_names = sh_get_history_table_col_names($form_id);

    // remove all the Submission History-specific tables so we can do a clean comparison
    array_splice($history_table_col_names, array_search("sh___history_id", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_date", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_type", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_account_type", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_account_id", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___changed_fields", $history_table_col_names), 1);

    foreach ($history_table_col_names as $col_name)
      $search_clauses[] = "$col_name LIKE '%$search%'";

    $search_clause = "AND (" . implode(" OR ", $search_clauses) . ")";
  }

  $query = mysql_query("
    SELECT sh___history_id
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  sh___change_type = 'delete' AND
           sh___history_id < $history_id
           $search_clause
    ORDER BY sh___history_id DESC
    LIMIT 1
  ");
  $result = mysql_fetch_assoc($query);

  return (!empty($result["sh___history_id"])) ? $result["sh___history_id"] : "";
}


function sh_get_next_deleted_submission($form_id, $info)
{
  global $g_table_prefix;

  $history_id = $info["history_id"];

  $search_clause = "";
  if (!empty($info["search"]))
  {
    $search = ft_sanitize($info["search"]);
    $history_table_col_names = sh_get_history_table_col_names($form_id);

    // remove all the Submission History-specific tables so we can do a clean comparison
    array_splice($history_table_col_names, array_search("sh___history_id", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_date", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_type", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_account_type", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___change_account_id", $history_table_col_names), 1);
    array_splice($history_table_col_names, array_search("sh___changed_fields", $history_table_col_names), 1);

    foreach ($history_table_col_names as $col_name)
      $search_clauses[] = "$col_name LIKE '%$search%'";

    $search_clause = "AND (" . implode(" OR ", $search_clauses) . ")";
  }

  $query = mysql_query("
    SELECT sh___history_id
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  sh___change_type = 'delete' AND
           sh___history_id > $history_id
           $search_clause
    ORDER BY sh___history_id ASC
    LIMIT 1
  ");
  $result = mysql_fetch_assoc($query);

  return (!empty($result["sh___history_id"])) ? $result["sh___history_id"] : "";
}


/**
 * This actually undeletes a submission and logs a new "undeleted" entry in the submission history table.
 *
 * @param integer $form_id
 * @param integer $history_id
 */
function sh_undelete_submission($form_id, $history_id)
{
  global $g_table_prefix, $L;

  $submission_id = sh_get_history_submission_id($form_id, $history_id);
  $data = sh_get_last_submission_history_row($form_id, $submission_id);

  while (list($col, $value) = each($data))
  {
    // ignore any special fields, or the last_modified_date
    if (in_array($col, array("sh___change_date", "sh___change_type", "sh___change_account_type",
        "sh___change_account_id", "sh___changed_fields", "sh___history_id")))
      continue;

    $col_values[$col] = "'" . ft_sanitize($value) . "'";
  }

  $col_names = implode(",", array_keys($col_values));
  $values    = implode(",", array_values($col_values));

  $query = "INSERT INTO {$g_table_prefix}form_{$form_id} ($col_names) VALUES ($values)";
  $result = @mysql_query($query);
  if ($query)
  {
    $submission_info = ft_get_submission_info($form_id, $submission_id);
    sh_add_history_row($form_id, $submission_id, "undelete", $submission_info);
    return array(true, $L["notify_submission_undeleted"]);
  }
  else
  {
    return array(true, $L["notify_submission_not_undeleted"]);
  }
}

