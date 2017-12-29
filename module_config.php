<?php

$STRUCTURE = array();


$HOOKS = array(
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Fields::addFormFieldsAdvanced",
        "hook_function"   => "hookAddFormFields",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Fields::deleteFormFields",
        "hook_function"   => "hookDeleteFormFields",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Forms::finalizeForm",
        "hook_function"   => "hookFinalizeForm",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "start",
        "function_name"   => "FormTools\\Forms::deleteForm",
        "hook_function"   => "hookDeleteForm",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\General::alterTableColumn",
        "hook_function"   => "renameTableColumn",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Submissions::createBlankSubmission",
        "hook_function"   => "hookCreateBlankSubmission",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Submissions::processFormSubmission",
        "hook_function"   => "hookProcessForm",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "start",
        "function_name"   => "FormTools\\Submissions::deleteSubmission",
        "hook_function"   => "hookDeleteSubmission",
        "priority"        => "50"
    ),
 array(
        "hook_type"       => "code",
        "action_location" => "start",
        "function_name"   => "FormTools\\Submissions::deleteSubmissions",
        "hook_function"   => "hookDeleteSubmissions",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Submissions::updateSubmission",
        "hook_function"   => "hookUpdateSubmission",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "start",
        "function_name"   => "FormTools\\Submissions::updateSubmission",
        "hook_function"   => "hookUpdateSubmissionInit",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Modules\\FormBackup\\General::duplicateForm",
        "hook_function"   => "hookOnFormBackup",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "end",
        "function_name"   => "FormTools\\Modules\\FieldTypeFile\\Module->deleteFileSubmission",
        "hook_function"   => "hookDeleteFileSubmission",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "template",
        "action_location" => "admin_edit_submission_bottom",
        "function_name"   => "",
        "hook_function"   => "hookDisplaySubmissionChangelog",
        "priority"        => "50"
    ),
    array(
        "hook_type"       => "code",
        "action_location" => "main",
        "function_name"   => "FormTools\\Themes::displayPage",
        "hook_function"   => "hookIncludeModuleResources",
        "priority"        => "50"
    )
);


$FILES = array(
    "code/",
    "code/actions.php",
    "code/Code.class.php",
    "code/General.class.php",
    "code/Module.class.php",
    "css/",
    "css/styles.css",
    "images/",
    "images/icon_submission_history.gif",
    "images/loading.gif",
    "lang/",
    "lang/en_us.php",
    "scripts/",
    "scripts/scripts.js",
    "templates/",
    "templates/admin_edit_submission.tpl",
    "templates/ajax_pagination.tpl",
    "templates/help.tpl",
    "templates/index.tpl",
    "templates/list_history.tpl",
    "templates/settings.tpl",
    "templates/undelete.tpl",
    "templates/view_change_history.tpl",
    "templates/view_deleted_submission.tpl",
    "help.php",
    "index.php",
    "library.php",
    "module.php",
    "module_config.php",
    "settings.php",
    "undelete.php",
    "view_deleted_submission.php"
);
