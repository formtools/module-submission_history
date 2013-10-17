  <!-- search -->
  <div id="sh__pagination">{$pagination}</div>

  <ul>
    <li>
      <div>
        <div class="sh__view_change"></div>
        <div class="sh__date bold">{$L.phrase_change_date}</div>
        <div class="sh__change_type bold">{$L.phrase_change_type}</div>
        <div class="sh__changed_by bold">{$L.phrase_changed_by}</div>
        <div class="sh__changes bold">{$L.phrase_changed_fields}</div>
      </div>
      <div style="clear: both"></div>
    </li>
  {foreach from=$results item=item}
    <li>
      <div>
        <div class="sh__view_change">
          <a href="javascript:sh.view_history_changes({$item.sh___history_id});">{$LANG.word_view|upper}</a>
        </div>
        <div class="sh__date">{$item.sh___change_date}</div>
        <div class="sh__change_type">
          {if $item.sh___change_type == "new"}
            <span class="change_type_new">{$L.word_new}</span>
          {elseif $item.sh___change_type == "update"}
            <span class="change_type_update">{$L.word_update}</span>
          {elseif $item.sh___change_type == "restore"}
            <span class="change_type_restore">{$L.word_restored}</span>
          {elseif $item.sh___change_type == "delete"}
            <span class="change_type_delete">{$L.word_deleted}</span>
          {elseif $item.sh___change_type == "original"}
            <span class="change_type_original">{$L.word_original}</span>
          {elseif $item.sh___change_type == "undelete"}
            <span class="change_type_undelete">{$L.word_undelete}</span>
          {elseif $item.sh___change_type == "submission"}
            <span class="change_type_submission">{$L.word_submission}</span>
          {/if}
        </div>
        <div class="sh__changed_by">
          {if $item.sh___change_account_id == 1}
            {$client_info[$item.sh___change_account_id].first_name} {$client_info[$item.sh___change_account_id].last_name}
          {elseif $item.sh___change_type == "submission"}
            {$L.phrase_submission_accounts_module}
          {else}
            <a href="../clients/edit.php?client_id={$item.sh___change_account_id}">{$client_info[$item.sh___change_account_id].first_name} {$client_info[$item.sh___change_account_id].last_name}</a>
          {/if}&nbsp;
        </div>
        <div class="sh__changes">
          {if $item.num_changed_fields == 0}
            <span class="no_changed_fields">&#8212;</span>
          {else}
            <select>
              <option>{$L.phrase_changed_fields_c} {$item.num_changed_fields}</option>
              {foreach from=$item.changed_fields item=field_info}
                <option>{$field_info}</option>
              {/foreach}
            </select>
          {/if}
        </div>
      </div>
      <div style="clear: both"></div>
    </li>
  {/foreach}
  </ul>

  <p>
    <input type="button" value="{$L.phrase_clear_submission_log}" onclick="sh.clear_submission_log()" />
  </p>
