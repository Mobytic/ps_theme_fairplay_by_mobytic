{** Mini features block: Age, Nombre de joueurs, Langue, Durée d'une partie **}
<div class="product-mini-features">
    {assign var=mf_age value=''}
    {assign var=mf_players value=''}
    {assign var=mf_players_min value=''}
    {assign var=mf_players_max value=''}
    {assign var=mf_lang value=''}
    {assign var=mf_duration value=''}

    {foreach from=$product.grouped_features item=feature}
        {if preg_match('/[aàâäÀÂÄ]ge/u', $feature.name)}
            {assign var=mf_age value=$feature.value}
        {elseif preg_match('/joueur.*(min|minimum)/iu', $feature.name)}
            {assign var=mf_players_min value=$feature.value}
        {elseif preg_match('/joueur.*(max|maximum)/iu', $feature.name)}
            {assign var=mf_players_max value=$feature.value}
        {elseif preg_match('/joueur/i', $feature.name)}
            {assign var=mf_players value=$feature.value}
        {elseif preg_match('/langue|language/i', $feature.name)}
            {assign var=mf_lang value=$feature.value}
        {elseif preg_match('/dur(e|ée|ee)|temps|min/i', $feature.name)}
            {if !$mf_duration}
                {assign var=mf_duration value=$feature.value}
            {/if}
        {/if}
    {/foreach}

    {** Post-process values: duration, age, language **}
    {if $mf_duration}
        {assign var=tmp value=$mf_duration|regex_replace:"/.*\(([^)]*)\).*/":"$1"}
        {if $tmp == $mf_duration}
            {assign var=tmp value=$mf_duration}
        {/if}
        {assign var=tmp value=$tmp|regex_replace:"/[–—−]/":"-"}
        {assign var=tmp value=$tmp|regex_replace:"/\\s*-\\s*/":"-"}
        {assign var=tmp value=$tmp|regex_replace:"/-+/":"-"}
        {assign var=tmp value=$tmp|regex_replace:"/\\s*\\+\\s*$/":"+"}
        {assign var=tmp value=$tmp|regex_replace:"/^\\s*[-–—−]\\s*(\\d+)/":"<$1"}
        {assign var=tmp value=$tmp|regex_replace:"/^(?:\\s*infini\b.*)/i":"∞"}
        {assign var=tmp value=$tmp|regex_replace:"/^\\s+|\\s+$/":""}
        {assign var=mf_duration_disp value=$tmp}
    {/if}

    {if $mf_age}
        {assign var=mf_age_disp value=$mf_age|regex_replace:"/.*?(\d+).*/":"$1+"}
    {/if}

    {if $mf_lang}
        {if preg_match('/franc|\bfr\b|françois|français|france/i', $mf_lang)}
            {assign var=mf_lang_disp value='FR'}
        {elseif preg_match('/angl|\ben\b|english|anglais/i', $mf_lang)}
            {assign var=mf_lang_disp value='EN'}
        {else}
            {assign var=mf_lang_disp value=$mf_lang}
        {/if}
    {/if}

    <ul class="product-mini-features-list list-unstyled d-flex flex-wrap mb-2">
        {if $mf_players_min}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Joueurs (min)' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1 mi-small" style="font-size: 1rem;" aria-hidden="true">person</i>
                <span class="feature-value">{$mf_players_min|escape:'html':'UTF-8'}</span>
            </li>
        {/if}

        {if $mf_players_max}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Joueurs (max)' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1 mi-large" aria-hidden="true">person</i>
                <span class="feature-value">{$mf_players_max|escape:'html':'UTF-8'}</span>
            </li>
        {/if}

        {if !$mf_players_min && !$mf_players_max && $mf_players}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Joueurs' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1" aria-hidden="true">people</i>
                <span class="feature-value">{$mf_players|escape:'html':'UTF-8'}</span>
            </li>
        {/if}

        {if $mf_duration_disp}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Durée' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1" aria-hidden="true">schedule</i>
                <span class="feature-value">{$mf_duration_disp|escape:'html':'UTF-8'}</span>
            </li>
        {/if}
        
        {if $mf_age_disp}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Âge' d='Shop.Theme.Catalog'}">
                <i class="material-icons me-1" aria-hidden="true">cake</i>
                <span class="feature-value">{$mf_age_disp|escape:'html':'UTF-8'}</span>
            </li>
        {/if}


        {if $mf_lang_disp}
            <li class="mini-feature me-3 d-flex align-items-center" title="{l s='Langue' d='Shop.Theme.Catalog'}">
                {if $mf_lang_disp == 'FR'}
                    <img src="/themes/fairplay-by-mobytic/assets/img/flags/fr.svg" alt="FR" class="me-1" style="width:20px;height:14px;"/>
                {elseif $mf_lang_disp == 'EN'}
                    <img src="/themes/fairplay-by-mobytic/assets/img/flags/en.svg" alt="EN" class="me-1" style="width:20px;height:14px;"/>
                {else}
                    <span class="me-1">{$mf_lang_disp|escape:'html':'UTF-8'}</span>
                {/if}
            </li>
        {/if}

    </ul>
</div>