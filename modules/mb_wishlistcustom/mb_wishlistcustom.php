<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Mb_Wishlistcustom extends Module
{
    public function __construct()
    {
        $this->name = 'mb_wishlistcustom';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mobytic';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Wishlist Customization');
        $this->description = $this->l('Fixes the blockwishlist modal stacking issue and allows customizing its design.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Enqueue the CSS/JS overrides for the blockwishlist modal (positioning + design).
     */
    public function hookDisplayHeader()
    {
        $this->context->controller->registerStylesheet(
            'mb-wishlistcustom-css',
            'modules/' . $this->name . '/views/css/mb_wishlistcustom.css'
        );

        $this->context->controller->registerJavascript(
            'mb-wishlistcustom-js',
            'modules/' . $this->name . '/views/js/mb_wishlistcustom.js'
        );
    }
}
