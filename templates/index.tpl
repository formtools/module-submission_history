{ft_include file='modules_header.tpl'}

  <table cellpadding="0" cellspacing="0">
  <tr>
    <td width="45"><a href="index.php"><img src="images/icon_submission_history.gif" border="0" width="34" height="34" /></a></td>
    <td class="title">
      <a href="../../admin/modules">{$LANG.word_modules}</a>
      <span class="joiner">&raquo;</span>
      {$L.module_name}
    </td>
  </tr>
  </table>

  {ft_include file='messages.tpl'}

  {if $module_settings.history_tables_created == "no"}

    <form action="{$same_page}" method="post" id="create_history_table_form">
      <div class="margin_bottom_large">
        {$L.text_module_intro}
      </div>
      <p>
        <input type="button" name="create_history_table" id="create_history_table"
          value="{$L.phrase_create_history_tables}" onclick="page_ns.create_history_tables()" />
      </p>
    </form>

  {else}

    <form action="{$same_page}" method="post">

      <div class="margin_bottom_large">
        {$L.text_tracking_table}
      </div>
      <table cellspacing="1" cellpadding="0" class="list_table check_areas">
      <tr>
        <th> </th>
        <th>{$LANG.word_form}</th>
        <th width="100">{$L.phrase_track_activity}</th>
        <th>{$L.phrase_table_size_kb}</th>
        <th>{$L.phrase_num_rows}</th>
        <th>{$L.word_undelete|upper}</th>
        <th width="80">{$L.phrase_clear_logs}</th>
      </tr>
      {foreach from=$forms item=form}
      <tr>
        <td class="pad_left_small light_grey">{$form.form_id}</td>
        <td class="pad_left_small"><a href="../../admin/forms/submissions.php?form_id={$form.form_id}">{$form.form_name}</a></td>
        <td align="center" class="check_area">
          <input type="checkbox" name="tracked_form_ids[]" value="{$form.form_id}"
            {if $form.form_id|in_array:$configured_form_ids}checked{/if} />
        </td>
        <td align="center">{$form.history_table_size}</td>
        <td align="center">{$form.history_table_rows}</td>
        <td align="center">
          {if $form.num_deleted_submissions == 0}
            <span class="light_grey">{$L.word_undelete|upper}</span>
          {else}
            <a href="undelete.php?form_id={$form.form_id}">{$L.word_undelete|upper}</a> ({$form.num_deleted_submissions})
          {/if}
        </td>
        <td><input type="button" value="{$L.phrase_clear_logs}" onclick="page_ns.clear_logs({$form.form_id})"/></td>
        </tr>
      {/foreach}
      </table>

      <p>
        <input type="submit" name="update_activity_tracking" value="{$L.phrase_update_form_activity_tracking}" />
        <input type="submit" name="clear_all_logs" value="{$L.phrase_clear_all_logs}" class="burgundy" />
      </p>
    </form>

  {/if}

{ft_include file='modules_footer.tpl'}
