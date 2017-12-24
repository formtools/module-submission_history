<?php

require_once("../../global/library.php");

use FormTools\General;
use FormTools\Modules;
use FormTools\Modules\SubmissionHistory\Code;

$module = Modules::initModulePage("admin");
$L = $module->getLangStrings();

$module_settings = $module->getSettings("", "submission_history");
$form_id = Modules::loadModuleField("submission_history", "form_id", "form_id");
$page = Modules::loadModuleField("submission_history", "page", "page", 1);
$search = Modules::loadModuleField("submission_history", "search", "search");

if (empty($form_id)) {
    header("location: index.php");
    exit;
}

$success = true;
$message = "";
if (isset($_POST["undelete"])) {
    list($success, $message) = Code::undeleteSubmission($form_id, $_POST["history_id"], $L);
}

$deleted_submissions = Code::getDeletedSubmissions($form_id, $page, $search);

$page_vars = array(
    "g_success" => $success,
    "g_message" => $message,
    "pagination" => General::getPageNav($deleted_submissions["num_results"], $module_settings["num_deleted_submissions_per_page"], $page),
    "num_results" => $deleted_submissions["num_results"],
    "deleted_submissions" => $deleted_submissions["results"],
    "module_settings" => $module_settings,
    "search" => $search
);

$module->displayPage("templates/undelete.tpl", $page_vars);
