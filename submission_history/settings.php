<?php

require_once("../../global/library.php");
ft_init_module_page();

if (isset($_POST["update"]))
	list($g_success, $g_message) = sh_update_settings($_POST);

$module_settings = ft_get_module_settings();

// ------------------------------------------------------------------------------------------------

$page_vars = array();
$page_vars["module_settings"] = $module_settings;

ft_display_module_page("templates/settings.tpl", $page_vars);
