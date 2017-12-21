<div class="module_section" id="sh__content">
    <div id="sh__loading"{if $module_settings.auto_load_on_edit_submission == "no"} style="display:none"{/if}><img src="{$g_root_url}/modules/submission_history/images/loading.gif" /></div>
    <div id="sh__load_history"{if $module_settings.auto_load_on_edit_submission == "yes"} style="display:none"{/if}><input type="button" value="{$L.phrase_load_history}" onclick="sh.load_history()" /></div>
    <div id="sh__page_label">{$module_settings.page_label}</div>
    <div id="sh__results_div"></div>
</div>

{if $module_settings.auto_load_on_edit_submission == "yes"}
  <script>sh.load_history(1)</script>
{/if}
