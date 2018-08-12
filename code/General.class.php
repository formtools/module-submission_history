<?php

namespace FormTools\Modules\SubmissionHistory;

use FormTools\Accounts;
use FormTools\Core;
use FormTools\Modules;
use Smarty, SmartyBC, PDO;

class General
{

    public static function checkHistoryTableEmpty($form_id)
    {
        $db = Core::$db;

        $db->query("SELECT count(*) FROM {PREFIX}form_{$form_id}_history");
        $db->execute();

        return $db->fetch(PDO::FETCH_COLUMN);
    }


    /**
     * This functions looks in sessions to get the current account type. Possible return values are: admin, client,
     * and unknown. "Unknown" should only occur for the Submission Accounts module - and those are identified with
     * having "submission" as the change_type in the history table.
     */
    public static function getCurrentAccountInfo()
    {
        $account_type = "unknown";
        $account_id = "";

        if (Core::$user->isLoggedIn()) {
            $account_type = Core::$user->getAccountType();
            $account_id = Core::$user->getAccountId();
        }

        return array($account_type, $account_id);
    }


    /**
     * Helper function to determine if a form is being tracked or not.
     *
     * @param integer $form_id
     */
    public static function isTrackingForm($form_id)
    {
        $tracked_form_ids = Modules::getModuleSettings("tracked_form_ids", "submission_history");
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
    public static function getDhtmlPageNav($num_results, $num_per_page, $current_page = 1)
    {
        $root_dir = Core::getRootDir();

        $smarty = Core::useSmartyBC() ? new SmartyBC() : new Smarty();
        $smarty->setTemplateDir("$root_dir/themes/default");
        $smarty->setCompileDir("$root_dir/themes/default/cache/");
        $smarty->setUseSubDirs(Core::shouldUseSmartySubDirs());
        $smarty->assign("LANG", Core::$L);
        $smarty->assign("g_root_dir", $root_dir);
        $smarty->assign("g_root_url", Core::getRootUrl());
        $smarty->assign("samepage", $_SERVER["PHP_SELF"]);
        $smarty->assign("num_results", $num_results);
        $smarty->assign("num_per_page", $num_per_page);
        $smarty->assign("current_page", $current_page);
        $smarty->assign("total_pages", ceil($num_results / $num_per_page));

        return $smarty->fetch("$root_dir/modules/submission_history/templates/ajax_pagination.tpl");
    }


    /**
     * Helper function to find the a history item's submission ID.
     *
     * @param integer $form_id
     * @param integer $history_id
     */
    public static function getHistorySubmissionId($form_id, $history_id)
    {
        $db = Core::$db;

        $db->query("
            SELECT submission_id
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  sh___history_id = :history_id
        ");
        $db->bind("history_id", $history_id);
        $db->execute();

        return $db->fetch(PDO::FETCH_COLUMN);
    }


    /**
     * Returns all the column names for a history table.
     *
     * @param integer $form_id
     */
    public static function getHistoryTableColNames($form_id)
    {
        $db = Core::$db;

        $db->query("DESCRIBE {PREFIX}form_{$form_id}_history");
        $db->execute();

        $column_names = array();
        foreach ($db->fetchAll() as $row) {
            $column_names[] = $row["Field"];
        }

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
    public static function submissionHasHistory($form_id, $submission_id)
    {
        $db = Core::$db;

        $db->query("
            SELECT count(*)
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  submission_id = :submission_id
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();

        return $db->fetch(PDO::FETCH_COLUMN) > 0;
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
    public static function getLastModifiedInfo($form_id, $submission_id)
    {
        $db = Core::$db;

        $db->query("
            SELECT sh___change_account_type, sh___change_account_id
            FROM   {PREFIX}form_{$form_id}_history
            WHERE  submission_id = :submission_id
            ORDER BY sh___history_id DESC
            LIMIT 1
        ");
        $db->bind("submission_id", $submission_id);
        $db->execute();

        $return_info = array(
            "has_been_modified" => false
        );

        if ($db->numRows() === 0) {
            return $return_info;
        }

        $result = $db->fetch();
        $return_info = array(
            "has_been_modified" => true,
            "account_type" => $result["sh___change_account_type"],
            "account_id" => $result["sh___change_account_id"]
        );

        if (is_numeric($result["sh___change_account_id"])) {
            $account_info = Accounts::getAccountInfo($result["sh___change_account_id"]);
            $return_info["first_name"] = $account_info["first_name"];
            $return_info["last_name"] = $account_info["last_name"];
        }

        return $return_info;
    }
}
