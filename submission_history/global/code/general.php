<?php

/**
 * Generate helper functions for the module.
 *
 * @param unknown_type $form_id
 */

// ------------------------------------------------------------------------------------------------

function sh_check_history_table_empty($form_id)
{
  global $g_table_prefix;

  $query = mysql_query("SELECT count(*) as c FROM {$g_table_prefix}form_{$form_id}_history");
  $result = mysql_fetch_assoc($query);

  return $result["c"] == 0;
}


/**
 * This functions looks in sessions to get the current account type. Possible return values are: admin, client,
 * and unknown. "Unknown" should only occur for the Submission Accounts module - and those are identified with
 * having "submission" as the change_type in the history table.
 */
function sh_get_current_account_info()
{
  $account_type = "unknown";
  $account_id   = "";

  if (isset($_SESSION["ft"]) && isset($_SESSION["ft"]["account"]["account_type"]))
  {
    $session_account_type = $_SESSION["ft"]["account"]["account_type"];
    if ($session_account_type == "admin")
      $account_type = "admin";
    else if ($session_account_type == "client")
      $account_type = "client";
  }

  if (isset($_SESSION["ft"]) && isset($_SESSION["ft"]["account"]["account_id"]))
  {
    $account_id = $_SESSION["ft"]["account"]["account_id"];
  }

  return array($account_type, $account_id);
}


/**
 * Helper function to determine if a form is being tracked or not.
 *
 * @param integer $form_id
 */
function sh_is_tracking_form($form_id)
{
  $tracked_form_ids = ft_get_module_settings("tracked_form_ids", "submission_history");
  $form_ids = explode(",", $tracked_form_ids);
  return in_array($form_id, $form_ids);
}


/**
 * Displays ajax << 1 2 3 >> navigation. It's a little kludgy, but okay. The function generates a bunch of
 * markup that contains JS links to reload the markup via Ajax to show the new page. It's a bit kludgy
 * because the pagination is itself reloaded with the table - it makes it look a little crumby. Instead,
 * it should only reload the table and manually update the pagination markup once the Ajax call is complete.
 *
 * @param integer $num_results The total number of results found.
 * @param integer $num_per_page The max number of results to list per page.
 * @param integer $current_page The current page number being examined (defaults to 1).
 */
function sh_get_dhtml_page_nav($num_results, $num_per_page, $current_page = 1)
{
  global $g_smarty_debug, $g_root_dir, $g_root_url, $LANG, $g_smarty_use_sub_dirs;

  $smarty = new Smarty();
  $smarty->template_dir = "$g_root_dir/themes/default";
  $smarty->compile_dir  = "$g_root_dir/themes/default/cache/";
  $smarty->use_sub_dirs = $g_smarty_use_sub_dirs;
  $smarty->assign("LANG", $LANG);
  $smarty->assign("g_root_dir", $g_root_dir);
  $smarty->assign("g_root_url", $g_root_url);
  $smarty->assign("samepage", $_SERVER["PHP_SELF"]);
  $smarty->assign("num_results", $num_results);
  $smarty->assign("num_per_page", $num_per_page);
  $smarty->assign("current_page", $current_page);
  $smarty->assign("total_pages", ceil($num_results / $num_per_page));

  return $smarty->fetch("$g_root_dir/modules/submission_history/templates/ajax_pagination.tpl");
}


/**
 * Helper function to find the a history item's submission ID.
 *
 * @param integer $form_id
 * @param integer $history_id
 */
function sh_get_history_submission_id($form_id, $history_id)
{
  global $g_table_prefix;

  $query = mysql_query("
    SELECT submission_id
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  sh___history_id = $history_id
  ");
  $result = mysql_fetch_assoc($query);

  return (!empty($result)) ? $result["submission_id"] : "";
}


/**
 * Returns all the column names for a history table.
 *
 * @param integer $form_id
 */
function sh_get_history_table_col_names($form_id)
{
  global $g_table_prefix;

  $query = mysql_query("DESCRIBE {$g_table_prefix}form_{$form_id}_history");
  $column_names = array();
  while ($row = mysql_fetch_assoc($query))
    $column_names[] = $row["Field"];

  return $column_names;
}


/**
 * Helper function to find out if a submission has a history or not.
 *
 * TODO - need a more efficient way to determine if a record exists (EXISTS?)
 *
 * @param integer $form_id
 * @param integer $submission_id
 */
function sh_submission_has_history($form_id, $submission_id)
{
  global $g_table_prefix;

  $query = mysql_query("
    SELECT count(*) as c
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  submission_id = $submission_id
  ");
  $result = mysql_fetch_assoc($query);

  return $result["c"] > 0;
}


/**
 * This returns information about who last modified the submission. Added in 1.1.3.
 *
 * @param integer $form_id
 * @param integer $submission_id
 * @return array has_history  - true/false
 *               account_type - admin/client/unknown
 *               account_id   - the ID of the admin/client
 *               first_name   - the admin/client last name
 *               last_name    - the admin/client last name
 */
function sh_get_last_modified_info($form_id, $submission_id)
{
  global $g_table_prefix;

  $query = mysql_query("
    SELECT sh___change_account_type, sh___change_account_id
    FROM   {$g_table_prefix}form_{$form_id}_history
    WHERE  submission_id = $submission_id
    ORDER BY     sh___history_id DESC
    LIMIT 1
  ");

  $return_info = array("has_been_modified" => false);
  if (mysql_num_rows($query) == 0)
  {
    return $return_info;
  }

  $result = mysql_fetch_assoc($query);
  $return_info = array(
    "has_been_modified" => true,
    "account_type"      => $result["sh___change_account_type"],
    "account_id"        => $result["sh___change_account_id"]
  );

  if (is_numeric($result["sh___change_account_id"]))
  {
    $account_info = ft_get_account_info($result["sh___change_account_id"]);
    $return_info["first_name"] = $account_info["first_name"];
    $return_info["last_name"] = $account_info["last_name"];
  }

  return $return_info;
}
