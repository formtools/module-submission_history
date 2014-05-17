<?php

$folder = dirname(__FILE__);
require(realpath("$folder/../../../../global/session_start.php"));

$folder = dirname(__FILE__);
require(realpath("$folder/../../library.php"));


switch ($_POST["action"])
{
  case "create_history_table":
    $form_id = $_POST["form_id"];
    $form_info = ft_get_form($form_id);
    $form_name = preg_replace("/\"/", '\"', $form_info["form_name"]);
    list($success, $message) = sh_create_history_table($form_id);
    $json = "{ \"success\": \"$success\", \"form_id\": \"$form_id\", \"form_name\": \"$form_name\" }";
    echo $json;
    break;

  case "finalize_setup":
    $settings = array(
      "history_tables_created" => "yes",
      "tracked_form_ids"       => $_POST["form_ids_str"]
    );
    ft_set_module_settings($settings);
    echo "{ \"success\": \"1\" }";
    break;

  case "load_history":
    echo sh_generate_history_list($_POST);
    break;

  case "view_history_changes":
    echo sh_generate_change_list($_POST["form_id"], $_POST["history_id"]);
    break;

  case "restore":
    echo sh_restore_submission($_POST["form_id"], $_POST["history_id"]);
    break;

  case "clear_submission_log":
    echo sh_clear_submission_log($_POST["form_id"], $_POST["submission_id"]);
    break;
}

