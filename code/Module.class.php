<?php

namespace FormTools\Modules\SubmissionHistory;

use FormTools\Core;
use FormTools\Fields;
use FormTools\FieldSizes;
use FormTools\Forms;
use FormTools\General as CoreGeneral;
use FormTools\Hooks;
use FormTools\Module as FormToolsModule;
use FormTools\Modules;
use FormTools\Settings;
use FormTools\Submissions;
use Exception;


class Module extends FormToolsModule
{
    protected $moduleName = "Submission History";
    protected $moduleDesc = "Logs all changes to form submissions, provides a panel on the administrator's Edit Submission page to browse the changes, and provides options to restore older versions and undelete submissions.";
    protected $author = "Ben Keen";
    protected $authorEmail = "ben.keen@gmail.com";
    protected $authorLink = "https://formtools.org";
    protected $version = "2.0.5";
    protected $date = "2019-05-29";
    protected $originLanguage = "en_us";

    protected $nav = array(
        "module_name" => array("index.php", false),
        "word_settings" => array("settings.php", true),
        "word_help" => array("help.php", true)
    );

    public function install($module_id)
    {
        $this->registerHooks();

        $settings = array(
            "history_tables_created" => "no",
            "track_new_forms" => "yes",
            "tracked_form_ids" => "",
            "history_max_record_size" => "50",
            "table_max_record_size" => "",
            "days_until_auto_delete" => "",
            "num_per_page" => "10",
            "date_format" => "M jS, Y g:i A",
            "auto_load_on_edit_submission" => "no",
            "page_label" => "Submission History",
            "num_deleted_submissions_per_page" => 10
        );
        Settings::set($settings, "submission_history");

        return array(true, "");
    }


    public function uninstall($module_id)
    {
        $db = Core::$db;

        Modules::deleteModuleSettings("submission_history");

        // delete all history tables
        $forms = Forms::getForms();
        foreach ($forms as $form_info) {
            if ($form_info["is_complete"] == "no") {
                continue;
            }
            $form_id = $form_info["form_id"];
            $db->query("DROP TABLE IF EXISTS {PREFIX}form_{$form_id}_history");
            $db->execute();
        }

        return array(true, "");
    }


    public function upgrade($module_id, $old_module_version)
    {
        Hooks::unregisterModuleHooks("submission_history");
        $this->registerHooks();
        return array(true, "");
    }


    public function registerHooks()
    {
        // these hook shadow the core functions so that any time the form tables change, the history table columns
        // are also updated accordingly
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Fields::addFormFieldsAdvanced", "hookAddFormFields");
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Fields::deleteFormFields", "hookDeleteFormFields");
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Forms::finalizeForm", "hookFinalizeForm");
        Hooks::registerHook("code", "submission_history", "start", "FormTools\\Forms::deleteForm", "hookDeleteForm");
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\General::alterTableColumn", "renameTableColumn");

        // submissions
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Submissions::createBlankSubmission", "hookCreateBlankSubmission");
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Submissions::processFormSubmission", "hookProcessForm");
        Hooks::registerHook("code", "submission_history", "start", "FormTools\\Submissions::deleteSubmission", "hookDeleteSubmission");
        Hooks::registerHook("code", "submission_history", "start", "FormTools\\Submissions::deleteSubmissions", "hookDeleteSubmissions");
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Submissions::updateSubmission", "hookUpdateSubmission");
        Hooks::registerHook("code", "submission_history", "start", "FormTools\\Submissions::updateSubmission", "hookUpdateSubmissionInit");

        // module integration
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Modules\\FieldTypeFile\\Module->deleteFileSubmission", "hookDeleteFileSubmission");
        Hooks::registerHook("code", "submission_history", "end", "FormTools\\Modules\\FormBackup\\General::duplicateForm", "hookOnFormBackup");

        // display the submission history on the administrator's Edit Submission page
        Hooks::registerHook("template", "submission_history", "admin_edit_submission_bottom", "", "hookDisplaySubmissionChangelog");
        Hooks::registerHook("code", "submission_history", "main", "FormTools\\Themes::getPage", "hookIncludeModuleResources");
    }


