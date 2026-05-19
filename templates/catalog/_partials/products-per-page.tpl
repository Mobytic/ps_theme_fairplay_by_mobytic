{assign var='productsPerPageCurrent' value=$listing.pagination.items_per_page|default:$smarty.get.resultsPerPage|default:$smarty.get.productsPerPage|default:$smarty.get.n|default:12}
{assign var='productsPerPageOptions' value=[12, 24, 48, 96]}

<span class="col-sm-3 col-md-5 hidden-sm-down sort-by">{l s='Per page:' d='Shop.Theme.Catalog'}</span>
<div class="{if !empty($listing.rendered_facets)}col-xs-8 col-sm-7{else}col-xs-12 col-sm-12{/if} col-md-9 products-per-page">
    <div class="dropdown products-sort-order products-per-page-dropdown" style="display:inline-block; min-width:120px;">
        <button class="btn-unstyle select-title" id="products-per-page-dropdown" data-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false" type="button">
            {$productsPerPageCurrent}
            <i class="material-icons float-xs-right">&#xE5C5;</i>
        </button>
        <div class="dropdown-menu" aria-labelledby="products-per-page-dropdown">
            {foreach from=$productsPerPageOptions item=productsPerPageOption}
                <a class="select-list js-products-per-page-link{if $productsPerPageCurrent == $productsPerPageOption} current{/if}"
                    href="#" data-value="{$productsPerPageOption}">
                    {$productsPerPageOption}
                </a>
            {/foreach}
        </div>
    </div>
</div>

{literal}
    <script>
        if (!window.mbProductsPerPageInit) {
            window.mbProductsPerPageInit = true;
            document.addEventListener('click', function(event) {
                var target = event.target;
                if (!target || !target.classList || !target.classList.contains('js-products-per-page-link')) {
                    return;
                }
                event.preventDefault();
                var value = target.getAttribute('data-value');
                var url = new URL(window.location.href);
                url.searchParams.set('n', value);
                url.searchParams.set('resultsPerPage', value);
                url.searchParams.set('productsPerPage', value);
                url.searchParams.set('page', '1');
                window.location.href = url.toString();
            });
        }
    </script>
{/literal}