<style>
    .mb-home-products .tab-buttons ul li {
        background-color: {$inactive_tab_bg_color};
        color: {$inactive_tab_color};
    }
    .mb-home-products .tab-buttons ul li.active {
        background-color: {$active_tab_bg_color};
        color: {$active_tab_color};
    }
</style>
<div class="mb-home-products mb-tabs featured-products">
    <h2 class="h2 products-section-title text-uppercase" style="margin-bottom: 3rem;">
        {$mb_home_tabs_title}
    </h2>

    <div class="tab-buttons">
        <ul>
            {foreach from=$mb_home_tabs key=key item=tab name=tabs}
                <li data-id="tab{$smarty.foreach.tabs.index+1}" class="{if $smarty.foreach.tabs.first}active{/if}">
                    <span>{$tab.title}</span>
                </li>
            {/foreach}
        </ul>
    </div>
    <div class="mb-card">
        {foreach from=$mb_home_tabs key=key item=tab name=tabs}
            <div id="tab{$smarty.foreach.tabs.index+1}" class="tab {if $smarty.foreach.tabs.first}active{/if}">
                <div>
                    {if $key == 'bestseller'}
                        {widget name='ps_bestsellers'}
                    {elseif $key == 'promotions'}
                        {widget name='ps_specials' hook='displayHome'}
                    {elseif $key == 'new'}
                        {widget name='ps_newproducts' hook='displayHome'}
                    {/if}
                </div>
            </div>
        {/foreach}
    </div>
</div>