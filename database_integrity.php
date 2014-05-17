<?php

$HOOKS = array();
$HOOKS["1.1.2"] = array(
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_add_form_fields",
    "hook_function"   => "sh_hook_add_form_fields",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_delete_form_fields",
    "hook_function"   => "sh_hook_delete_form_fields",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_finalize_form",
    "hook_function"   => "sh_hook_finalize_form",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "start",
    "function_name"   => "ft_delete_form",
    "hook_function"   => "sh_hook_delete_form",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_update_form_fields_tab",
    "hook_function"   => "ft_hook_update_form_fields_tab",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_create_blank_submission",
    "hook_function"   => "sh_hook_create_blank_submission",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_process_form",
    "hook_function"   => "sh_hook_process_form",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "start",
    "function_name"   => "ft_delete_submission",
    "hook_function"   => "sh_hook_delete_submission",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "start",
    "function_name"   => "ft_delete_submissions",
    "hook_function"   => "sh_hook_delete_submissions",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_update_submission",
    "hook_function"   => "sh_hook_update_submission",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "start",
    "function_name"   => "ft_update_submission",
    "hook_function"   => "sh_hook_update_submission_init",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "end",
    "function_name"   => "ft_file_delete_file_submission",
    "hook_function"   => "sh_hook_delete_file_submission",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "template",
    "action_location" => "admin_edit_submission_bottom",
    "function_name"   => "",
    "hook_function"   => "sh_hook_display_submission_changelog",
    "priority"        => "50"
  ),
  array(
    "hook_type"       => "code",
    "action_location" => "main",
    "function_name"   => "ft_display_page",
    "hook_function"   => "sh_hook_include_module_resources",
    "priority"        => "50"
  )
);