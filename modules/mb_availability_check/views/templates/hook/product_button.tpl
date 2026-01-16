{* This block is only output when the feature is enabled for this product *}
<style>
.mb-availability-btn-custom {
    background: {$mb_availability_button_bg_color} !important;
    color: {$mb_availability_button_text_color} !important;
    border-color: {$mb_availability_button_bg_color} !important;
}
</style>
<a href="#"
   class="btn btn-primary mb-availability-btn mb-availability-btn-custom"
   data-mb-availability-btn="1">
    {$mb_availability_button_label|escape:'html':'UTF-8'}
</a>
