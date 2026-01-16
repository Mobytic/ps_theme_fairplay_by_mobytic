<a href="{$href|escape:'html':'UTF-8'}" class="btn btn-default{if isset($class)} {$class}{/if}" title="{$action|escape:'html':'UTF-8'}"{if isset($id)} data-id="{$id|intval}"{/if}>
    <i class="{$icon|escape:'html':'UTF-8'}"></i>
    {$action|escape:'html':'UTF-8'}
</a>
