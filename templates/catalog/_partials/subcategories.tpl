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
            <div class="subcategory-image">
              <a href="{$subcategory.link}" title="{$subcategory.name|escape:'html':'UTF-8'}" class="img">
                {assign var=image_url value=$subcategory.image.category_default.url|default:$subcategory.image.bySize.category_default.url|default:$subcategory.thumbnail.category_default.url|default:$subcategory.image.url|default:$urls.no_picture_image.category_default.url}
                {assign var=image_avif value=$subcategory.image.category_default.sources.avif|default:$subcategory.image.bySize.category_default.sources.avif|default:$subcategory.thumbnail.category_default.sources.avif|default:$urls.no_picture_image.category_default.sources.avif}
                {assign var=image_webp value=$subcategory.image.category_default.sources.webp|default:$subcategory.image.bySize.category_default.sources.webp|default:$subcategory.thumbnail.category_default.sources.webp|default:$urls.no_picture_image.category_default.sources.webp}
                {assign var=image_width value=$subcategory.image.category_default.width|default:$subcategory.image.bySize.category_default.width|default:$subcategory.thumbnail.category_default.width|default:$urls.no_picture_image.category_default.width}
                {assign var=image_height value=$subcategory.image.category_default.height|default:$subcategory.image.bySize.category_default.height|default:$subcategory.thumbnail.category_default.height|default:$urls.no_picture_image.category_default.height}

                <picture>
                  {if !empty($image_avif)}
                    <source srcset="{$image_avif}" type="image/avif">
                  {/if}
                  {if !empty($image_webp)}
                    <source srcset="{$image_webp}" type="image/webp">
                  {/if}
                  <img class="img-fluid" src="{$image_url}" alt="{$subcategory.name|escape:'html':'UTF-8'}" loading="lazy"
                    width="{$image_width}" height="{$image_height}" decoding="async" />
                </picture>

              </a>
            </div>

            <span>
              <a class="subcategory-name" href="{$subcategory.link}">
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