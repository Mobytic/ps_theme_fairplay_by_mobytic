<?php

/**
 * Module: MB Category as Catalogue
 * Description: Convert products in selected categories to catalogue mode
 * Author: Mobytic
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mb_Category_As_Catalogue extends Module
{
    private static $is_updating = false;

    public function __construct()
    {
        $this->name = 'mb_category_as_catalogue';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Mobytic';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Category as Catalogue');
        $this->description = $this->l('Turn products in selected categories into catalogue mode (hide order button)');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    /**
     * Install module
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('displayProductActions')
            && $this->registerHook('displayProductFlags')
            && $this->registerHook('displayAfterProductThumbs')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductSave')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionAfterUpdateProductFormHandler')
            && $this->registerHook('actionAfterCreateProductFormHandler')
            && Configuration::updateValue('MB_CATALOGUE_CATEGORIES', '')
            && Configuration::updateValue('MB_CATALOGUE_TAG_LABEL', '')
            && Configuration::updateValue('MB_CATALOGUE_TAG_BG', '#333333')
            && Configuration::updateValue('MB_CATALOGUE_TAG_COLOR', '#ffffff')
            && Configuration::updateValue('MB_CATALOGUE_TAG_ICON', '');
    }

    /**
     * Uninstall module
     */
    public function uninstall()
    {
        return Configuration::deleteByName('MB_CATALOGUE_CATEGORIES')
            && Configuration::deleteByName('MB_CATALOGUE_TAG_LABEL')
            && Configuration::deleteByName('MB_CATALOGUE_TAG_BG')
            && Configuration::deleteByName('MB_CATALOGUE_TAG_COLOR')
            && Configuration::deleteByName('MB_CATALOGUE_TAG_ICON')
            && parent::uninstall();
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitMbCatalogueConfig')) {
            $categories = Tools::getValue('MB_CATALOGUE_CATEGORIES');

            $tag_label = Tools::getValue('MB_CATALOGUE_TAG_LABEL');
            $tag_bg = Tools::getValue('MB_CATALOGUE_TAG_BG');
            $tag_color = Tools::getValue('MB_CATALOGUE_TAG_COLOR');
            $tag_icon = Tools::getValue('MB_CATALOGUE_TAG_ICON');

            if (is_array($categories)) {
                Configuration::updateValue('MB_CATALOGUE_CATEGORIES', json_encode($categories));
                Configuration::updateValue('MB_CATALOGUE_TAG_LABEL', $tag_label);
                Configuration::updateValue('MB_CATALOGUE_TAG_BG', $tag_bg);
                Configuration::updateValue('MB_CATALOGUE_TAG_COLOR', $tag_color);
                Configuration::updateValue('MB_CATALOGUE_TAG_ICON', $tag_icon);

                $output .= $this->displayConfirmation($this->l('Settings updated successfully'));

                // Update all products in selected categories
                $this->updateProductsInCategories($categories);
            } else {
                Configuration::updateValue('MB_CATALOGUE_CATEGORIES', '');
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Display configuration form
     */
    public function displayForm()
    {
        // Get selected categories
        $selectedCategories = json_decode(Configuration::get('MB_CATALOGUE_CATEGORIES'), true);
        if (!is_array($selectedCategories)) {
            $selectedCategories = array();
        }

        // Get all categories
        $categories = Category::getCategories($this->context->language->id, true, false);

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Category as Catalogue Settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'categories',
                        'label' => $this->l('Select Categories'),
                        'name' => 'MB_CATALOGUE_CATEGORIES',
                        'desc' => $this->l('Products in selected categories will be set to catalogue mode (order button hidden when out of stock)'),
                        'tree' => array(
                            'id' => 'categories-tree',
                            'selected_categories' => $selectedCategories,
                            'use_search' => true,
                            'use_checkbox' => true,
                            'root_category' => Category::getRootCategory()->id,
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Custom tag label'),
                        'name' => 'MB_CATALOGUE_TAG_LABEL',
                        'desc' => $this->l('Label for the custom tag shown for catalogue products')
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Tag background color'),
                        'name' => 'MB_CATALOGUE_TAG_BG',
                        'desc' => $this->l('Background color for the custom tag')
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Tag text color'),
                        'name' => 'MB_CATALOGUE_TAG_COLOR',
                        'desc' => $this->l('Text color for the custom tag')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Tag icon (CSS class)'),
                        'name' => 'MB_CATALOGUE_TAG_ICON',
                        'desc' => $this->l('Optional icon class (e.g., "fa fa-star") to show in the tag')
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMbCatalogueConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['MB_CATALOGUE_CATEGORIES[]'] = $selectedCategories;
        $helper->fields_value['MB_CATALOGUE_TAG_LABEL'] = Configuration::get('MB_CATALOGUE_TAG_LABEL');
        $helper->fields_value['MB_CATALOGUE_TAG_BG'] = Configuration::get('MB_CATALOGUE_TAG_BG');
        $helper->fields_value['MB_CATALOGUE_TAG_COLOR'] = Configuration::get('MB_CATALOGUE_TAG_COLOR');
        $helper->fields_value['MB_CATALOGUE_TAG_ICON'] = Configuration::get('MB_CATALOGUE_TAG_ICON');

        return $helper->generateForm(array($fields_form));
    }

    /**
     * Update products in selected categories
     */
    protected function updateProductsInCategories($categories)
    {
        if (empty($categories)) {
            return;
        }

        self::$is_updating = true;

        foreach ($categories as $id_category) {
            $products = Product::getProducts(
                $this->context->language->id,
                0,
                0,
                'id_product',
                'ASC',
                $id_category,
                true
            );

            foreach ($products as $product) {
                // Update directly in database to avoid triggering hooks
                Db::getInstance()->update(
                    'product',
                    array('out_of_stock' => 0),
                    'id_product = ' . (int)$product['id_product']
                );

                // Set stock quantity to 0
                Db::getInstance()->update(
                    'stock_available',
                    array('quantity' => 0),
                    'id_product = ' . (int)$product['id_product']
                );
            }
        }

        self::$is_updating = false;
    }

    /**
     * Hook: Product Save/Update
     * Automatically set out_of_stock when product is saved
     */
    public function hookActionProductSave($params)
    {
        return $this->hookActionProductUpdate($params);
    }

    public function hookActionProductUpdate($params)
    {
        // Prevent infinite loop
        if (self::$is_updating) {
            return;
        }

        if (isset($params['id_product'])) {
            $id_product = (int)$params['id_product'];
        } elseif (isset($params['product'])) {
            $id_product = (int)$params['product']->id;
        } else {
            return;
        }

        $product = new Product($id_product);
        $productCategories = $product->getCategories();

        $selectedCategories = json_decode(Configuration::get('MB_CATALOGUE_CATEGORIES'), true);
        if (!is_array($selectedCategories)) {
            return;
        }

        // Check if product is in any of the selected categories
        $isInCatalogueCategory = false;
        foreach ($productCategories as $cat) {
            if (in_array($cat, $selectedCategories)) {
                $isInCatalogueCategory = true;
                break;
            }
        }

        if ($isInCatalogueCategory && $product->out_of_stock != 0) {
            // Set flag to prevent recursion
            self::$is_updating = true;

            // Update directly in database to avoid triggering hooks again
            Db::getInstance()->update(
                'product',
                array('out_of_stock' => 0),
                'id_product = ' . (int)$id_product
            );

            // Set stock quantity to 0
            Db::getInstance()->update(
                'stock_available',
                array('quantity' => 0),
                'id_product = ' . (int)$id_product
            );

            self::$is_updating = false;
        }
    }

    /**
     * Hook: Display Product Actions
     * Hide add to cart button for products in catalogue categories
     */
    public function hookDisplayProductActions($params)
    {
        if (!isset($params['product'])) {
            return;
        }

        $product = $params['product'];
        $id_product = (int)$product['id_product'];

        $productObj = new Product($id_product);
        $productCategories = $productObj->getCategories();

        $selectedCategories = json_decode(Configuration::get('MB_CATALOGUE_CATEGORIES'), true);
        if (!is_array($selectedCategories)) {
            return;
        }

        // Check if product is in any of the selected categories
        $isInCatalogueCategory = false;
        foreach ($productCategories as $cat) {
            if (in_array($cat, $selectedCategories)) {
                $isInCatalogueCategory = true;
                break;
            }
        }

        if (!$isInCatalogueCategory) {
            return;
        }

        // Product is in catalogue category - hide add-to-cart and inject custom flag
        $css = '<style>'
            . '.product-add-to-cart, .add-to-cart, #add_to_cart, button.add-to-cart, .js-buy-btn, .buy-button, .product-page .add-to-cart, .product-miniature .add-to-cart, .quickview .product-add-to-cart, .modal .add-to-cart { display: none !important; }'
            . '</style>';

        // Build custom tag settings
        $tag_label = Configuration::get('MB_CATALOGUE_TAG_LABEL');
        $tag_bg = Configuration::get('MB_CATALOGUE_TAG_BG');
        $tag_color = Configuration::get('MB_CATALOGUE_TAG_COLOR');
        $tag_icon = Configuration::get('MB_CATALOGUE_TAG_ICON');

        // Inject JS to replace/hide existing flags and insert our custom flag into the .product-flags list
        $js = '<script>(function(){'
            . 'var label=' . json_encode($tag_label) . ';'
            . 'var bg=' . json_encode($tag_bg) . ';'
            . 'var color=' . json_encode($tag_color) . ';'
            . 'var icon=' . json_encode($tag_icon) . ';'
            . 'function inject(){'
            . 'var containers = document.querySelectorAll(".product-flags");'
            . 'if(!containers.length) return;'
            . 'containers.forEach(function(container){'
            . 'try{'
            . 'var children = container.querySelectorAll("li");'
            . 'children.forEach(function(ch){ if(!ch.classList.contains("mb-cat-custom")) ch.style.display="none"; });'
            . 'var existing = container.querySelector(".mb-cat-custom");'
            . 'if(existing) return;'
            . 'var li=document.createElement("li");'
            . 'li.className="product-flag mb-cat-custom";'
            . 'li.style.display="inline-block";li.style.marginRight="5px";li.style.padding="4px 8px";'
            . 'if(bg) li.style.background=bg; if(color) li.style.color=color; li.style.fontSize="12px";'
            . 'li.innerHTML = (icon? ("<i class=\""+icon+"\" style=\"margin-right:4px\"></i>") : "") + (label||"Catalogue");'
            . 'container.insertBefore(li, container.firstChild);'
            . '}catch(e){}'
            . '});'
            . '}'
            . 'inject();'
            . 'setTimeout(inject, 100);'
            . 'setTimeout(inject, 500);'
            . '})();</script>';

        return $css . $js;
    }

    /**
     * Hook: Display Product Flags
     * Show custom flag and hide others for catalogue products
     */
    public function hookDisplayProductFlags($params)
    {
        if (!isset($params['product'])) {
            return;
        }

        $product = $params['product'];
        $id_product = isset($product['id_product']) ? (int)$product['id_product'] : (int)$product['id'];

        if (!$id_product) {
            return;
        }

        $productObj = new Product($id_product);
        $productCategories = $productObj->getCategories();

        $selectedCategories = json_decode(Configuration::get('MB_CATALOGUE_CATEGORIES'), true);
        if (!is_array($selectedCategories)) {
            return;
        }

        // Check if product is in any of the selected categories
        $isInCatalogueCategory = false;
        foreach ($productCategories as $cat) {
            if (in_array($cat, $selectedCategories)) {
                $isInCatalogueCategory = true;
                break;
            }
        }

        if (!$isInCatalogueCategory) {
            return;
        }

        // Build custom tag settings
        $tag_label = Configuration::get('MB_CATALOGUE_TAG_LABEL');
        $tag_bg = Configuration::get('MB_CATALOGUE_TAG_BG');
        $tag_color = Configuration::get('MB_CATALOGUE_TAG_COLOR');
        $tag_icon = Configuration::get('MB_CATALOGUE_TAG_ICON');

        $style = '';
        if ($tag_bg) {
            $style .= 'background:' . htmlspecialchars($tag_bg) . ';';
        }
        if ($tag_color) {
            $style .= 'color:' . htmlspecialchars($tag_color) . ';';
        }
        $style .= 'display:inline-block;margin-right:5px;padding:4px 8px;font-size:12px;';

        $icon_html = '';
        if ($tag_icon) {
            $icon_html = '<i class="' . htmlspecialchars($tag_icon) . '" style="margin-right:4px"></i>';
        }

        // Return custom flag HTML + CSS to hide other flags
        $output = '<style>.product-flags li:not(.mb-cat-custom) { display: none !important; }</style>';
        $output .= '<li class="product-flag mb-cat-custom" style="' . $style . '">';
        $output .= $icon_html . htmlspecialchars($tag_label ?: 'Catalogue');
        $output .= '</li>';

        return $output;
    }

    /**
     * Hook: Display After Product Thumbs (Product Page)
     * Inject custom flag on product page
     */
    public function hookDisplayAfterProductThumbs($params)
    {
        if (!isset($params['product'])) {
            return;
        }

        $product = $params['product'];
        $id_product = isset($product['id_product']) ? (int)$product['id_product'] : (isset($product['id']) ? (int)$product['id'] : 0);

        if (!$id_product) {
            return;
        }

        $productObj = new Product($id_product);
        $productCategories = $productObj->getCategories();

        $selectedCategories = json_decode(Configuration::get('MB_CATALOGUE_CATEGORIES'), true);
        if (!is_array($selectedCategories)) {
            return;
        }

        // Check if product is in any of the selected categories
        $isInCatalogueCategory = false;
        foreach ($productCategories as $cat) {
            if (in_array($cat, $selectedCategories)) {
                $isInCatalogueCategory = true;
                break;
            }
        }

        if (!$isInCatalogueCategory) {
            return;
        }

        // Build custom tag settings
        $tag_label = Configuration::get('MB_CATALOGUE_TAG_LABEL');
        $tag_bg = Configuration::get('MB_CATALOGUE_TAG_BG');
        $tag_color = Configuration::get('MB_CATALOGUE_TAG_COLOR');
        $tag_icon = Configuration::get('MB_CATALOGUE_TAG_ICON');

        // Inject JS and CSS to manipulate flags on product page
        $output = '<script>(function(){';
        $output .= 'var label=' . json_encode($tag_label) . ';';
        $output .= 'var bg=' . json_encode($tag_bg) . ';';
        $output .= 'var color=' . json_encode($tag_color) . ';';
        $output .= 'var icon=' . json_encode($tag_icon) . ';';
        $output .= 'function injectFlag(){';
        $output .= '  var container = document.querySelector(".product-flags");';
        $output .= '  if(!container) return;';
        $output .= '  var children = container.querySelectorAll("li");';
        $output .= '  children.forEach(function(ch){ if(!ch.classList.contains("mb-cat-custom")) ch.style.display="none"; });';
        $output .= '  var existing = container.querySelector(".mb-cat-custom");';
        $output .= '  if(existing) { existing.style.display="inline-block"; return; }';
        $output .= '  var li=document.createElement("li");';
        $output .= '  li.className="product-flag mb-cat-custom";';
        $output .= '  li.style.display="inline-block";li.style.marginRight="5px";li.style.padding="4px 8px";';
        $output .= '  if(bg) li.style.background=bg; if(color) li.style.color=color; li.style.fontSize="12px";';
        $output .= '  li.innerHTML = (icon? ("<i class=\""+icon+"\" style=\"margin-right:4px\"></i>") : "") + (label||"Catalogue");';
        $output .= '  container.insertBefore(li, container.firstChild);';
        $output .= '}';
        $output .= 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",injectFlag);}else{injectFlag();}';
        $output .= 'setTimeout(injectFlag, 100);';
        $output .= '})();</script>';

        return $output;
    }

    /**
     * Hook: After product form update (PS 1.7+)
     * Force out_of_stock after form save
     */
    public function hookActionAfterUpdateProductFormHandler($params)
    {
        return $this->forceOutOfStockAfterFormSave($params);
    }

    /**
     * Hook: After product form create (PS 1.7+)
     * Force out_of_stock after form save
     */
    public function hookActionAfterCreateProductFormHandler($params)
    {
        return $this->forceOutOfStockAfterFormSave($params);
    }

    /**
     * Hook: Display Back Office Header
     * Inject small JS in product edit page to log out_of_stock value
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        return;

        $id_product = (int)Tools::getValue('id_product');
        if (!$id_product) {
            return;
        }

        $product = new Product($id_product);
        // $out_of_stock = (int)$product->out_of_stock;

        $script = '<script>console.log("mb_category_as_catalogue: product ' . (string)$id_product . ' out_of_stock:", ' . json_encode($product) . ');</script>';

        return $script;
    }

    /**
     * Force out_of_stock value after form save
     */
    protected function forceOutOfStockAfterFormSave($params)
    {
        return;

        // Prevent infinite loop
        if (self::$is_updating) {
            return;
        }

        $id_product = null;

        if (isset($params['id'])) {
            $id_product = (int)$params['id'];
        } elseif (isset($params['form_data']['id'])) {
            $id_product = (int)$params['form_data']['id'];
        }

        if (!$id_product) {
            return;
        }

        $product = new Product($id_product);
        $productCategories = $product->getCategories();

        $selectedCategories = json_decode(Configuration::get('MB_CATALOGUE_CATEGORIES'), true);
        if (!is_array($selectedCategories)) {
            return;
        }

        // Check if product is in any of the selected categories
        foreach ($productCategories as $cat) {
            if (in_array($cat, $selectedCategories)) {
                // Set flag to prevent recursion
                self::$is_updating = true;

                // Update directly in database: set "refuse orders when out of stock"
                Db::getInstance()->update(
                    'product',
                    array('out_of_stock' => 0),
                    'id_product = ' . (int)$id_product
                );

                // Set stock quantity to 0
                Db::getInstance()->update(
                    'stock_available',
                    array('quantity' => 0),
                    'id_product = ' . (int)$id_product
                );

                self::$is_updating = false;
                break;
            }
        }
    }
}
