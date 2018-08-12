<?php

namespace FormTools\Modules\SubmissionHistory;

use FormTools\Core;
use FormTools\Fields;
use FormTools\FieldSizes;
use FormTools\FieldTypes;
use FormTools\Files;
use FormTools\Forms;
use FormTools\General as CoreGeneral;
use FormTools\Modules;
use FormTools\Settings;
use FormTools\Submissions;

use PDO, Exception, Smarty;


class Code
{
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
    public static function createHistoryTable($form_id)
    {
        $db = Core::$db;
        $field_sizes = FieldSizes::get();

        // this returns all fields in the database table except for is_finalized
        $fields = Fields::getFormFields($form_id);

        $query = "
            CREATE TABLE {PREFIX}form_{$form_id}_history (
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

        foreach ($fields as $field) {
            // don't add system fields (submission ID, date & IP address)
            if ($field["is_system_field"] == "yes") {
                continue;
            }

            $new_field_size_sql = $field_sizes[$field["field_size"]]["sql"];
            $query .= "{$field['col_name']} {$new_field_size_sql},\n";
        }

        $query .= "is_finalized ENUM('yes','no') default 'yes') DEFAULT CHARSET=utf8";

        try {
            $db->query($query);
            $db->execute();
        } catch (Exception $e) {
            return array(false, "There was an error creating the form history table: <b>$query</b> - " . $e->getMessage());
        }

        return array(true, "The history table has been created.");
    }


    /**
     * This is called after initially creating the form history table. It populates the history table with the existing
     * submission data.
     */
    public function populateHistoryTable($form_id)
    {
        $db = Core::$db;

        try {
            $db->query("SELECT * FROM {PREFIX}form_{$form_id} ORDER BY submission_id");
            $db->execute();
        } catch (Exception $e) {
            return array(false, "failed_select_query", "");
        }
        $results = $db->fetchAll();

        // construct the custom table column list
        $fields = Fields::getFormFields($form_id);

        $placeholders = array();
        $columns = array();
        foreach ($fields as $field) {
            $column_name = $field["col_name"];
            $placeholders[] = ":" . $column_name;
            $columns[] = $field["col_name"];
        }
        $placeholders[] = ":is_finalized";

        $column_str = join(", ", $columns);

        // import the data
        foreach ($results as $row) {

            $insert_qry = "
                INSERT INTO {PREFIX}form_{$form_id}_history
                  (sh___change_date, sh___change_type, sh___change_account_type, sh___change_account_id, $column_str, is_finalized)
                VALUES (:submission_date, 'new', 'unknown', NULL,
            ";

            $map = array(
                "submission_date" => $row["submission_date"],
                "is_finalized" => $row["is_finalized"]
            );
            foreach ($fields as $field) {
                $column_name = $field["col_name"];
                $map[$column_name] = $row[$column_name];
            }

            $insert_qry .= implode(", ", $placeholders);

            try {
                $db->query($insert_qry);
                $db->bindAll($map);
                $db->execute();
            } catch (Exception $e) {
                return array(false, "failed_insertion_query", $e->getMessage());
            }
        }

        return array(true, "", "");
    }


    /**
     * Returns the last row for a submission.
     */
    public static function getLastSubmissionHistoryRow($form_id, $submission_id)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  submission_id = :submission_id
            ORDER BY sh___history_id DESC
            LIMIT 1
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();

        return $db->fetch();
    }


    /**
     * This function returns a little info about all history tables: the total size of the table (in KB)
     * and the number of rows. This is really just to give the user an idea of how large the logs are getting.
     *
     * @return array a hash of table names to table data (size and rows)
     */
    public static function getHistoryTableInfo()
    {
        $db = Core::$db;
        $prefix = Core::getDbTablePrefix();

        $db->query("SHOW TABLE STATUS");
        $db->execute();

        $tables = array();
        foreach ($db->fetchAll() as $row) {
            if (preg_match("/{$prefix}form_(\d+)_history/", $row['Name'], $matches)) {
                $total_size = ($row["Data_length"] + $row["Index_length"]) / 1024;
                $tables[$row['Name']] = array(
                    "size" => sprintf("%.2f", $total_size),
                    "rows" => $row['Rows']
                );
            }
        }

        return $tables;
    }


    public static function updateActivityTracking($info, $L)
    {
        $tracked_form_ids = isset($info["tracked_form_ids"]) ? implode(",", $info["tracked_form_ids"]) : "";
        Modules::setModuleSettings(array(
            "tracked_form_ids" => $tracked_form_ids
        ));

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
    public static function addHistoryRow($form_id, $submission_id, $change_type, $data)
    {
        $db = Core::$db;

        if (empty($data)) {
            return;
        }

        if (!General::isTrackingForm($form_id)) {
            return;
        }

        $now = CoreGeneral::getCurrentDatetime();
        list($account_type, $account_id) = General::getCurrentAccountInfo();

        $col_values = array(
            "sh___change_date" => $now,
            "sh___change_type" => $change_type,
            "sh___change_account_type" => $account_type,
            "sh___change_account_id" => $account_id
        );

        while (list($col, $value) = each($data)) {
            // ignore any special fields, or the last_modified_date
            if (in_array($col, array(
                "sh___change_date",
                "sh___change_type",
                "sh___change_account_type",
                "sh___change_account_id",
                "sh___changed_fields",
                "sh___history_id"
            ))) {
                continue;
            }

            $col_values[$col] = $value;
        }
        reset($data);

        // if this is an "update" or "restore" record, we're interested in what fields just changed.
        // "new" ones are brand new, ergo all/no fields have changed (depending on how you look at it)
        // and "delete"'s are merely removing them, not changing the content
        $changed_fields = array();
        if ($change_type == "update" || $change_type == "restore" || $change_type == "submission") {
            $last_history_row_data = Code::getLastSubmissionHistoryRow($form_id, $submission_id);

            // if there IS no last history row data, we're not interested in what fields have changed, since
            // it will look like ALL have. Instead, we just log the new history with blank for that field
            if (!empty($last_history_row_data)) {
                while (list($col, $value) = each($data)) {
                    // ignore any special fields
                    if (in_array($col, array(
                        "sh___change_date",
                        "sh___change_type",
                        "sh___change_account_type",
                        "sh___change_account_id",
                        "sh___changed_fields",
                        "sh___history_id",
                        "last_modified_date"
                    ))) {
                        continue;
                    }

                    if ($last_history_row_data[$col] != $value) {
                        $changed_fields[] = $col;
                    }
                }

                // at this juncture, if $changed_fields is empty, then a user just clicked "Update" on the page without
                // actually changing anything. Don't bother logging this in the submission history.
                if (empty($changed_fields)) {
                    return;
                }

                reset($data);
            }
        }

        $col_values["sh___changed_fields"] = implode(",", $changed_fields);

        list ($col_str, $placeholders, $col_values) = Core::$db->getInsertStatementParams($col_values);
        try {
            $db->query("INSERT INTO {PREFIX}form_{$form_id}_history ($col_str) VALUES ($placeholders)");
            $db->bindAll($col_values);
            $db->execute();
        } catch (Exception $e) {
            echo "Problem with this query in sh_add_history_row(): " . $e->getMessage();
            exit;
        }

        // clean up the logs
        Code::historyCleanup($form_id, $submission_id);
    }


    public static function generateHistoryList($info, $L)
    {
        $db = Core::$db;
        $root_dir = Core::getRootDir();

        $submission_id = $info["submission_id"];
        $form_id = $info["form_id"];

        $module_settings = Modules::getModuleSettings("", "submission_history");

        $page = (isset($info["page"])) ? $info["page"] : 1;
        $num_per_page = $module_settings["num_per_page"];
        $date_format = $module_settings["date_format"];

        $first_item = ($page - 1) * $num_per_page;
        $limit_clause = ($num_per_page == "all") ? "" : "LIMIT $first_item, $num_per_page";

        $db->query("
            SELECT *
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  submission_id = :submission_id
            ORDER BY sh___history_id DESC
            $limit_clause
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();
        $rows = $db->fetchAll();

        $db->query("
            SELECT count(*)
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  submission_id = :submission_id
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();
        $total_results = $db->fetch(PDO::FETCH_COLUMN);

        $account_ids = array();

        $cached_field_cols_to_titles = array();
        $results = array();
        foreach ($rows as $row) {
            if (!in_array($row["sh___change_account_id"], $account_ids)) {
                $account_ids[] = $row["sh___change_account_id"];
            }

            $row["sh___change_date"] = date($date_format, CoreGeneral::convertDatetimeToTimestamp($row["sh___change_date"]));
            $row["changed_fields"] = "";
            $row["num_changed_fields"] = 0;

            if (!empty($row["sh___changed_fields"])) {
                $changed_fields = explode(",", $row["sh___changed_fields"]);
                $changed_fields_arr = array();
                foreach ($changed_fields as $col_name) {
                    if (array_key_exists($col_name, $cached_field_cols_to_titles)) {
                        $changed_fields_arr[] = $cached_field_cols_to_titles[$col_name];
                    } else {
                        $changed_fields_arr[] = Fields::getFieldTitleByFieldCol($form_id, $col_name);
                    }
                }
                $row["changed_fields"] = $changed_fields_arr;
                $row["num_changed_fields"] = count($row["changed_fields"]);
            }

            $results[] = $row;
        }

        if (empty($results)) {
            return $L["notify_no_history"];
        }

        $client_info = array();
        if (!empty($account_ids)) {
            $account_id_str = implode(",", $account_ids);

            $db->query("
                SELECT *
                FROM   {PREFIX}accounts
                WHERE  account_id IN ($account_id_str)
            ");
            $db->execute();

            foreach ($db->fetchAll() as $row) {
                $client_info[$row["account_id"]] = $row;
            }
        }

        $smarty = new Smarty();
        $smarty->setTemplateDir("$root_dir/themes/default");
        $smarty->setCompileDir("$root_dir/themes/default/cache");
        $smarty->setUseSubDirs(Core::shouldUseSmartySubDirs());

        $smarty->assign("L", $L);
        $smarty->assign("LANG", Core::$L);
        $smarty->assign("results", $results);
        $smarty->assign("client_info", $client_info);
        $smarty->assign("module_settings", $module_settings);

        $pagination_html = ($num_per_page == "all") ? "" : General::getDhtmlPageNav($total_results, $num_per_page, $page);

        $smarty->assign("pagination", $pagination_html);

        return $smarty->fetch("$root_dir/modules/submission_history/templates/list_history.tpl");
    }


    /**
    * Called in sh_add_history_row(). This ensures that the logs are kept in check, depending on
    * the settings for size & row count specified by the administrator.
    *
    * @param $form_id
    * @param $submission_id
    */
    public static function historyCleanup($form_id, $submission_id)
    {
        $db = Core::$db;

        $module_settings = Modules::getModuleSettings("", "submission_history");

        if (!empty($module_settings["days_until_auto_delete"])) {
            $days = $module_settings["days_until_auto_delete"];

            if (is_numeric($days)) {
                $db->query("
                    DELETE FROM {PREFIX}form_{$form_id}_history
                    WHERE submission_id = $submission_id AND
                      DATE_SUB(curdate(), INTERVAL $days DAY) < sh___change_date
                ");
                $db->execute();
            }
        }

        // history_max_record_size - delete all but the last N most recent items for this submission
        if (!empty($module_settings["history_max_record_size"])) {
            $history_max_record_size = $module_settings["history_max_record_size"];

            $db->query("
                SELECT sh___history_id
                FROM {PREFIX}form_{$form_id}_history
                WHERE submission_id = :submission_id
                ORDER BY sh___history_id DESC
                LIMIT $history_max_record_size, 1
            ");
            $db->bind("submission_id", $submission_id);
            $db->execute();

            $history_id = $db->fetch(PDO::FETCH_COLUMN);
            if (!empty($history_id)) {
                $db->query("
                    DELETE FROM {PREFIX}form_{$form_id}_history
                    WHERE submission_id = :submission_id AND
                          sh___history_id <= $history_id
                ");
                $db->bind("submission_id", $submission_id);
                $db->execute();
            }
        }

        // table max num records
        if (!empty($module_settings["table_max_record_size"])) {
            $table_max_record_size = $module_settings["table_max_record_size"];

            $db->query("
                SELECT sh___history_id
                FROM {PREFIX}form_{$form_id}_history
                ORDER BY sh___history_id DESC
                LIMIT $table_max_record_size, 1
            ");
            $db->execute();

            $history_id = $db->fetch(PDO::FETCH_COLUMN);
            if (!empty($history_id)) {
                $db->query("
                    DELETE FROM {PREFIX}form_{$form_id}_history
                    WHERE sh___history_id <= $history_id
                ");
                $db->execute();
            }
        }
    }


    public static function getHistoryItem($form_id, $history_id)
    {
        $db = Core::$db;

        $db->query("
            SELECT *
            FROM {PREFIX}form_{$form_id}_history
            WHERE sh___history_id = :history_id
        ");
        $db->bind("history_id", $history_id);
        $db->execute();

        return $db->fetch();
    }


    public static function getPreviousHistoryItem($form_id, $history_id)
    {
        $db = Core::$db;
        $submission_id = General::getHistorySubmissionId($form_id, $history_id);

        $db->query("
            SELECT *
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  sh___history_id < $history_id AND
                   submission_id = :submission_id
            ORDER BY sh___history_id DESC
            LIMIT 1
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();

        return $db->fetch();
    }


    public static function getNextHistoryItem($form_id, $history_id)
    {
        $db = Core::$db;
        $submission_id = General::getHistorySubmissionId($form_id, $history_id);

        $db->query("
            SELECT *
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  sh___history_id > $history_id AND
                   submission_id = :submission_id
            ORDER BY sh___history_id ASC
            LIMIT 1
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();

        return $db->fetch();
    }


    /**
     * Generates the HTML to display for a history item. This also figures out which (if any) history IDs
     * to link to in the previous & next links.
     *
     * @param integer $history_id
     */
    public static function generateChangeList($form_id, $history_id, $L)
    {
        $root_dir = Core::getRootDir();
        $LANG = Core::$L;

        $history_info = Code::getHistoryItem($form_id, $history_id);
        $changed_fields = explode(",", $history_info["sh___changed_fields"]);
        $changed_fields[] = "last_modified_date";
        $submission_id = $history_info["submission_id"];

        $previous_history_item_info = Code::getPreviousHistoryItem($form_id, $history_id);
        $next_history_item_info = Code::getNextHistoryItem($form_id, $history_id);
        $submission_info = Submissions::getSubmission($form_id, $submission_id);

        $fields = array();
        while (list($col, $value) = each($history_info)) {
            // ignore any special fields
            if (in_array($col, array(
                "sh___change_date",
                "sh___change_type",
                "sh___change_account_type",
                "sh___change_account_id",
                "sh___changed_fields",
                "sh___history_id",
                "is_finalized"
            ))) {
                continue;
            }

            $curr_field_info = array(
                "has_changed" => false,
                "field_name" => Fields::getFieldTitleByFieldCol($form_id, $col),
                "new_value" => $history_info[$col],
                "previous_value" => $previous_history_item_info[$col],
                "col_name" => $col
            );

            foreach ($submission_info as $field_info) {
                if ($field_info["col_name"] == $col) {
                    $curr_field_info["field_id"] = $field_info["field_id"];
                    $curr_field_info["field_type_id"] = $field_info["field_type_id"];
                    $full_field_info = Fields::getFormField($field_info["field_id"],
                        array("include_field_settings" => true));

                    $curr_field_info = array_merge($curr_field_info, $full_field_info);
                    break;
                }
            }
            if (in_array($col, $changed_fields)) {
                $curr_field_info["has_changed"] = true;
            }

            $fields[] = $curr_field_info;
        }

        $module_settings = Modules::getModuleSettings("", "submission_history");
        $change_date = date($module_settings["date_format"], CoreGeneral::convertDatetimeToTimestamp($history_info["sh___change_date"]));
        $previous_history_id = (isset($previous_history_item_info["sh___history_id"])) ? $previous_history_item_info["sh___history_id"] : "";
        $next_history_id = (isset($next_history_item_info["sh___history_id"])) ? $next_history_item_info["sh___history_id"] : "";

        $smarty = new Smarty();
        $smarty->setTemplateDir("$root_dir/themes/default");
        $smarty->setCompileDir("$root_dir/themes/default/cache/");
        $smarty->setUseSubDirs(Core::shouldUseSmartySubDirs());
        $smarty->setPluginsDir(array(
            "$root_dir/global/smarty_plugins",
            "$root_dir/modules/submission_history/smarty_plugins/"
        ));
        $smarty->assign("LANG", $LANG);
        $smarty->assign("L", $L);
        $smarty->assign("fields", $fields);
        $smarty->assign("item", $history_info);
        $smarty->assign("change_date", $change_date);
        $smarty->assign("previous_history_id", $previous_history_id);
        $smarty->assign("next_history_id", $next_history_id);
        $smarty->assign("has_previous_entry", !empty($previous_history_item_info));

        // some values for the field type
        $field_types = FieldTypes::get(true);
        $smarty->assign("form_id", $form_id);
        $smarty->assign("submission_id", $submission_id);
        $smarty->assign("field_types", $field_types);
        $smarty->assign("context", "submission_history_module");

        return $smarty->fetch("$root_dir/modules/submission_history/templates/view_change_history.tpl");
    }


    /**
     * This restores the actual submission in the database to a version from the history table.
     * By and large this is just a question of copying the data over, but if any files are referenced
     * that no longer exist, those values will be emptied.
     *
     * @param integer $form_id
     * @param integer $history_id
     */
    public static function restoreSubmission($form_id, $history_id)
    {
        $db = Core::$db;

        $history_info = self::getHistoryItem($form_id, $history_id);
        $submission_id = $history_info["submission_id"];
        $file_type_id = FieldTypes::getFieldTypeIdByIdentifier("file");

        $pairs = array("submission_id" => $submission_id);
        $col_names = array();
        foreach ($history_info as $col_name => $value) {

            // ignore any special fields
            if (in_array($col_name, array(
                "sh___change_date",
                "sh___change_type",
                "sh___change_account_type",
                "sh___change_account_id",
                "sh___changed_fields",
                "sh___history_id",
                "last_modified_date",
                "is_finalized",
                "submission_id"
            ))) {
                continue;
            }

            $field_info = Fields::getFormFieldByColname($form_id, $col_name);

            if (empty($field_info)) {
                echo $col_name;
            }
            // if this is a file, check the file still exists
            if ($field_info["field_type_id"] == $file_type_id) {
                $settings = Fields::getFieldSettings($field_info["field_id"]);
                $file_upload_dir = $settings["folder_path"];
                if (!is_file("$file_upload_dir/$value")) {
                    $value = "";
                }
            }

            $col_names[] = $col_name;
            $pairs[$col_name] = $value;
        }

        // this should always run, but wrap it in an if-statement, just in case
        if (!empty($pairs)) {
            $set_clauses = array();
            foreach ($col_names as $col_name) {
                $set_clauses[] = "$col_name = :$col_name";
            }
            $set_clauses_str = implode(", ", $set_clauses);

            $db->query("
                UPDATE {PREFIX}form_{$form_id}
                SET    $set_clauses_str
                WHERE  submission_id = :submission_id
            ");
            $db->bindAll($pairs);
            $db->execute();

            // now create a new history row and set it as as "restore"
            $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);
            self::addHistoryRow($form_id, $submission_id, "restore", $submission_info);
        }

        return true;
    }


    public static function clearSubmissionLog($form_id, $submission_id)
    {
        $db = Core::$db;

        $db->query("
            DELETE FROM {PREFIX}form_{$form_id}_history
            WHERE  submission_id = :submission_id
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();

        return true;
    }


    public static function clearFormLogs($form_id, $L)
    {
        $db = Core::$db;

        $db->query("DELETE FROM {PREFIX}form_{$form_id}_history");
        $db->execute();

        return array(true, $L["notify_form_logs_deleted"]);
    }


    public static function clearAllFormLogs($L)
    {
        $db = Core::$db;

        $forms = Forms::getForms();
        foreach ($forms as $form_info) {
            if ($form_info["is_complete"] == "no") {
                continue;
            }

            $form_id = $form_info["form_id"];
            $db->query("DELETE FROM {PREFIX}form_{$form_id}_history");
            $db->execute();
        }

        return array(true, $L["notify_all_form_logs_deleted"]);
    }


    public static function getNumDeletedSubmissions($form_id)
    {
        $db = Core::$db;

        // first, grab all the deleted records
        $db->query("
            SELECT submission_id, sh___history_id
            FROM   {PREFIX}form_{$form_id}_history h1
            WHERE  h1.sh___change_type = 'delete' AND
                   h1.sh___history_id = (
                    SELECT h2.sh___history_id
                    FROM   {PREFIX}form_{$form_id}_history h2
                    WHERE  h2.submission_id = h1.submission_id
                    ORDER BY h2.sh___history_id DESC
                    LIMIT 1
                  ) ORDER BY h1.sh___history_id DESC
        ");
        $db->execute();
        $rows = $db->fetchAll();

        // now loop through them all, and get the list of history_ids for ONLY the last deleted version of a file
        $history_ids = array();
        $logged_submission_ids = array();
        foreach ($rows as $row) {
            if (in_array($row["submission_id"], $logged_submission_ids)) {
                continue;
            }
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
    public static function getDeletedSubmissions($form_id, $page = 1, $search = "")
    {
        $db = Core::$db;
        $module_settings = Modules::getModuleSettings("", "submission_history");
        $per_page = $module_settings["num_deleted_submissions_per_page"];

        // determine the LIMIT clause
        $first_item = ($page - 1) * $per_page;
        $limit_clause = "LIMIT $first_item, $per_page";

        $search_clause = "";
        $query_map = array();

        if (!empty($search)) {
            $history_table_col_names = General::getHistoryTableColNames($form_id);

            // remove all the Submission History-specific tables so we can do a clean comparison
            array_splice($history_table_col_names, array_search("sh___history_id", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_date", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_type", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_account_type", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_account_id", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___changed_fields", $history_table_col_names), 1);

            foreach ($history_table_col_names as $col_name) {
                $query_map[$col_name] = "%$search%";
                $search_clauses[] = "$col_name LIKE :$col_name";
            }

            if (!empty($search_clauses)) {
                $search_clause = "AND (" . implode(" OR ", $search_clauses) . ")";
            }
        }


        // first, grab all log entries marked as deleted that DON'T have any newer entries
        $db->query("
            SELECT *
            FROM   {PREFIX}form_{$form_id}_history h1
            WHERE  h1.sh___change_type = 'delete' AND
                   h1.sh___history_id = (
                       SELECT h2.sh___history_id
                       FROM   {PREFIX}form_{$form_id}_history h2
                       WHERE h2.submission_id = h1.submission_id
                       ORDER BY h2.sh___history_id DESC
                       LIMIT 1
                   )
            ORDER BY sh___history_id DESC
        ");
        $db->execute();

        // now loop through them all, and get the list of history_ids for ONLY the last deleted version of a file
        $history_ids = array();
        $logged_submission_ids = array();
        foreach ($db->fetchAll() as $row) {
            if (in_array($row["submission_id"], $logged_submission_ids)) {
                continue;
            }

            $logged_submission_ids[] = $row["submission_id"];
            $history_ids[] = $row["sh___history_id"];
        }

        $history_id_str = implode(",", $history_ids);

        // now do our main query, including the searches, etc
        if (empty($history_ids)) {
            return array(
                "results" => array(),
                "num_results" => 0
            );
        }

        $db->query("
            SELECT *
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  sh___change_type = 'delete' AND
                   sh___history_id IN ($history_id_str)
                   $search_clause
            ORDER BY sh___history_id DESC
            $limit_clause
        ");

        $db->bindAll($query_map);
        $db->execute();
        $results = $db->fetchAll();

        $db->query("
            SELECT count(*)
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  sh___change_type = 'delete'
        ");
        $db->execute();

        return array(
            "results" => $results,
            "num_results" => $db->fetch(PDO::FETCH_COLUMN)
        );
    }


    public static function getPreviousDeletedSubmission($form_id, $info)
    {
        $db = Core::$db;
        $history_id = $info["history_id"];

        $query_map = array();
        $search_clause = "";

        if (!empty($info["search"])) {
            $history_table_col_names = General::getHistoryTableColNames($form_id);

            // remove all the Submission History-specific tables so we can do a clean comparison
            array_splice($history_table_col_names, array_search("sh___history_id", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_date", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_type", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_account_type", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_account_id", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___changed_fields", $history_table_col_names), 1);

            foreach ($history_table_col_names as $col_name) {
                $query_map[$col_name] = "%{$info["search"]}%";
                $search_clauses[] = "$col_name LIKE :$col_name";
            }

            if (!empty($search_clauses)) {
                $search_clause = "AND (" . implode(" OR ", $search_clauses) . ")";
            }
        }

        $db->query("
            SELECT sh___history_id
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  sh___change_type = 'delete' AND
                   sh___history_id < $history_id
                   $search_clause
            ORDER BY sh___history_id DESC
            LIMIT 1
        ");
        $db->bindAll($query_map);
        $db->execute();

        return $db->fetch(PDO::FETCH_COLUMN);
    }


    public static function getNextDeletedSubmission($form_id, $info)
    {
        $db = Core::$db;

        $history_id = $info["history_id"];

        $query_map = array();
        $search_clause = "";
        if (!empty($info["search"])) {
            $history_table_col_names = General::getHistoryTableColNames($form_id);

            // remove all the Submission History-specific tables so we can do a clean comparison
            array_splice($history_table_col_names, array_search("sh___history_id", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_date", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_type", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_account_type", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___change_account_id", $history_table_col_names), 1);
            array_splice($history_table_col_names, array_search("sh___changed_fields", $history_table_col_names), 1);

            foreach ($history_table_col_names as $col_name) {
                $query_map[$col_name] = "%{$info["search"]}%";
                $search_clauses[] = "$col_name LIKE :$col_name";
            }

            if (!empty($search_clauses)) {
                $search_clause = "AND (" . implode(" OR ", $search_clauses) . ")";
            }
        }

        $db->query("
            SELECT sh___history_id
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  sh___change_type = 'delete' AND
            sh___history_id > $history_id
            $search_clause
            ORDER BY sh___history_id ASC
            LIMIT 1
        ");
        $db->bindAll($query_map);
        $db->execute();

        return $db->fetch(PDO::FETCH_COLUMN);
    }


    /**
     * This actually undeletes a submission and logs a new "undeleted" entry in the submission history table.
     *
     * @param integer $form_id
     * @param integer $history_id
     */
    public static function undeleteSubmission($form_id, $history_id, $L)
    {
        $db = Core::$db;

        $submission_id = General::getHistorySubmissionId($form_id, $history_id);
        $data = self::getLastSubmissionHistoryRow($form_id, $submission_id);

        $columns = array();
        $pairs = array();
        while (list($col, $value) = each($data)) {
            // ignore any special fields, or the last_modified_date
            if (in_array($col, array(
                "sh___change_date",
                "sh___change_type",
                "sh___change_account_type",
                "sh___change_account_id",
                "sh___changed_fields",
                "sh___history_id"
            ))) {
                continue;
            }

            $columns[$col] = ":$col";
            $pairs[$col] = $value;
        }

        $col_names = implode(",", array_keys($columns));
        $placeholders = implode(",", array_values($columns));

        try {
            $db->query("INSERT INTO {PREFIX}form_{$form_id} ($col_names) VALUES ($placeholders)");
            $db->bindAll($pairs);
            $db->execute();

            $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);
            self::addHistoryRow($form_id, $submission_id, "undelete", $submission_info);
        } catch (Exception $e) {
            return array(false, $L["notify_submission_not_undeleted"]);
        }

        return array(true, $L["notify_submission_undeleted"]);
    }
}
