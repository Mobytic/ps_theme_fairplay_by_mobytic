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
            && Configuration::updateValue('MB_CATALOGUE_TAG_ICON', '')
            && Configuration::updateValue('MB_CATALOGUE_MESSAGE', json_encode(array()))
            && Configuration::updateValue('MB_CATALOGUE_MESSAGE_ENABLED', 1)
            && Configuration::updateValue('MB_CATALOGUE_HIDE_PRICES', 0)
            && Configuration::updateValue('MB_CATALOGUE_CARD_BG', '#f8f9fa')
            && Configuration::updateValue('MB_CATALOGUE_CARD_TEXT_COLOR', '#333333')
            && Configuration::updateValue('MB_CATALOGUE_CARD_BORDER_COLOR', '#dee2e6')
            && Configuration::updateValue('MB_CATALOGUE_CARD_BORDER_RADIUS', '8')
            && Configuration::updateValue('MB_CATALOGUE_CARD_PADDING', '20')
            && Configuration::updateValue('MB_CATALOGUE_CARD_FONT_SIZE', '14')
            && Configuration::updateValue('MB_CATALOGUE_CARD_BOX_SHADOW', '0 2px 4px rgba(0,0,0,0.1)');
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
            && Configuration::deleteByName('MB_CATALOGUE_MESSAGE')
            && Configuration::deleteByName('MB_CATALOGUE_MESSAGE_ENABLED')
            && Configuration::deleteByName('MB_CATALOGUE_HIDE_PRICES')
            && Configuration::deleteByName('MB_CATALOGUE_CARD_BG')
            && Configuration::deleteByName('MB_CATALOGUE_CARD_TEXT_COLOR')
            && Configuration::deleteByName('MB_CATALOGUE_CARD_BORDER_COLOR')
            && Configuration::deleteByName('MB_CATALOGUE_CARD_BORDER_RADIUS')
            && Configuration::deleteByName('MB_CATALOGUE_CARD_PADDING')
            && Configuration::deleteByName('MB_CATALOGUE_CARD_FONT_SIZE')
            && Configuration::deleteByName('MB_CATALOGUE_CARD_BOX_SHADOW')
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
            $raw_messages = Tools::getValue('MB_CATALOGUE_MESSAGE');

            $message_enabled = Tools::getValue('MB_CATALOGUE_MESSAGE_ENABLED');

            // Normalize multilingual messages: HelperForm may submit either an array
            // under 'MB_CATALOGUE_MESSAGE' or individual keys like 'MB_CATALOGUE_MESSAGE_<id_lang>'.
            $messages = array();
            $languages = Language::getLanguages(false);
            if (is_array($raw_messages)) {
                $messages = $raw_messages;
            } else {
                foreach ($languages as $lang) {
                    $id_lang = $lang['id_lang'];
                    $val = Tools::getValue('MB_CATALOGUE_MESSAGE_' . $id_lang);
                    if ($val === null) {
                        // try array-style fallback
                        $arr = Tools::getValue('MB_CATALOGUE_MESSAGE');
                        if (is_array($arr) && isset($arr[$id_lang])) {
                            $val = $arr[$id_lang];
                        } else {
                            $val = '';
                        }
                    }
                    $messages[$id_lang] = $val;
                }
            }

            if (is_array($categories)) {
                Configuration::updateValue('MB_CATALOGUE_CATEGORIES', json_encode($categories));
                Configuration::updateValue('MB_CATALOGUE_TAG_LABEL', $tag_label);
                Configuration::updateValue('MB_CATALOGUE_TAG_BG', $tag_bg);
                Configuration::updateValue('MB_CATALOGUE_TAG_COLOR', $tag_color);
                Configuration::updateValue('MB_CATALOGUE_TAG_ICON', $tag_icon);
                Configuration::updateValue('MB_CATALOGUE_MESSAGE_ENABLED', (int)$message_enabled);
                $hide_prices = Tools::getValue('MB_CATALOGUE_HIDE_PRICES');
                Configuration::updateValue('MB_CATALOGUE_HIDE_PRICES', (int)$hide_prices);
                
                // Save card design settings
                Configuration::updateValue('MB_CATALOGUE_CARD_BG', Tools::getValue('MB_CATALOGUE_CARD_BG'));
                Configuration::updateValue('MB_CATALOGUE_CARD_TEXT_COLOR', Tools::getValue('MB_CATALOGUE_CARD_TEXT_COLOR'));
                Configuration::updateValue('MB_CATALOGUE_CARD_BORDER_COLOR', Tools::getValue('MB_CATALOGUE_CARD_BORDER_COLOR'));
                Configuration::updateValue('MB_CATALOGUE_CARD_BORDER_RADIUS', (int)Tools::getValue('MB_CATALOGUE_CARD_BORDER_RADIUS'));
                Configuration::updateValue('MB_CATALOGUE_CARD_PADDING', (int)Tools::getValue('MB_CATALOGUE_CARD_PADDING'));
                Configuration::updateValue('MB_CATALOGUE_CARD_FONT_SIZE', (int)Tools::getValue('MB_CATALOGUE_CARD_FONT_SIZE'));
                Configuration::updateValue('MB_CATALOGUE_CARD_BOX_SHADOW', Tools::getValue('MB_CATALOGUE_CARD_BOX_SHADOW'));
                // Save multilingual messages as JSON
                if (is_array($messages)) {
                    Configuration::updateValue('MB_CATALOGUE_MESSAGE', json_encode($messages));
                } else {
                    Configuration::updateValue('MB_CATALOGUE_MESSAGE', json_encode(array()));
                }

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
                        'type' => 'switch',
                        'label' => $this->l('Enable custom message'),
                        'name' => 'MB_CATALOGUE_MESSAGE_ENABLED',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'mb_catalogue_msg_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'mb_catalogue_msg_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                        'desc' => $this->l('Enable or disable the custom message shown where Add to cart is hidden')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Hide prices'),
                        'name' => 'MB_CATALOGUE_HIDE_PRICES',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'mb_catalogue_hide_prices_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'mb_catalogue_hide_prices_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        ),
                        'desc' => $this->l('Hide product prices for catalogue products')
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Custom message (shown instead of Add to cart)'),
                        'name' => 'MB_CATALOGUE_MESSAGE',
                        'lang' => true,
                        'cols' => 40,
                        'rows' => 6,
                        'autoload_rte' => true,
                        'desc' => $this->l('Message displayed where the add-to-cart button is hidden (multilingual). HTML is allowed.')
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
                    array(
                        'type' => 'color',
                        'label' => $this->l('Card background color'),
                        'name' => 'MB_CATALOGUE_CARD_BG',
                        'desc' => $this->l('Background color for the custom message card')
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Card text color'),
                        'name' => 'MB_CATALOGUE_CARD_TEXT_COLOR',
                        'desc' => $this->l('Text color for the custom message card')
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->l('Card border color'),
                        'name' => 'MB_CATALOGUE_CARD_BORDER_COLOR',
                        'desc' => $this->l('Border color for the custom message card')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Card border radius (px)'),
                        'name' => 'MB_CATALOGUE_CARD_BORDER_RADIUS',
                        'desc' => $this->l('Border radius for the custom message card (default: 8)')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Card padding (px)'),
                        'name' => 'MB_CATALOGUE_CARD_PADDING',
                        'desc' => $this->l('Padding for the custom message card (default: 20)')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Card font size (px)'),
                        'name' => 'MB_CATALOGUE_CARD_FONT_SIZE',
                        'desc' => $this->l('Font size for the custom message card (default: 14)')
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Card box shadow'),
                        'name' => 'MB_CATALOGUE_CARD_BOX_SHADOW',
                        'desc' => $this->l('Box shadow for the custom message card (default: 0 2px 4px rgba(0,0,0,0.1))')
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
        $helper->languages = Language::getLanguages(false);
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
        $helper->fields_value['MB_CATALOGUE_MESSAGE_ENABLED'] = Configuration::get('MB_CATALOGUE_MESSAGE_ENABLED');
        $helper->fields_value['MB_CATALOGUE_HIDE_PRICES'] = Configuration::get('MB_CATALOGUE_HIDE_PRICES');
        $helper->fields_value['MB_CATALOGUE_CARD_BG'] = Configuration::get('MB_CATALOGUE_CARD_BG');
        $helper->fields_value['MB_CATALOGUE_CARD_TEXT_COLOR'] = Configuration::get('MB_CATALOGUE_CARD_TEXT_COLOR');
        $helper->fields_value['MB_CATALOGUE_CARD_BORDER_COLOR'] = Configuration::get('MB_CATALOGUE_CARD_BORDER_COLOR');
        $helper->fields_value['MB_CATALOGUE_CARD_BORDER_RADIUS'] = Configuration::get('MB_CATALOGUE_CARD_BORDER_RADIUS');
        $helper->fields_value['MB_CATALOGUE_CARD_PADDING'] = Configuration::get('MB_CATALOGUE_CARD_PADDING');
        $helper->fields_value['MB_CATALOGUE_CARD_FONT_SIZE'] = Configuration::get('MB_CATALOGUE_CARD_FONT_SIZE');
        $helper->fields_value['MB_CATALOGUE_CARD_BOX_SHADOW'] = Configuration::get('MB_CATALOGUE_CARD_BOX_SHADOW');
        // Load multilingual messages into helper fields
        $storedMessages = json_decode(Configuration::get('MB_CATALOGUE_MESSAGE'), true);
        if (!is_array($storedMessages)) {
            $storedMessages = array();
        }
        foreach (Language::getLanguages(false) as $lang) {
            $id_lang = $lang['id_lang'];
            $helper->fields_value['MB_CATALOGUE_MESSAGE'][$id_lang] = isset($storedMessages[$id_lang]) ? $storedMessages[$id_lang] : '';
        }

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
        $hide_prices = (int) Configuration::get('MB_CATALOGUE_HIDE_PRICES');

        // Build custom tag settings
        $tag_label = Configuration::get('MB_CATALOGUE_TAG_LABEL'); 
        $tag_bg = Configuration::get('MB_CATALOGUE_TAG_BG'); 
        $tag_color = Configuration::get('MB_CATALOGUE_TAG_COLOR'); 
        $tag_icon = Configuration::get('MB_CATALOGUE_TAG_ICON'); 

        // Prepare multilingual message for current language if enabled
        $enabled = (int) Configuration::get('MB_CATALOGUE_MESSAGE_ENABLED');
        $storedMessages = json_decode(Configuration::get('MB_CATALOGUE_MESSAGE'), true);
        $message = '';
        if ($enabled) {
            $currentLang = isset($this->context->language->id) ? $this->context->language->id : null;
            if ($currentLang && is_array($storedMessages) && isset($storedMessages[$currentLang])) {
                // Decode HTML entities to render HTML properly in front-end
                $message = html_entity_decode($storedMessages[$currentLang], ENT_QUOTES, 'UTF-8');
            }
        }

        // Get card design settings
        $card_bg = Configuration::get('MB_CATALOGUE_CARD_BG') ?: '#f8f9fa';
        $card_text_color = Configuration::get('MB_CATALOGUE_CARD_TEXT_COLOR') ?: '#333333';
        $card_border_color = Configuration::get('MB_CATALOGUE_CARD_BORDER_COLOR') ?: '#dee2e6';
        $card_border_radius = Configuration::get('MB_CATALOGUE_CARD_BORDER_RADIUS') ?: '8';
        $card_padding = Configuration::get('MB_CATALOGUE_CARD_PADDING') ?: '20';
        $card_font_size = Configuration::get('MB_CATALOGUE_CARD_FONT_SIZE') ?: '14';
        $card_box_shadow = Configuration::get('MB_CATALOGUE_CARD_BOX_SHADOW') ?: '0 2px 4px rgba(0,0,0,0.1)';

        // Inject JS to replace/hide existing flags and insert our custom flag into the .product-flags list
        $js = '<script>(function(){'
            . 'var id=' . (int)$id_product . ';'
            . 'var label=' . json_encode($tag_label) . ';'
            . 'var bg=' . json_encode($tag_bg) . ';'
            . 'var color=' . json_encode($tag_color) . ';'
            . 'var icon=' . json_encode($tag_icon) . ';'
            . 'var message=' . json_encode($message) . ';'
            . 'var cardBg=' . json_encode($card_bg) . ';'
            . 'var cardTextColor=' . json_encode($card_text_color) . ';'
            . 'var cardBorderColor=' . json_encode($card_border_color) . ';'
            . 'var cardBorderRadius=' . json_encode($card_border_radius) . ';'
            . 'var cardPadding=' . json_encode($card_padding) . ';'
            . 'var cardFontSize=' . json_encode($card_font_size) . ';'
            . 'var cardBoxShadow=' . json_encode($card_box_shadow) . ';'
            . 'function findContainer(){'
            . '  var sel = "[data-id-product=\\\""+id+"\\\"], [data-id_product=\\\""+id+"\\\"], .product-miniature[data-id-product=\\\""+id+"\\\"], .product-miniature[data-id_product=\\\""+id+"\\\"], .product[data-product-id=\\\""+id+"\\\"], .product[data-id-product=\\\""+id+"\\\"]";'
            . '  var el = document.querySelector(sel); if(el) return el; return null;'
            . '}'
            . 'function inject(){'
            . '  var containerEl = findContainer(); if(!containerEl) return; var containers = containerEl.querySelectorAll(".product-flags"); if(!containers.length) containers=[containerEl]; containers.forEach(function(container){ try{ var children = container.querySelectorAll("li"); children.forEach(function(ch){ if(!ch.classList.contains("mb-cat-custom")) ch.style.display="none"; }); var existing = container.querySelector(".mb-cat-custom"); if(existing) return; var li=document.createElement("li"); li.className="product-flag mb-cat-custom"; li.style.display="inline-block";li.style.marginRight="5px";li.style.padding="4px 8px"; if(bg) li.style.background=bg; if(color) li.style.color=color; li.style.fontSize="12px"; li.innerHTML = (icon? ("<i class=\\\""+icon+"\\\" style=\\\"margin-right:4px\\\"></i>") : "") + (label||"Catalogue"); container.insertBefore(li, container.firstChild); }catch(e){} });'
            . '}'
            . 'function handleQuickView(){'
            . '  if(!message) return;'
            . '  var quickview = document.querySelector(".quickview.modal, #quickview, .modal.show, .modal-dialog"); if(!quickview) return;'
            . '  var productInModal = quickview.querySelector("[data-id-product=\\\""+id+"\\\"], [data-id_product=\\\""+id+"\\\"]"); if(!productInModal) productInModal = quickview;'
            . '  if(quickview.getAttribute("data-mb-processed-"+id)) return; quickview.setAttribute("data-mb-processed-"+id, "1");'
            . '  var atcSelectors = [".product-add-to-cart", ".add-to-cart", "#add_to_cart", "button.add-to-cart", ".add", "form[data-button-action]", ".product-actions .add-to-cart", ".js-product-add-to-cart"];'
            . '  var notifSelectors = [".product-additional-info", ".availability-form", "[data-module]", ".out-of-stock-text", ".product-quantities"];'
            . '  atcSelectors.forEach(function(sel){ var els = quickview.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); });'
            . '  notifSelectors.forEach(function(sel){ var els = quickview.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); });'
            . '  if(quickview.querySelector(".mb-cat-message")) return;'
            . '  var insertPoint = quickview.querySelector(".product-information, .product-prices, .modal-body, .product-add-to-cart") || quickview;'
            . '  var msg = document.createElement("div"); msg.className = "mb-cat-message"; msg.style.marginTop = "20px"; msg.style.marginBottom = "15px"; msg.style.padding = cardPadding+"px"; msg.style.fontSize = cardFontSize+"px"; msg.style.color = cardTextColor; msg.style.lineHeight = "1.6"; msg.style.backgroundColor = cardBg; msg.style.border = "1px solid "+cardBorderColor; msg.style.borderRadius = cardBorderRadius+"px"; msg.style.boxShadow = cardBoxShadow; msg.innerHTML = message;'
            . '  if(insertPoint === quickview) { quickview.appendChild(msg); } else { insertPoint.parentElement.insertBefore(msg, insertPoint.nextSibling); }'
            . '}'
            . 'function hideInside(){ try{ var container = findContainer(); if(!container) return; var atcSel=[".product-add-to-cart", ".add-to-cart", "#add_to_cart", "button.add-to-cart", ".js-buy-btn", ".buy-button", ".product-page .add-to-cart", ".product-miniature .add-to-cart"]; atcSel.forEach(function(sel){ var els=container.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); var priceSel=[".product-price", ".product-prices", ".current-price", ".regular-price", ".price", ".product-price-and-shipping", ".product-miniature .price", ".product .price"]; priceSel.forEach(function(sel){ var els=container.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); }catch(e){} }'
            . 'var observer = new MutationObserver(function(mutations){ mutations.forEach(function(mutation){ if(mutation.addedNodes.length){ handleQuickView(); } }); }); observer.observe(document.body, {childList: true, subtree: true});'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){inject();hideInside();handleQuickView();});}else{inject();hideInside();handleQuickView();}'
            . 'setTimeout(function(){inject();hideInside();handleQuickView();},100); setTimeout(function(){handleQuickView();},500); setTimeout(function(){handleQuickView();},1000);'
            . '})();</script>';

        return $js;
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

        // Return custom flag HTML + per-product JS to hide other flags/prices inside this product container
        $output = '<li class="product-flag mb-cat-custom" data-mb-product-id="' . (int)$id_product . '" style="' . $style . '">';
        $output .= $icon_html . htmlspecialchars($tag_label ?: 'Catalogue');
        $output .= '</li>';

        // Add small script to scope hiding to this product container only
        $output .= '<script>(function(){var id=' . (int)$id_product . '; try{ var lis=document.querySelectorAll(".mb-cat-custom[data-mb-product-id=\""+id+"\"]"); lis.forEach(function(li){ try{ var container = li.closest(".product-miniature, .product, .product-container, .product-view"); if(!container) return; // hide other flags
            var others = container.querySelectorAll(".product-flags li:not(.mb-cat-custom)"); others.forEach(function(o){ o.style.display="none"; }); // hide prices inside container
            var priceSelectors=[".product-price", ".product-prices", ".current-price", ".regular-price", ".price", ".product-price-and-shipping", ".product-miniature .price", ".product .price"]; priceSelectors.forEach(function(sel){ var els=container.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); }catch(e){} }); }catch(e){} })();</script>';

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

        // Prepare multilingual message for current language if enabled
        $enabled = (int) Configuration::get('MB_CATALOGUE_MESSAGE_ENABLED');
        $storedMessages = json_decode(Configuration::get('MB_CATALOGUE_MESSAGE'), true);
        $message = '';
        if ($enabled) {
            $currentLang = isset($this->context->language->id) ? $this->context->language->id : null;
            if ($currentLang && is_array($storedMessages) && isset($storedMessages[$currentLang])) {
                // Decode HTML entities to render HTML properly in front-end
                $message = html_entity_decode($storedMessages[$currentLang], ENT_QUOTES, 'UTF-8');
            }
        }

        // Get card design settings
        $card_bg = Configuration::get('MB_CATALOGUE_CARD_BG') ?: '#f8f9fa';
        $card_text_color = Configuration::get('MB_CATALOGUE_CARD_TEXT_COLOR') ?: '#333333';
        $card_border_color = Configuration::get('MB_CATALOGUE_CARD_BORDER_COLOR') ?: '#dee2e6';
        $card_border_radius = Configuration::get('MB_CATALOGUE_CARD_BORDER_RADIUS') ?: '8';
        $card_padding = Configuration::get('MB_CATALOGUE_CARD_PADDING') ?: '20';
        $card_font_size = Configuration::get('MB_CATALOGUE_CARD_FONT_SIZE') ?: '14';
        $card_box_shadow = Configuration::get('MB_CATALOGUE_CARD_BOX_SHADOW') ?: '0 2px 4px rgba(0,0,0,0.1)';

        // Inject JS to manipulate flags on product page and show message near add-to-cart (scoped per product)
        $output = '';
        $output .= '<script>(function(){';
        $output .= 'var label=' . json_encode($tag_label) . ';';
        $output .= 'var bg=' . json_encode($tag_bg) . ';';
        $output .= 'var color=' . json_encode($tag_color) . ';';
        $output .= 'var icon=' . json_encode($tag_icon) . ';';
        $output .= 'var message=' . json_encode($message) . ';';
        $output .= 'var id=' . (int)$id_product . ';';
        $output .= 'var cardBg=' . json_encode($card_bg) . ';';
        $output .= 'var cardTextColor=' . json_encode($card_text_color) . ';';
        $output .= 'var cardBorderColor=' . json_encode($card_border_color) . ';';
        $output .= 'var cardBorderRadius=' . json_encode($card_border_radius) . ';';
        $output .= 'var cardPadding=' . json_encode($card_padding) . ';';
        $output .= 'var cardFontSize=' . json_encode($card_font_size) . ';';
        $output .= 'var cardBoxShadow=' . json_encode($card_box_shadow) . ';';
        $output .= 'function injectFlag(){';
        $output .= '  var container = document.querySelector(".product-flags");';
        $output .= '  if(container){ try{ var children = container.querySelectorAll("li"); children.forEach(function(ch){ if(!ch.classList.contains("mb-cat-custom")) ch.style.display="none"; }); var existing = container.querySelector(".mb-cat-custom"); if(existing) { existing.style.display="inline-block"; return; } var li=document.createElement("li"); li.className="product-flag mb-cat-custom"; li.setAttribute("data-mb-product-id", id); li.style.display="inline-block";li.style.marginRight="5px";li.style.padding="4px 8px"; if(bg) li.style.background=bg; if(color) li.style.color=color; li.style.fontSize="12px"; li.innerHTML = (icon? ("<i class=\""+icon+"\" style=\"margin-right:4px\"></i>") : "") + (label||"Catalogue"); container.insertBefore(li, container.firstChild); }catch(e){} }';
        $output .= '}';
        $output .= 'function injectMessage(){';
        $output .= '  if(!message) return;';
        $output .= '  try{ var container = document.querySelector(".product-add-to-cart, #add_to_cart, .product-actions"); if(!container) return; var parent = container.parentElement || document.body; if(!parent.querySelector(".mb-cat-message")){ var msg = document.createElement("div"); msg.className="mb-cat-message"; msg.style.marginTop="20px"; msg.style.marginBottom="15px"; msg.style.padding=cardPadding+"px"; msg.style.fontSize=cardFontSize+"px"; msg.style.lineHeight="1.6"; msg.style.color=cardTextColor; msg.style.backgroundColor=cardBg; msg.style.border="1px solid "+cardBorderColor; msg.style.borderRadius=cardBorderRadius+"px"; msg.style.boxShadow=cardBoxShadow; msg.innerHTML = message; parent.insertBefore(msg, container.nextSibling); } }catch(e){}';
        $output .= '}';
        // hide prices and add-to-cart inside the product container only
        $output .= 'function hideInside(){ try{ var atcSel=[".product-add-to-cart", ".add-to-cart", "#add_to_cart", "button.add-to-cart", ".js-buy-btn", ".buy-button", ".product-actions .add-to-cart", ".js-product-add-to-cart"]; atcSel.forEach(function(sel){ var els=document.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); var notifSel=[".product-additional-info", ".availability-form", ".out-of-stock-text", ".product-quantities"]; notifSel.forEach(function(sel){ var els=document.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); var priceSel=[".product-price", ".product-prices", ".current-price", ".regular-price", ".price", ".product-price-and-shipping"]; priceSel.forEach(function(sel){ var els=document.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); }catch(e){} }';
        $output .= 'function handleQuickView(){ if(!message) return; var quickview = document.querySelector(".quickview.modal, #quickview, .modal.show, .modal-dialog"); if(!quickview) return; if(quickview.getAttribute("data-mb-processed-"+id)) return; quickview.setAttribute("data-mb-processed-"+id, "1"); var atcSelectors = [".product-add-to-cart", ".add-to-cart", "#add_to_cart", "button.add-to-cart", ".add", "form[data-button-action]", ".product-actions .add-to-cart", ".js-product-add-to-cart"]; var notifSelectors = [".product-additional-info", ".availability-form", "[data-module]", ".out-of-stock-text", ".product-quantities"]; atcSelectors.forEach(function(sel){ var els = quickview.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); notifSelectors.forEach(function(sel){ var els = quickview.querySelectorAll(sel); els.forEach(function(e){ try{ e.style.display="none"; }catch(ex){} }); }); if(quickview.querySelector(".mb-cat-message")) return; var insertPoint = quickview.querySelector(".product-information, .product-prices, .modal-body, .product-add-to-cart") || quickview; var msg = document.createElement("div"); msg.className = "mb-cat-message"; msg.style.marginTop = "20px"; msg.style.marginBottom = "15px"; msg.style.padding = cardPadding+"px"; msg.style.fontSize = cardFontSize+"px"; msg.style.color = cardTextColor; msg.style.lineHeight = "1.6"; msg.style.backgroundColor = cardBg; msg.style.border = "1px solid "+cardBorderColor; msg.style.borderRadius = cardBorderRadius+"px"; msg.style.boxShadow = cardBoxShadow; msg.innerHTML = message; if(insertPoint === quickview) { quickview.appendChild(msg); } else { insertPoint.parentElement.insertBefore(msg, insertPoint.nextSibling); } }';
        $output .= 'var observer = new MutationObserver(function(mutations){ mutations.forEach(function(mutation){ if(mutation.addedNodes.length){ handleQuickView(); } }); }); observer.observe(document.body, {childList: true, subtree: true});';
        $output .= 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){injectFlag();injectMessage();hideInside();handleQuickView();});}else{injectFlag();injectMessage();hideInside();handleQuickView();}';
        $output .= 'setTimeout(function(){injectFlag();injectMessage();hideInside();handleQuickView();},100); setTimeout(function(){injectMessage();hideInside();handleQuickView();},500); setTimeout(function(){handleQuickView();},1000);';
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
