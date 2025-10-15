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
<div class="product-additional-info js-product-additional-info">
  {hook h='displayProductAdditionalInfo' product=$product}


  {** Mini features block: Age, Nombre de joueurs, Langue, Durée d'une partie **}
  <div class="product-mini-features">
    <ul class="product-mini-features-list list-unstyled d-flex flex-wrap mb-2">
      {foreach from=$product.grouped_features item=feature}
        {if preg_match('/age|âge/i', $feature.name)}
          <li class="mini-feature me-3 d-flex align-items-center" title="{$feature.name|escape:'html':'UTF-8'}">
            <i class="material-icons me-1" aria-hidden="true">child_care</i>
            <span class="feature-value">{$feature.value|escape:'html':'UTF-8'}</span>
          </li>
        {elseif preg_match('/joueur/i', $feature.name)}
          <li class="mini-feature me-3 d-flex align-items-center" title="{$feature.name|escape:'html':'UTF-8'}">
            <i class="material-icons me-1" aria-hidden="true">people</i>
            <span class="feature-value">{$feature.value|escape:'html':'UTF-8'}</span>
          </li>
        {elseif preg_match('/langue|language/i', $feature.name)}
          <li class="mini-feature me-3 d-flex align-items-center" title="{$feature.name|escape:'html':'UTF-8'}">
            <i class="material-icons me-1" aria-hidden="true">language</i>
            <span class="feature-value">{$feature.value|escape:'html':'UTF-8'}</span>
          </li>
        {elseif preg_match('/dur(e|ée|ee)|temps|min/i', $feature.name)}
          <li class="mini-feature me-3 d-flex align-items-center" title="{$feature.name|escape:'html':'UTF-8'}">
            <i class="material-icons me-1" aria-hidden="true">schedule</i>
            <span class="feature-value">{$feature.value|escape:'html':'UTF-8'}</span>
          </li>
        {/if}
      {/foreach}
    </ul>
  </div>
</div>