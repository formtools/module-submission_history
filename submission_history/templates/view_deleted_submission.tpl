{include file='modules_header.tpl'}

  <table cellpadding="0" cellspacing="0">
  <tr>
    <td width="45"><a href="index.php"><img src="images/icon_submission_history.gif" border="0" width="34" height="34" /></a></td>
    <td class="title">
      <a href="../../admin/modules">{$LANG.word_modules}</a>
      <span class="joiner">&raquo;</span>
      <a href="./">{$L.module_name}</a>
      <span class="joiner">&raquo;</span>
      {$L.word_undelete}
    </td>
  </tr>
  </table>

  {include file='messages.tpl'}

  <table cellpadding="0" cellspacing="0" class="margin_bottom_large">
    <tr>
      <td class="nowrap" width="60">
        {if $previous_history_id}
          <a href="view_deleted_submission.php?history_id={$previous_history_id}">{$LANG.word_previous_leftarrow}</a>
        {else}
          <span class="light_grey">{$LANG.word_previous_leftarrow}</a>
        {/if}
      </td>
      <td class="nowrap" width="180" align="center"><a href="undelete.php">{$LANG.phrase_back_to_search_results}</a></td>
      <td class="nowrap">
        {if $next_history_id}
          <a href="view_deleted_submission.php?history_id={$next_history_id}">{$L.word_newer_rightarrow}</a>
        {else}
          <span class="light_grey">{$L.word_newer_rightarrow}</span>
        {/if}
      </td>
    </tr>
  </table>

  <table cellspacing="1" cellpadding="0" class="list_table">
  {foreach from=$fields key=k item=v}
    <tr>
      <td class="pad_left_small">{$k}</td>
      <td class="pad_left_small">{$v}</td>
    </tr>
  {/foreach}
  </table>

  <table cellpadding="0" cellspacing="0">
    <tr>
      <td class="nowrap" width="60">
        {if $previous_history_id}
          <a href="view_deleted_submission.php?history_id={$previous_history_id}">{$LANG.word_previous_leftarrow}</a>
        {else}
          <span class="light_grey">{$LANG.word_previous_leftarrow}</a>
        {/if}
      </td>
      <td class="nowrap" width="180" align="center"><a href="undelete.php">{$LANG.phrase_back_to_search_results}</a></td>
      <td class="nowrap">
        {if $next_history_id}
          <a href="view_deleted_submission.php?history_id={$next_history_id}">{$L.word_newer_rightarrow}</a>
        {else}
          <span class="light_grey">{$L.word_newer_rightarrow}</span>
        {/if}
      </td>
    </tr>
  </table>

  <form action="undelete.php" method="post">
    <input type="hidden" name="history_id" value="{$history_id}" />
    <p>
      <input type="submit" name="undelete" class="blue" value="{$L.word_undelete}" />
    </p>
  </form>

{include file='modules_footer.tpl'}
