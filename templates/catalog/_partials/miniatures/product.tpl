{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{block name='product_miniature_item'}
  <div class="js-product product{if !empty($productClasses)} {$productClasses}{/if}">
    <article class="product-miniature js-product-miniature" data-id-product="{$product.id_product}"
      data-id-product-attribute="{$product.id_product_attribute}">
      <div class="thumbnail-container">
        <div class="thumbnail-top">
          {block name='product_thumbnail'}
            {if $product.cover}
              <a href="{$product.url}" class="thumbnail product-thumbnail">
                <picture>
                  {if !empty($product.cover.bySize.home_default.sources.avif)}
                  <source srcset="{$product.cover.bySize.home_default.sources.avif}" type="image/avif">{/if}
                  {if !empty($product.cover.bySize.home_default.sources.webp)}
                  <source srcset="{$product.cover.bySize.home_default.sources.webp}" type="image/webp">{/if}
                  <img src="{$product.cover.bySize.home_default.url}"
                    alt="{if !empty($product.cover.legend)}{$product.cover.legend}{else}{$product.name|truncate:30:'...'}{/if}"
                    loading="lazy" data-full-size-image-url="{$product.cover.large.url}"
                    width="{$product.cover.bySize.home_default.width}" height="{$product.cover.bySize.home_default.height}" />
                </picture>
              </a>
            {else}
              <a href="{$product.url}" class="thumbnail product-thumbnail">
                <picture>
                  {if !empty($urls.no_picture_image.bySize.home_default.sources.avif)}
                  <source srcset="{$urls.no_picture_image.bySize.home_default.sources.avif}" type="image/avif">{/if}
                  {if !empty($urls.no_picture_image.bySize.home_default.sources.webp)}
                  <source srcset="{$urls.no_picture_image.bySize.home_default.sources.webp}" type="image/webp">{/if}
                  <img src="{$urls.no_picture_image.bySize.home_default.url}" loading="lazy"
                    width="{$urls.no_picture_image.bySize.home_default.width}"
                    height="{$urls.no_picture_image.bySize.home_default.height}" />
                </picture>
              </a>
            {/if}
          {/block}

          <div class="highlighted-informations{if !$product.main_variants} no-variants{/if}">
            {block name='quick_view'}
              <a class="quick-view js-quick-view" href="#" data-link-action="quickview">
                <i class="material-icons search">&#xE8B6;</i> {l s='Quick view' d='Shop.Theme.Actions'}
              </a>
            {/block}

            {block name='product_variants'}
              {if $product.main_variants}
                {include file='catalog/_partials/variant-links.tpl' variants=$product.main_variants}
              {/if}
            {/block}
          </div>
        </div>

        <div class="product-description">
          {block name='product_name'}
            {if $page.page_name == 'index'}
              <h3 class="h3 product-title"><a href="{$product.url}"
                  content="{$product.url}">{$product.name|truncate:100:'...'}</a></h3>
            {else}
              <h2 class="h3 product-title"><a href="{$product.url}"
                  content="{$product.url}">{$product.name|truncate:100:'...'}</a></h2>
            {/if}
          {/block}


          <div class="product-price-add-to-cart">
            {block name='product_price_and_shipping'}
              {if $product.show_price}
                <div class="product-price-and-shipping">
                  {if $product.has_discount}
                    {hook h='displayProductPriceBlock' product=$product type="old_price"}

                    <span class="regular-price"
                      aria-label="{l s='Regular price' d='Shop.Theme.Catalog'}">{$product.regular_price}</span>
                    {if $product.discount_type === 'percentage'}
                      <span class="discount-percentage discount-product">{$product.discount_percentage}</span>
                    {elseif $product.discount_type === 'amount'}
                      <span class="discount-amount discount-product">{$product.discount_amount_to_display}</span>
                    {/if}
                  {/if}

                  {hook h='displayProductPriceBlock' product=$product type="before_price"}

                  <span class="price" aria-label="{l s='Price' d='Shop.Theme.Catalog'}">
                    {capture name='custom_price'}{hook h='displayProductPriceBlock' product=$product type='custom_price' hook_origin='products_list'}{/capture}
                    {if '' !== $smarty.capture.custom_price}
                      {$smarty.capture.custom_price nofilter}
                    {else}
                      {$product.price}
                    {/if}
                  </span>

                  {hook h='displayProductPriceBlock' product=$product type='unit_price'}

                  {hook h='displayProductPriceBlock' product=$product type='weight'}
                </div>
              {/if}
            {/block}
            {block name='product_list_actions'}
              <div class="product-list-actions">
                {if $product.main_variants}
                  <a class="btn btn-primary w-100" href="{$product.url}" rel="nofollow">
                    {l s='Select' d='Shop.Theme.Actions'}
                  </a>
                {else}
                  {if $product.add_to_cart_url}
                    <form class="add-to-cart-from-listing" method="post" action="{$urls.pages.cart}?ajax=1"
                      data-add-to-cart-form data-id-product="{$product.id_product|default:$product.id}"
                      data-id-product-attribute="{$product.id_product_attribute|default:0}"
                      data-min-qty="{$product.minimal_quantity|default:1}" data-stock="{$product.quantity|intval}">
                      <input type="hidden" name="token" value="{$static_token}">
                      <input type="hidden" name="id_product" value="{$product.id_product|default:$product.id}">
                      <input type="hidden" name="id_product_attribute" value="{$product.id_product_attribute|default:0}">
                      <input type="hidden" name="qty" value="{$product.minimal_quantity|default:1}">
                      <input type="hidden" name="add" value="1">
                      <input type="hidden" name="action" value="update">

                      {* <div class="quantity-selector mb-2" data-qty-stepper>
                        <button type="button" class="btn btn-light btn-sm quantity-decrease" data-qty-decr>-</button>
                        <input type="number" class="form-control text-center qty-input" name="qty_visible"
                          value="{$product.minimal_quantity|default:1}" min="{$product.minimal_quantity|default:1}"
                          max="{$product.quantity|default:0}">
                        <button type="button" class="btn btn-light btn-sm quantity-increase" data-qty-incr>+</button>
                      </div> *}

                      {assign var=isOut value=($product.quantity <= 0)}
                      <button class="add-to-cart{if $isOut} out-of-stock{/if}" data-button-action="add-to-cart"
                        data-add-to-cart-btn data-out-label="{l s='Out of stock' d='Shop.Theme.Actions'}" type="submit"
                        {if $isOut}disabled{/if}>
                        {if $isOut}
                          {* {l s='Out of stock' d='Shop.Theme.Actions'} *}
                          <i class="material-icons remove_shopping_cart">remove_shopping_cart</i>
                        {else}
                          <img height="18px" width="18px" src="{_PS_THEME_URI_}upload/img/icons/card.png"
                            alt="{l s='Shopping cart' d='Shop.Theme.Actions'}" />
                          {* <i class="material-icons shopping-cart"></i> {l s='Add' d='Shop.Theme.Actions'} *}
                        {/if}
                      </button>
                    </form>

                  {else}
                    <a href="{$product.url}" rel="nofollow">
                      {* {l s='View' d='Shop.Theme.Actions'} *}
                      {* icon eye *}
                      <i class="material-icons visibility">visibility</i>
                    </a>
                  {/if}
                {/if}

                {hook h='displayProductListFunctionalButtons' product=$product}
              </div>

            {/block}
          </div>

          {block name='product_reviews'}
            {hook h='displayProductListReviews' product=$product}
          {/block}
        </div>

        {include file='catalog/_partials/product-flags.tpl'}
      </div>
    </article>
  </div>
{/block}