{ft_include file='modules_header.tpl'}

  <table cellpadding="0" cellspacing="0">
  <tr>
    <td width="45"><a href="index.php"><img src="images/icon_submission_history.gif" border="0" width="34" height="34" /></a></td>
    <td class="title">
      <a href="../../admin/modules">{$LANG.word_modules}</a>
      <span class="joiner">&raquo;</span>
      <a href="./">{$L.module_name}</a>
      <span class="joiner">&raquo;</span>
      {$L.word_settings}
    </td>
  </tr>
  </table>

  {ft_include file='messages.tpl'}

  <div class="margin_bottom_large">
    {$L.text_settings_page}
  </div>

  <form action="{$same_page}" method="post">

    <div class="subtitle margin_bottom_large">{$LANG.phrase_main_settings}</div>

    <table cellspacing="1" cellpadding="1" width="100%" class="list_table margin_bottom_large">
    <tr>
      <td width="280"><label for="track_new_forms">{$L.phrase_automatically_track}</label></td>
      <td>
        <input type="radio" name="track_new_forms" value="yes" id="tnf1"
          {if $module_settings.track_new_forms == "yes"}checked{/if} />
          <label for="tnf1">{$LANG.word_yes}</label>
        <input type="radio" name="track_new_forms" value="no" id="tnf2"
          {if $module_settings.track_new_forms == "no"}checked{/if} />
          <label for="tnf2">{$LANG.word_no}</label>
      </td>
    </tr>
    <tr>
      <td>{$L.phrase_limit_history_size}</td>
      <td>
        <select name="history_max_record_size">
          <option value="" {if $module_settings.history_max_record_size == ""}selected{/if}>{$L.phrase_do_not_limit_history}</option>
          <option value="5" {if $module_settings.history_max_record_size == "5"}selected{/if}>{$L.phrase_5_records}</option>
          <option value="10" {if $module_settings.history_max_record_size == "10"}selected{/if}>{$L.phrase_10_records}</option>
          <option value="15" {if $module_settings.history_max_record_size == "15"}selected{/if}>{$L.phrase_15_records}</option>
          <option value="20" {if $module_settings.history_max_record_size == "20"}selected{/if}>{$L.phrase_20_records}</option>
          <option value="25" {if $module_settings.history_max_record_size == "25"}selected{/if}>{$L.phrase_25_records}</option>
          <option value="50" {if $module_settings.history_max_record_size == "50"}selected{/if}>{$L.phrase_50_records}</option>
          <option value="100" {if $module_settings.history_max_record_size == "100"}selected{/if}>{$L.phrase_100_records}</option>
          <option value="200" {if $module_settings.history_max_record_size == "200"}selected{/if}>{$L.phrase_200_records}</option>
          <option value="500" {if $module_settings.history_max_record_size == "500"}selected{/if}>{$L.phrase_500_records}</option>
        </select>
      </td>
    </tr>
    <tr>
      <td>{$L.phrase_limit_total_table_log_size}</td>
      <td>
        <select name="table_max_record_size">
          <option value="" {if $module_settings.table_max_record_size == ""}selected{/if}>{$L.phrase_do_not_limit_history}</option>
          <option value="100" {if $module_settings.table_max_record_size == "100"}selected{/if}>{$L.phrase_100_records}</option>
          <option value="500" {if $module_settings.table_max_record_size == "500"}selected{/if}>{$L.phrase_500_records}</option>
          <option value="1000" {if $module_settings.table_max_record_size == "1000"}selected{/if}>{$L.phrase_1000_records}</option>
          <option value="5000" {if $module_settings.table_max_record_size == "5000"}selected{/if}>{$L.phrase_5000_records}</option>
          <option value="10000" {if $module_settings.table_max_record_size == "10000"}selected{/if}>{$L.phrase_10000_records}</option>
          <option value="20000" {if $module_settings.table_max_record_size == "20000"}selected{/if}>{$L.phrase_20000_records}</option>
          <option value="30000" {if $module_settings.table_max_record_size == "30000"}selected{/if}>{$L.phrase_30000_records}</option>
          <option value="40000" {if $module_settings.table_max_record_size == "40000"}selected{/if}>{$L.phrase_40000_records}</option>
          <option value="50000" {if $module_settings.table_max_record_size == "50000"}selected{/if}>{$L.phrase_50000_records}</option>
          <option value="100000" {if $module_settings.table_max_record_size == "100000"}selected{/if}>{$L.phrase_100000_records}</option>
        </select>
      </td>
    </tr>
    <tr>
      <td>{$L.phrase_auto_delete_history}</td>
      <td>
        <select name="days_until_auto_delete">
          <option value="" {if $module_settings.days_until_auto_delete == ""}selected{/if}>{$L.phrase_do_not_delete_history}</option>
          <option value="2" {if $module_settings.days_until_auto_delete == "2"}selected{/if}>{$L.phrase_2_days}</option>
          <option value="3" {if $module_settings.days_until_auto_delete == "3"}selected{/if}>{$L.phrase_3_days}</option>
          <option value="4" {if $module_settings.days_until_auto_delete == "4"}selected{/if}>{$L.phrase_4_days}</option>
          <option value="5" {if $module_settings.days_until_auto_delete == "5"}selected{/if}>{$L.phrase_5_days}</option>
          <option value="6" {if $module_settings.days_until_auto_delete == "6"}selected{/if}>{$L.phrase_6_days}</option>
          <option value="7" {if $module_settings.days_until_auto_delete == "7"}selected{/if}>{$L.phrase_1_week}</option>
          <option value="14" {if $module_settings.days_until_auto_delete == "14"}selected{/if}>{$L.phrase_2_weeks}</option>
          <option value="21" {if $module_settings.days_until_auto_delete == "21"}selected{/if}>{$L.phrase_3_weeks}</option>
          <option value="30" {if $module_settings.days_until_auto_delete == "30"}selected{/if}>{$L.phrase_1_month}</option>
          <option value="180" {if $module_settings.days_until_auto_delete == "180"}selected{/if}>{$L.phrase_6_months}</option>
          <option value="364" {if $module_settings.days_until_auto_delete == "364"}selected{/if}>{$L.phrase_1_year}</option>
          <option value="728" {if $module_settings.days_until_auto_delete == "728"}selected{/if}>{$L.phrase_2_years}</option>
          <option value="1092" {if $module_settings.days_until_auto_delete == "1092"}selected{/if}>{$L.phrase_3_years}</option>
          <option value="1820" {if $module_settings.days_until_auto_delete == "1820"}selected{/if}>{$L.phrase_5_years}</option>
        </select>
      </td>
    </tr>
    <tr>
      <td>{$L.phrase_num_deleted_submissions_per_page}</td>
      <td>
        <select name="num_deleted_submissions_per_page">
          <option value="10" {if $module_settings.num_deleted_submissions_per_page == "10"}selected{/if}>10</option>
          <option value="15" {if $module_settings.num_deleted_submissions_per_page == "15"}selected{/if}>15</option>
          <option value="20" {if $module_settings.num_deleted_submissions_per_page == "20"}selected{/if}>20</option>
          <option value="25" {if $module_settings.num_deleted_submissions_per_page == "25"}selected{/if}>25</option>
          <option value="30" {if $module_settings.num_deleted_submissions_per_page == "30"}selected{/if}>30</option>
          <option value="50" {if $module_settings.num_deleted_submissions_per_page == "50"}selected{/if}>50</option>
          <option value="100" {if $module_settings.num_deleted_submissions_per_page == "100"}selected{/if}>100</option>
        </select>
      </td>
    </tr>
    </table>

    <div class="subtitle margin_bottom_large">{$L.phrase_edit_submission_page}</div>

    <table cellspacing="1" cellpadding="1" width="100%" class="list_table">
    <tr>
      <td width="280"><label for="page_label">{$L.phrase_submission_history_label}</label></td>
      <td>
        <input type="text" name="page_label" id="page_label" value="{$module_settings.page_label|escape}" />
      </td>
    </tr>
    <tr>
      <td>{$L.phrase_auto_load_history}</td>
      <td>
        <input type="radio" name="auto_load_on_edit_submission" value="yes" id="ales1"
          {if $module_settings.auto_load_on_edit_submission == "yes"}checked{/if} />
          <label for="ales1">{$LANG.word_yes}</label>
        <input type="radio" name="auto_load_on_edit_submission" value="no" id="ales2"
          {if $module_settings.auto_load_on_edit_submission == "no"}checked{/if} />
          <label for="ales2">{$LANG.word_no}</label>
      </td>
    </tr>
    <tr>
      <td><label for="num_per_page">{$L.phrase_num_history_per_page}</label></td>
      <td>
        <select name="num_per_page" id="num_per_page">
          <option value="5"  {if $module_settings.num_per_page == 5}selected{/if}>5</option>
          <option value="10" {if $module_settings.num_per_page == 10}selected{/if}>10</option>
          <option value="15" {if $module_settings.num_per_page == 15}selected{/if}>15</option>
          <option value="20" {if $module_settings.num_per_page == 20}selected{/if}>20</option>
          <option value="25" {if $module_settings.num_per_page == 25}selected{/if}>25</option>
          <option value="50" {if $module_settings.num_per_page == 50}selected{/if}>50</option>
          <option value="100" {if $module_settings.num_per_page == 100}selected{/if}>100</option>
          <option value="all" {if $module_settings.num_per_page == "all"}selected{/if}>All</option>
        </select>
      </td>
    </tr>
    <tr>
      <td><label for="date_format">{$LANG.phrase_date_format}</label></td>
      <td>
        <input type="text" name="date_format" id="date_format" value="{$module_settings.date_format|escape}" />
      </td>
    </tr>
    </table>

    <p>
      <input type="submit" name="update" value="{$LANG.word_update}" />
    </p>
  </form>

{ft_include file='modules_footer.tpl'}
