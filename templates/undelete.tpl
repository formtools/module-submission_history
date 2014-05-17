{include file='modules_header.tpl'}

  <table cellpadding="0" cellspacing="0">
  <tr>
    <td width="45"><img src="images/icon_submission_history.gif" width="34" height="34" /></td>
    <td class="title"><a href="./">{$L.module_name|upper}</a> &raquo; {$L.phrase_deleted_submissions|upper}</td>
  </tr>
  </table>

  {include file='messages.tpl'}

  {if $num_results == 0}
    <div class="notify">
      <div style="padding: 6px">
        {$L.notify_no_deleted_submissions}
      </div>
    </div>
  {else}

    <div class="margin_bottom_large">
      {$L.text_undelete_page}
    </div>

    <form action="{$same_page}" method="post">
      <div class="margin_bottom_large search_row">
        <input type="text" name="search" value="{$search|escape}" />
        <input type="submit" name="" value="{$LANG.word_search}" />
      </div>
    </form>

    {if $deleted_submissions|@count == 0}

      <div class="notify">
        <div style="padding: 6px">
          {$L.notify_no_results_found}
        </div>
      </div>

    {else}

      {$pagination}

      <table cellspacing="1" cellpadding="0" class="list_table" style="width: 400px">
      <tr>
        <th>{$LANG.phrase_submission_id}</th>
        <th>{$L.phrase_date_deleted}</th>
        <th width="80">{$LANG.word_view|upper}</th>
      </tr>
      {foreach from=$deleted_submissions item=submission}
      <tr>
        <td class="pad_left_small">{$submission.submission_id}</td>
        <td align="center">
          {$submission.sh___change_date|custom_format_date:$SESSION.account.timezone_offset:$module_settings.date_format}
        </td>
        <td align="center">
          <a href="view_deleted_submission.php?history_id={$submission.sh___history_id}">{$LANG.word_view|upper}</a>
        </td>
      </tr>
      {/foreach}
      </table>
    {/if}
  {/if}

{include file='modules_footer.tpl'}
