<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Mb_SitemapAccordion extends Module
{
    public function __construct()
    {
        $this->name = 'mb_sitemapaccordion';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mobytic';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Sitemap Accordion');
        $this->description = $this->l('Adds accordion behavior with tree lines to the sitemap, without editing theme templates.');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install() && $this->registerHook('header');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookHeader($params)
    {
        // Backwards-compatible asset inclusion
        if (method_exists($this->context->controller, 'registerStylesheet')) {
            $this->context->controller->registerStylesheet(
                'module-sitemapaccordion-css',
                $this->_path . 'views/css/sitemap-accordion.css',
                ['media' => 'all', 'priority' => 150]
            );
        } else {
            $this->context->controller->addCSS($this->_path . 'views/css/sitemap-accordion.css');
        }

        if (method_exists($this->context->controller, 'registerJavascript')) {
            $this->context->controller->registerJavascript(
                'module-sitemapaccordion-js',
                $this->_path . 'views/js/sitemap-accordion.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        } else {
            $this->context->controller->addJS($this->_path . 'views/js/sitemap-accordion.js');
        }
    }
}
