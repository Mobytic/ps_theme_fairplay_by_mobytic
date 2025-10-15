{** Mini features block: Age, Nombre de joueurs, Langue, Durée d'une partie **}
<div class="product-mini-features">
    {assign var=mf_age value=''}
    {assign var=mf_players value=''}
    {assign var=mf_lang value=''}
    {assign var=mf_duration value=''}

    {foreach from=$product.grouped_features item=feature}
        {if preg_match('/[aàâäÀÂÄ]ge/u', $feature.name)}
            {assign var=mf_age value=$feature.value}
        {elseif preg_match('/joueur/i', $feature.name)}
            {assign var=mf_players value=$feature.value}
        {elseif preg_match('/langue|language/i', $feature.name)}
            {assign var=mf_lang value=$feature.value}
        {elseif preg_match('/dur(e|ée|ee)|temps|min/i', $feature.name)}
            {assign var=mf_duration value=$feature.value}
        {/if}
    {/foreach}

    <ul class="product-mini-features-list list-unstyled d-flex flex-wrap mb-2">
        {if $mf_age}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Age' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1" aria-hidden="true">cake</i>
                <span class="feature-value">{$mf_age|escape:'html':'UTF-8'}</span>
            </li>
        {/if}

        {if $mf_players}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Players' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1" aria-hidden="true">people</i>
                <span class="feature-value">{$mf_players|escape:'html':'UTF-8'}</span>
            </li>
        {/if}

        {if $mf_lang}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Language' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1" aria-hidden="true">language</i>
                <span class="feature-value">{$mf_lang|escape:'html':'UTF-8'}</span>
            </li>
        {/if}

        {if $mf_duration}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Duration' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1" aria-hidden="true">schedule</i>
                <span class="feature-value">{$mf_duration|escape:'html':'UTF-8'}</span>
            </li>
        {/if}
    </ul>
</div>