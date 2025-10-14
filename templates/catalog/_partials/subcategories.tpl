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
{if !empty($subcategories)}
  {if (isset($display_subcategories) && $display_subcategories eq 1) || !isset($display_subcategories) }
    <div id="subcategories" class="mb-card card-block">
      {* <h2 class="subcategory-heading">{l s='Subcategories' d='Shop.Theme.Category'}</h2> *}

      <ul class="subcategories-list">
        {foreach from=$subcategories item=subcategory}
          <li>
            {* Build the href: use provided link if present, else generate it *}
            {assign var=href value=$subcategory.link|default:$link->getCategoryLink($subcategory.id_category, $subcategory.link_rewrite)}

            {* Build the image URL: if we have an id_image, point to /img/c/... ; else use the category placeholder *}
            {if $subcategory.id_image}
              {assign var=img_url value=$link->getImageLink($subcategory.link_rewrite, 'c/'|cat:$subcategory.id_image, 'category_default')}
            {else}
              {assign var=img_url value="{$urls.img_cat_url}{$language.iso_code}-default-category_default.jpg"}
            {/if}

            <div class="subcategory-image">
              <a href="{$href}" title="{$subcategory.name|escape:'html':'UTF-8'}" class="img">
                <img class="img-fluid" src="{$img_url}" alt="{$subcategory.name|escape:'html':'UTF-8'}" loading="lazy"
                  decoding="async" />
              </a>
            </div>

            <span>
              <a class="subcategory-name" href="{$href}">
                {$subcategory.name|truncate:25:'...'|escape:'html':'UTF-8'}
              </a>
            </span>

            {if $subcategory.description}
              <div class="cat_desc">{$subcategory.description|unescape:'html' nofilter}</div>
            {/if}
          </li>
        {/foreach}
      </ul>
    </div>
  {/if}
{/if}