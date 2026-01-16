<div id="mb-availability-modal" class="mb-availability-modal">
    <div class="mb-availability-modal-backdrop" data-mb-availability-close="1"></div>
    <div class="mb-availability-modal-content">
        <button class="mb-availability-modal-close" data-mb-availability-close="1">×</button>

        <h3>{l s='Check availability' mod='mb_availability_check'}</h3>

        <p>{$availability_message|escape:'html':'UTF-8'}</p>

        <form method="post" action="{$availability_send_url|escape:'html':'UTF-8'}">
            <input type="hidden" name="qty" id="mb_quantity" value="1" />

            <div class="form-group">
                <label>{l s='Product' mod='mb_availability_check'}</label>
                {if isset($availability_products) && $availability_products|@count > 0}
                    <select class="form-control" name="id_product" id="mb_product_select">
                        {foreach from=$availability_products item=prod}
                            <option value="{$prod.id}"{if $prod.id == $availability_product_id} selected{/if}>{$prod.name|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                {else}
                    <input type="hidden" name="id_product" value="{$availability_product_id|intval}" />
                    <input type="text" class="form-control" value="{$availability_product_name|escape:'html':'UTF-8'}" readonly="readonly" />
                {/if}
            </div>

            <div class="form-group">
                <label for="mb_quantity_visible">{l s='Quantité souhaitée' mod='mb_availability_check'}</label>
                <input id="mb_quantity_visible" type="number" min="1" name="mb_quantity_visible" class="form-control" value="1" required />
            </div>

            <div class="form-group">
                <label for="mb_firstname">{l s='First name' mod='mb_availability_check'}</label>
                <input id="mb_firstname" name="firstname" class="form-control" />
            </div>

            <div class="form-group">
                <label for="mb_lastname">{l s='Last name' mod='mb_availability_check'}</label>
                <input id="mb_lastname" name="lastname" class="form-control" />
            </div>

            <div class="form-group">
                <label for="mb_email">{l s='Email' mod='mb_availability_check'}</label>
                <input id="mb_email" type="email" name="email" class="form-control" />
            </div>

            <div class="form-group">
                <label for="mb_phone">{l s='Phone' mod='mb_availability_check'}</label>
                <input id="mb_phone" type="text" name="phone" class="form-control" />
            </div>

            <button type="submit" class="btn btn-primary">
                {l s='Send request' mod='mb_availability_check'}
            </button>
        </form>
    </div>
</div>

{if isset($availability_products) && $availability_products|@count > 0}
<script>
    window.availabilityProducts = {$availability_products|json_encode};
</script>
{/if}

<style>
{literal}
#mb-availability-modal {
    display: none;
    position: fixed !important;
    z-index: 999999 !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
}
#mb-availability-modal.mb-availability-open {
    display: block !important;
    position: fixed !important;
    z-index: 999999 !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
}
.mb-availability-modal-backdrop {
    position: fixed !important;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999998;
}
.mb-availability-modal-content {
    position: fixed !important;
    max-width: 500px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 20px;
    background: #fff;
    max-height: 80vh;
    overflow-y: auto;
    z-index: 999999;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.5);
}
.mb-availability-modal-close {
    position: absolute;
    top: 5px;
    right: 10px;
    border: 0;
    background: transparent;
    font-size: 24px;
    cursor: pointer;
    z-index: 1000000;
    line-height: 1;
    padding: 5px 10px;
}
.mb-availability-modal .form-group {
    margin-bottom: 15px;
}
.mb-availability-modal .form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    text-align: left !important;
}
.mb-availability-modal .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}
.mb-availability-modal h3 {
    margin-top: 0;
    margin-bottom: 15px;
}
{/literal}
</style>
