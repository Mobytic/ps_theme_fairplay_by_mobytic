 {** Mini features block: Age, Nombre de joueurs, Langue, Durée d'une partie **}
 <div class="product-mini-features">
     <ul class="product-mini-features-list list-unstyled d-flex flex-wrap mb-2">
         {foreach from=$product.grouped_features item=feature}
             {$feature.name|@var_dump}
             {if preg_match('/[aàâäÀÂÄ]ge/u', $feature.name)}
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