    /**
     * This hook is called after the administrator adds one or more fields to a form. Rather than re-doing
     * the work done by ft_add_form_fields, this just examines the content of the form table and adds those
     * columns not already in the history table.
     *
     * @param $postdata
     */
    public function hookAddFormFields($postdata)
    {
        $form_id = $postdata["form_id"];
        $field_sizes = FieldSizes::get();

        // get column names of form table
        $col_name_hash = Forms::getFormColumnNames($form_id);
        $original_col_names = array_keys($col_name_hash);

        // get column names of history table
        $history_table_col_names = General::getHistoryTableColNames($form_id);

        // figure out which columns exist in the table that AREN'T in the history table
        $new_columns = array_diff($original_col_names, $history_table_col_names);

        // loop through each new field and find out the database size (stored in ft_form_fields table).
        // for each, add the new column to the history table
        foreach ($new_columns as $new_column_name) {

            // get the field size of this form - that's all we really need to know
            $field_info = Fields::getFormFieldByColname($form_id, $new_column_name);
            $field_size = $field_info["field_size"];
            $new_field_size = $field_sizes[$field_size]["sql"];
            CoreGeneral::addTableColumn("{PREFIX}form_{$form_id}_history", $new_column_name, $new_field_size);
        }
    }


    /**
     * Updated in 1.1.4, this deletes the corresponding form fields in the history table when the admin
     * deletes them from the Edit Form -> Fields tab.
     *
     * By the time this function is called, the actual fields have been removed from the database. This
     * function wasn't properly working prior to 1.1.4. This module now REQUIRES Form Tools Core 2.1.6 or later. :-(
     *
     * @param array $postdata
     */
    public function hookDeleteFormFields($postdata)
    {
        $db = Core::$db;

        $form_id = $postdata["form_id"];
        if (!isset($postdata["field_ids"]) || !is_array($postdata["field_ids"]) || !isset($postdata["removed_fields"])) {
            return;
        }

        foreach ($postdata["removed_fields"] as $field_id => $col_name) {
            try {
                $db->query("ALTER TABLE {PREFIX}form_{$form_id}_history DROP $col_name");
                $db->execute();
            } catch (Exception $e) {

            }
        }
    }


    /**
     * Our shadow for the ft_create_blank_submission function.
     *
     * @param array $postdata
     */
    public function hookCreateBlankSubmission($postdata)
    {
        $form_id = $postdata["form_id"];

        if (!General::isTrackingForm($form_id)) {
            return;
        }

        $data = array(
            "submission_id"      => $postdata["new_submission_id"],
            "ip_address"         => $postdata["ip"],
            "submission_date"    => $postdata["now"],
            "last_modified_date" => $postdata["now"],
        );
        Code::addHistoryRow($form_id, $postdata["new_submission_id"], "new", $data);
    }


    /**
     * Called after the Form Backup module successfully backs up a form.
     * @param $hook_data
     */
    public function hookOnFormBackup($hook_data)
    {
        $db = Core::$db;
        $settings = Modules::getModuleSettings(array("history_tables_created", "tracked_form_ids", "track_new_forms"), "submission_history");

        // a little odd this, but the history_tables_created is really just an extra step the user needs to do to
        // activate the module.
        if ($settings["history_tables_created"] === "no") {
            return;
        }

        $original_form_id = $hook_data["form_id"];
        $form_id = $hook_data["new_form_id"];

        // if the user copied the form submissions over with the form backup AND they want to automatically track new forms,
        // let's create the history table with the history data populated as well. Seems the most logical thing to do from
        // a UX perspective.
        if ($hook_data["copy_submissions"] && $settings["track_new_forms"] === "yes") {

            // assumption is the the history table exists. This is reasonable because we've checked the history_tables_created above
            $db->query("CREATE TABLE {PREFIX}form_{$form_id}_history LIKE {PREFIX}form_{$original_form_id}_history");
            $db->execute();

            $db->query("INSERT {PREFIX}form_{$form_id}_history SELECT * FROM {PREFIX}form_{$original_form_id}_history");
            $db->execute();
        } else {
            Code::createHistoryTable($form_id);
        }

        if ($settings["track_new_forms"] == "yes") {
            $form_ids = explode(",", $settings["tracked_form_ids"]);
            $form_ids[] = $form_id;
            $updated_form_id_str = implode(",", $form_ids);

            $this->setSettings(array(
                "tracked_form_ids" => $updated_form_id_str
            ));
        }
    }

