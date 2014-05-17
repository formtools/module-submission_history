  <table cellpadding="0" cellspacing="0" class="margin_top_large margin_bottom_large">
    <tr>
      <td class="nowrap" width="60">
        {if $previous_history_id}
          <a href="javascript:sh.view_history_changes({$previous_history_id})">{$L.word_older_leftarrow}</a>
        {else}
          <span class="light_grey">{$L.word_older_leftarrow}</a>
        {/if}
      </td>
      <td class="nowrap" width="150" align="center"><a href="javascript:sh.back_to_history()">{$L.phrase_back_to_history}</a></td>
      <td class="nowrap">
        {if $next_history_id}
          <a href="javascript:sh.view_history_changes({$next_history_id})">{$L.word_newer_rightarrow}</a>
        {else}
          <span class="light_grey">{$L.word_newer_rightarrow}</span>
        {/if}
      </td>
    </tr>
  </table>

  <table cellspacing="0" cellpadding="0" class="change_type_info">
  <tr>
    <td width="120">{$L.phrase_change_type}</td>
    <td>
      {if $item.sh___change_type == "new"}
        <span class="change_type_new">{$L.word_new}</span>
      {elseif $item.sh___change_type == "update"}
        <span class="change_type_update">{$L.word_update}</span>
      {elseif $item.sh___change_type == "restore"}
        <span class="change_type_restore">{$L.word_restored}</span>
      {/if}
    </td>
  </tr>
  </table>

  <ul>
    <li>
      <div>
        <div class="sh__field_name bold">{$L.word_field}</div>
        <div class="sh__previous_value bold">{$L.phrase_previous_edit_value}</div>
        <div class="sh__new_value bold">{$L.phrase_new_edit_value}</div>
      </div>
      <div style="clear: both"></div>
    </li>
  {foreach from=$fields item=field_info}
    <li{if $field_info.has_changed} class="changed_field"{/if}>
      <div>
        <div class="sh__field_name">{$field_info.field_name}</div>
        <div class="sh__previous_value">
          {if $has_previous_entry}
            {if $field_info.field_type == "select"}
              {submission_dropdown name=$field_info.col_name field_id=$field_info.field_id
                selected=$field_info.previous_value is_editable="no"}
            {elseif $field_info.field_type == "radio-buttons"}
              {submission_radios name=$field_info.col_name field_id=$field_info.field_id
                selected=$field_info.previous_value is_editable="no"}
            {elseif $field_info.field_type == "checkboxes"}
              {submission_checkboxes name=$field_info.col_name field_id=$field_info.field_id
                selected=$field_info.previous_value is_editable="no"}
            {elseif $field_info.field_type == "multi-select"}
              {submission_dropdown_multiple name=$field_info.col_name field_id=$field_info.field_id
                selected=$field_info.previous_value is_editable="no"}
            {elseif $field_info.field_type == "file"}
              <span id="field_{$field_id}_link" {if $field_info.previous_value == ""}style="display:none"{/if}>
                {display_file_field field_id=$field_info.field_id filename=$field_info.previous_value show_in_new_window=true}
              </span>
            {else}
              {$field_info.previous_value|truncate:60}
            {/if}
          {else}
            <span class="light_grey">(no previous version)</span>
          {/if}
        </div>
        <div class="sh__new_value">
          {if $field_info.field_type == "select"}
            {submission_dropdown name=$field_info.col_name field_id=$field_info.field_id
              selected=$field_info.new_value is_editable="no"}
          {elseif $field_info.field_type == "radio-buttons"}
            {submission_radios name=$field_info.col_name field_id=$field_info.field_id
              selected=$field_info.new_value is_editable="no"}
          {elseif $field_info.field_type == "checkboxes"}
            {submission_checkboxes name=$field_info.col_name field_id=$field_info.field_id
              selected=$field_info.new_value is_editable="no"}
          {elseif $field_info.field_type == "multi-select"}
            {submission_dropdown_multiple name=$field_info.col_name field_id=$field_info.field_id
              selected=$field_info.new_value is_editable="no"}
          {elseif $field_info.field_type == "file"}
            <span id="field_{$field_id}_link" {if $field_info.new_value == ""}style="display:none"{/if}>
              {display_file_field field_id=$field_info.field_id filename=$field_info.new_value show_in_new_window=true}
            </span>
          {else}
            {$field_info.new_value|truncate:60}
          {/if}
        </div>
      </div>
      <div style="clear: both"></div>
    </li>
  {/foreach}
  </ul>

  <table cellpadding="0" cellspacing="0">
    <tr>
      <td class="nowrap" width="60">
        {if $previous_history_id}
          <a href="javascript:sh.view_history_changes({$previous_history_id})">{$L.word_older_leftarrow}</a>
        {else}
          <span class="light_grey">{$L.word_older_leftarrow}</a>
        {/if}
      </td>
      <td class="nowrap" width="150" align="center"><a href="javascript:sh.back_to_history()">{$L.phrase_back_to_history}</a></td>
      <td class="nowrap">
        {if $next_history_id}
          <a href="javascript:sh.view_history_changes({$next_history_id})">{$L.word_newer_rightarrow}</a>
        {else}
          <span class="light_grey">{$L.word_newer_rightarrow}</span>
        {/if}
      </td>
    </tr>
  </table>

  <p>
    <input type="button" class="blue" value="{$L.phrase_restore_version}" onclick="sh.restore({$item.sh___history_id})" />
  </p>
