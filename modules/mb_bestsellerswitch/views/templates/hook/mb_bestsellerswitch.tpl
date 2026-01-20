<section class="featured-products clearfix mt-3">
    <h2 class="h2 products-section-title text-uppercase">
        {$title}
    </h2>

    {include file="catalog/_partials/productlist.tpl"
    products=$products
    productClass="col-xs-12 col-sm-6 col-lg-4 col-xl-3"
  }

    <a class="all-product-link" href="{$allBestSellers}">
        {l s='All best sellers' d='Shop.Theme.Catalog'}<i class="material-icons">&#xE315;</i>
    </a>
</section>