    /**
     * Called when a single submission is deleted. This is called at the START of the ft_delete_submission
     * function, so we can rely on the submission still being in the main forms table.
     *
     * @param $postdata
     */
    public function hookDeleteSubmission($postdata)
    {
        $submission_id = $postdata["submission_id"];
        $form_id       = $postdata["form_id"];
        $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);
        Code::addHistoryRow($form_id, $submission_id, "delete", $submission_info);
    }


    /**
     * Called when multiple submissions are deleted. This function could potentially create a serious
     * lag in the UI if the number of submissions being deleted are in the hundreds or thousands. It
     * creates a new entry in the history table for each and every submission being deleted. Unfortunately
     * there's not much we can do to get around this...
     *
     * @param $postdata
     */
    public function hookDeleteSubmissions($postdata)
    {
        $form_id = $postdata["form_id"];
        $submissions_to_delete = $postdata["submissions_to_delete"];

        foreach ($submissions_to_delete as $submission_id) {
            $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);
            Code::addHistoryRow($form_id, $submission_id, "delete", $submission_info);
        }
    }


    /**
     * Called after finalizing a form. It creates the new history table. If the user has indicated that they want all
     * new forms tracked, this adds the form ID to the tracked_form_ids settings so it's automatically picked
     * up by the other hooks.
     *
     * @param $postdata
     */
    public function hookFinalizeForm($postdata)
    {
        $form_id = $postdata["form_id"];
        Code::createHistoryTable($form_id);

        $settings = Modules::getModuleSettings(array("tracked_form_ids", "track_new_forms"), "submission_history");
        if ($settings["track_new_forms"] == "yes") {
            $form_ids = explode(",", $settings["tracked_form_ids"]);
            $form_ids[] = $form_id;
            $updated_form_id_str = implode(",", $form_ids);

            $this->setSettings(array(
                "tracked_form_ids" => $updated_form_id_str
            ));
        }
    }


    public function hookDeleteForm($postdata)
    {
        $db = Core::$db;

        $form_id = $postdata["form_id"];
        try {
            $db->query("DROP TABLE {PREFIX}form_{$form_id}_history");
            $db->execute();
        } catch (Exception $e) {

        }
    }


    public function hookProcessForm($postdata)
    {
        $form_id       = $postdata["form_id"];
        $submission_id = $postdata["submission_id"];
        $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);
        Code::addHistoryRow($form_id, $submission_id, "new", $submission_info);
    }


    /**
     * This function is called after every ft_update_submission call - after the submission info is
     * updated in the database.
     *
     * @param array $postdata
     */
    public function hookUpdateSubmission($postdata)
    {
        $form_id       = $postdata["form_id"];
        $submission_id = $postdata["submission_id"];
        $context = (isset($postdata["infohash"]["context"]) && $postdata["infohash"]["context"] == "submission_accounts") ? "submission" : "update";
        $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);
        Code::addHistoryRow($form_id, $submission_id, $context, $submission_info);
    }

    /**
     * This is a workaround, but not a terribly bad one. It's called at the BEGINNING of the ft_update_submission
     * function, prior to updating the database. Since this module doesn't initially prepopulate the history
     * tables with copies of all the data (too much work, too much wasted db space), this function checks to see if
     * the submission already has history. If it doesn't, it creates a new record of the EXISTING submission content
     * (prior to update. The sh_hook_update_submission function then logs the updated content right after.
     *
     * @param array $postdata
     */
    public function hookUpdateSubmissionInit($postdata)
    {
        $form_id       = $postdata["form_id"];
        $submission_id = $postdata["submission_id"];

        if (!General::isTrackingForm($form_id)) {
            return;
        }

        if (!General::submissionHasHistory($form_id, $submission_id)) {
            $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);
            Code::addHistoryRow($form_id, $submission_id, "original", $submission_info);
        }
    }


    /**
     * Called on page load on the admin's Edit Submission page. This actually does nothing other than show the
     * submission history row (if the form is being tracked). If the user has selected the auto-load history
     * option, the markup inserted by this function will load the history via an Ajax call.
     *
     * @param string $location
     * @param array $info
     */
    public function hookDisplaySubmissionChangelog($location, $info)
    {
        $smarty = Core::$smarty;
        $root_dir = Core::getRootDir();

        $form_id = $info["form_id"];
        if (!General::isTrackingForm($form_id)) {
            return;
        }

        $smarty->setTemplateDir("$root_dir/themes/default");
        $smarty->setCompileDir("$root_dir/themes/default/cache");
        $smarty->setUseSubDirs(Core::shouldUseSmartySubDirs());
        $smarty->assign("L", $this->getLangStrings());
        $smarty->assign("module_settings", $this->getSettings());
        $smarty->display("$root_dir/modules/submission_history/templates/admin_edit_submission.tpl");
    }


    public function hookIncludeModuleResources($postdata)
    {
        $root_url = Core::getRootUrl();

        if (!isset($postdata["page_vars"]["page"])) {
            return;
        }
        if ($postdata["page_vars"]["page"] != "admin_edit_submission") {
            return;
        }

        $smarty = $postdata["smarty"];
        $template_vars = $smarty->getTemplateVars();

        $head_string = $template_vars["head_string"];
        $head_string .=<<<EOF
  <link rel="stylesheet" type="text/css" media="all" href="{$root_url}/modules/submission_history/css/styles.css" />
  <script src="{$root_url}/modules/submission_history/scripts/scripts.js"></script>
EOF;

        $postdata["smarty"]->assign("head_string", $head_string);

        return $postdata;
    }


    /**
     * Called when the admin / client deletes a file in a submission.
     *
     * @param array $postdata
     */
    public function hookDeleteFileSubmission($postdata)
    {
        $form_id = $postdata["form_id"];
        if (!General::isTrackingForm($form_id)) {
            return;
        }

        $submission_id = $postdata["submission_id"];
        $submission_info = Submissions::getSubmissionInfo($form_id, $submission_id);

        Code::addHistoryRow($form_id, $submission_id, "update", $submission_info);
    }


    /**
     * Used to handle cases where the user changes the database column name. This changed in 1.1.4
     * to actually WORK with Form Tools Core 2.1.x (needs 2.1.7 or later).
     *
     * This hook call extends the core _ft_alter_table_column function, called whenever ANY table
     * is altered. This function figures out if
     *
     * @param array $postdata
     */
    public function renameTableColumn($info)
    {
        $db = Core::$db;

        if (!preg_match("/{PREFIX}form_(\d)+/", $info["table"], $matches)) {
            return;
        }

        // just blithely attempt to update the database table. It may not exist, but that's fine.
        $history_table = "{$info["table"]}_history";
        $old_col_name = $info["old_col_name"];
        $new_col_name = $info["new_col_name"];
        $col_type     = $info["col_type"];

        try {
            $db->query("
                ALTER TABLE $history_table
                CHANGE      $old_col_name $new_col_name $col_type
            ");
            $db->execute();
        } catch (Exception $e) {

        }
    }


    public function updateSettings($info)
    {
        $L = $this->getLangStrings();

        $settings = array(
            "track_new_forms" => (isset($info["track_new_forms"]) ? $info["track_new_forms"] : "no"),
            "page_label" => $info["page_label"],
            "history_max_record_size" => $info["history_max_record_size"],
            "table_max_record_size" => $info["table_max_record_size"],
            "days_until_auto_delete" => $info["days_until_auto_delete"],
            "auto_load_on_edit_submission" => (isset($info["auto_load_on_edit_submission"]) ? $info["auto_load_on_edit_submission"] : "no"),
            "num_per_page" => $info["num_per_page"],
            "date_format" => $info["date_format"]
        );

        $this->setSettings($settings);

        return array(true, $L["notify_settings_updated"]);
    }

}
