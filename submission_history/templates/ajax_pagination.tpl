{*
  Template: ajax_pagination.tpl
  Purpose:  This is based on dhtml_pagination.tpl from the Core. It does pretty much the same stuff, except that
            the pages are loaded via Ajax instead of having them hidden in the page.
*}

  {if $total_pages > 1}
    <div id="list_nav">

      {* always show a "<<" (previous page) link. Its contents are changed with JS *}
      <span id="nav_previous_page">
        {if $current_page != 1}
          {assign var='previous_page' value=$current_page-1}
          <a href="javascript:sh.load_history({$previous_page})">&laquo;</a>
        {else}
          &laquo;
        {/if}
      </span>

      {section name=counter start=1 loop=$total_pages+1}
        {assign var="page" value=$smarty.section.counter.index}

        <span id="nav_page_{$page}">
          {if $page == $current_page}
            <span id="list_current_page"><b>{$page}</b></span>
          {else}
            <span class="pad_right_small"><a href="javascript:sh.load_history({$page})">{$page}</a></span>
          {/if}
        </span>
      {/section}

      {* always show a ">>" (next page) link. Its content is changed with JS *}
      <span id="nav_next_page">
        {if $current_page != $total_pages}
          {assign var='next_page' value=$current_page+1}
          <a href="javascript:sh.load_history({$next_page})">&raquo;</a>
        {else}
          <span id="nav_next_page">&raquo;</span>
        {/if}
      </span>

    </div>
  {/if}
