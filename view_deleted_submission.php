<?php

require_once("../../global/library.php");

use FormTools\Fields;
use FormTools\General;
use FormTools\Modules;
use FormTools\Modules\SubmissionHistory\Code;

$module = Modules::initModulePage("admin");
$module_settings = $module->getSettings();

$form_id = Modules::loadModuleField("submission_history", "form_id", "form_id");
$history_id = Modules::loadModuleField("submission_history", "history_id", "history_id");
$page = Modules::loadModuleField("submission_history", "page", "page", 1);
$search = Modules::loadModuleField("submission_history", "search", "search");

if (empty($form_id) || empty($history_id)) {
    header("location: index.php");
    exit;
}

$fields = Code::getHistoryItem($form_id, $history_id);

$clean_fields = array();
while (list($col_name, $value) = each($fields)) {
    // ignore any special fields, or the last_modified_date
    if (in_array($col_name, array(
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

    $field_title = Fields::getFieldTitleByFieldCol($form_id, $col_name);
    $clean_fields[$field_title] = $value;
}

// get the previous and next history IDs for the nav
$info = array(
    "history_id" => $history_id,
    "search" => $search
);
$previous_history_id = Code::getPreviousDeletedSubmission($form_id, $info);
$next_history_id = Code::getNextDeletedSubmission($form_id, $info);
$deleted_submissions = Code::getDeletedSubmissions($form_id, $page, $search);

$page_vars = array(
    "pagination" => General::getPageNav(count($deleted_submissions["results"]), $module_settings["num_deleted_submissions_per_page"], $page),
    "num_results" => $deleted_submissions["num_results"],
    "deleted_submissions" => $deleted_submissions["results"],
    "module_settings" => $module_settings,
    "fields" => $clean_fields,
    "search" => $search,
    "history_id" => $history_id,
    "previous_history_id" => $previous_history_id,
    "next_history_id" => $next_history_id
);

$module->displayPage("templates/view_deleted_submission.tpl", $page_vars);
