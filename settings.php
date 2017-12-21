<?php

require_once("../../global/library.php");

use FormTools\Modules;

$module = Modules::initModulePage("admin");

$success = true;
$message = "";
if (isset($_POST["update"])) {
    list($success, $message) = $module->updateSettings($_POST);
}

$page_vars = array(
    "g_success" => $success,
    "g_message" => $message,
    "module_settings" => $module->getSettings()
);

$module->displayPage("templates/settings.tpl", $page_vars);
