<?php

require_once(__DIR__ . "/../../../global/library.php");

use FormTools\Forms;
use FormTools\Modules;
use FormTools\Modules\SubmissionHistory\Code;

$module = Modules::initModulePage();
$L = $module->getLangStrings();

switch ($_POST["action"]) {
    case "create_history_table":
        $form_id = $_POST["form_id"];
        $form_info = Forms::getForm($form_id);
        $form_name = preg_replace("/\"/", '\"', $form_info["form_name"]);
        list($success, $message) = Code::createHistoryTable($form_id);

        echo returnJSON(array(
            "success" => $success,
            "form_id" => $form_id,
            "form_name" => $form_name
        ));
        break;

    case "finalize_setup":
        $module->setSettings(array(
            "history_tables_created" => "yes",
            "tracked_form_ids" => $_POST["form_ids_str"]
        ));
        echo returnJSON(array("success" => 1));
        break;

    case "load_history":
        echo Code::generateHistoryList($_POST, $L);
        break;

    case "view_history_changes":
        echo Code::generateChangeList($_POST["form_id"], $_POST["history_id"], $L);
        break;

    case "restore":
        echo Code::restoreSubmission($_POST["form_id"], $_POST["history_id"]);
        break;

    case "clear_submission_log":
        echo Code::clearSubmissionLog($_POST["form_id"], $_POST["submission_id"]);
        break;
}


function returnJSON($php)
{
    header("Content-Type: application/json");
    return json_encode($php);
}
