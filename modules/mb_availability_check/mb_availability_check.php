<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class mb_availability_check extends Module
{
    public function __construct()
    {
        $this->name = 'mb_availability_check';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mobytic';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Availability check button');
        $this->description = $this->l('Replace Add to cart by "Vérifier la disponibilité" for selected products with a contact form.');
    }

    public function install()
    {
        if (
            !parent::install()
            // Only set defaults if not already configured (preserves data on reinstall)
            || (!Configuration::get('MB_AVAILABILITY_EMAIL') && !Configuration::updateValue('MB_AVAILABILITY_EMAIL', ''))
            || (!Configuration::get('MB_AVAILABILITY_SEND_EMAIL') && !Configuration::updateValue('MB_AVAILABILITY_SEND_EMAIL', 0))
            || (!Configuration::get('MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL') && !Configuration::updateValue('MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL', 0))
            || (!Configuration::get('MB_AVAILABILITY_PRODUCTS') && !Configuration::updateValue('MB_AVAILABILITY_PRODUCTS', ''))
            || (!Configuration::get('MB_AVAILABILITY_CATEGORIES') && !Configuration::updateValue('MB_AVAILABILITY_CATEGORIES', ''))
            || (!Configuration::get('MB_AVAILABILITY_FLAG_LABEL') && !Configuration::updateValue('MB_AVAILABILITY_FLAG_LABEL', 'Sur demande'))
            || (!Configuration::get('MB_AVAILABILITY_FLAG_BG_COLOR') && !Configuration::updateValue('MB_AVAILABILITY_FLAG_BG_COLOR', '#25b9d7'))
            || (!Configuration::get('MB_AVAILABILITY_FLAG_TEXT_COLOR') && !Configuration::updateValue('MB_AVAILABILITY_FLAG_TEXT_COLOR', '#ffffff'))
            || (!Configuration::get('MB_AVAILABILITY_BUTTON_LABEL') && !Configuration::updateValue('MB_AVAILABILITY_BUTTON_LABEL', 'Vérifier la disponibilité'))
            || (!Configuration::get('MB_AVAILABILITY_BUTTON_BG_COLOR') && !Configuration::updateValue('MB_AVAILABILITY_BUTTON_BG_COLOR', '#25b9d7'))
            || (!Configuration::get('MB_AVAILABILITY_BUTTON_TEXT_COLOR') && !Configuration::updateValue('MB_AVAILABILITY_BUTTON_TEXT_COLOR', '#ffffff'))
            || !$this->installDb()
            // FRONT HOOKS
            || !$this->registerHook('displayProductAdditionalInfo')
            || !$this->registerHook('displayFooterProduct')
            || !$this->registerHook('displayProductListReviews')
            || !$this->registerHook('actionProductFlagsModifier')
            || !$this->registerHook('displayHeader')
            // BACK OFFICE PRODUCT FORM (Symfony)
            || !$this->registerHook('actionProductFormBuilderModifier')
            || !$this->registerHook('actionAfterUpdateProductFormHandler')
            || !$this->registerHook('actionAfterCreateProductFormHandler')
        ) {
            return false;
        }

        // create Back Office tab
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminMbAvailabilityRequests';
        $tab->module = $this->name;
        $languages = Language::getLanguages();
        foreach ($languages as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('Availability requests');
        }
        // try to place under Orders, fallback to Catalog
        $parentId = (int) Tab::getIdFromClassName('AdminParentOrders');
        if (!$parentId) {
            $parentId = (int) Tab::getIdFromClassName('AdminCatalog');
        }
        if ($parentId) {
            $tab->id_parent = $parentId;
        }

        if (!$tab->add()) {
            // if tab creation fails, continue but log (not fatal)
        }

        return true;
    }

    public function uninstall()
    {
        $deleteData = (int) Configuration::get('MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL', 0);

        if ($deleteData) {
            // Delete all data when uninstalling
            $result = parent::uninstall()
                && $this->uninstallDb()
                && Configuration::deleteByName('MB_AVAILABILITY_EMAIL')
                && Configuration::deleteByName('MB_AVAILABILITY_SEND_EMAIL')
                && Configuration::deleteByName('MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL')
                && Configuration::deleteByName('MB_AVAILABILITY_PRODUCTS')
                && Configuration::deleteByName('MB_AVAILABILITY_CATEGORIES')
                && Configuration::deleteByName('MB_AVAILABILITY_FLAG_LABEL')
                && Configuration::deleteByName('MB_AVAILABILITY_FLAG_BG_COLOR')
                && Configuration::deleteByName('MB_AVAILABILITY_FLAG_TEXT_COLOR')
                && Configuration::deleteByName('MB_AVAILABILITY_BUTTON_LABEL')
                && Configuration::deleteByName('MB_AVAILABILITY_BUTTON_BG_COLOR')
                && Configuration::deleteByName('MB_AVAILABILITY_BUTTON_TEXT_COLOR');
        } else {
            // Preserve data for future reinstallation
            $result = parent::uninstall();
        }

        // Always remove Back Office tab if exists
        $idTab = (int) Tab::getIdFromClassName('AdminMbAvailabilityRequests');
        if ($idTab) {
            $tab = new Tab($idTab);
            $tab->delete();
        }

        return $result;
    }

    protected function installDb()
    {
        $sql1 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mb_availability_settings` (
            `id_product` INT(10) UNSIGNED NOT NULL,
            `enabled` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_product`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $sql2 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mb_availability_requests` (
            `id_request` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_product` INT(10) UNSIGNED NOT NULL,
            `qty` INT(10) UNSIGNED NOT NULL DEFAULT 1,
            `status` VARCHAR(32) NOT NULL DEFAULT "Nouveau",
            `firstname` VARCHAR(255) NOT NULL,
            `lastname` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(64) DEFAULT NULL,
            `token` VARCHAR(64) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_request`),
            KEY `token` (`token`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $sql3 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mb_availability_notifications` (
            `id_notification` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_request` INT(11) UNSIGNED NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `template` VARCHAR(255) NOT NULL,
            `template_vars` TEXT,
            `processed` TINYINT(1) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_notification`),
            KEY `id_request` (`id_request`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql1)
            && Db::getInstance()->execute($sql2)
            && Db::getInstance()->execute($sql3);
    }

    protected function uninstallDb()
    {
        // If you want to keep data, comment this out
        $sql1 = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mb_availability_settings`';
        $sql2 = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mb_availability_requests`';

        $sql3 = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mb_availability_notifications`';

        return Db::getInstance()->execute($sql1)
            && Db::getInstance()->execute($sql2)
            && Db::getInstance()->execute($sql3);
    }

    /**
     * Add JS/CSS
     */
    public function hookDisplayHeader($params)
    {
        $phpSelf = isset($this->context->controller->php_self) ? $this->context->controller->php_self : '';
        
        // Load availability.js on all pages to support quick view
        $this->context->controller->registerJavascript(
            'module-mbavailabilitycheck',
            'modules/' . $this->name . '/views/js/availability.js',
            ['position' => 'bottom', 'priority' => 150]
        );
        
        if ($phpSelf === 'cart') {
            $this->context->controller->registerJavascript(
                'module-mbavailabilitycheck-cart',
                'modules/' . $this->name . '/views/js/cart_lock.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        }

        // Add CSS for miniature flag
        $this->context->controller->registerStylesheet(
            'module-mbavailabilitycheck-miniature',
            'modules/' . $this->name . '/views/css/miniature.css',
            ['media' => 'all', 'priority' => 150]
        );

        // Add inline CSS for custom colors
        $bgColor = Configuration::get('MB_AVAILABILITY_FLAG_BG_COLOR', '#25b9d7');
        $textColor = Configuration::get('MB_AVAILABILITY_FLAG_TEXT_COLOR', '#ffffff');
        $buttonBgColor = Configuration::get('MB_AVAILABILITY_BUTTON_BG_COLOR', '#25b9d7');
        $buttonTextColor = Configuration::get('MB_AVAILABILITY_BUTTON_TEXT_COLOR', '#ffffff');

        $customCss = '.product-flags .product-flag.mb-availability-check { background: ' . $bgColor . ' !important; color: ' . $textColor . ' !important; }';
        $customCss .= ' .mb-availability-miniature-flag { background: ' . $bgColor . ' !important; color: ' . $textColor . ' !important; }';
        $customCss .= ' .mb-availability-btn { background: ' . $buttonBgColor . ' !important; color: ' . $buttonTextColor . ' !important; border-color: ' . $buttonBgColor . ' !important; }';

        $this->context->controller->registerStylesheet(
            'module-mbavailabilitycheck-custom',
            'data:text/css;base64,' . base64_encode($customCss),
            ['media' => 'all', 'priority' => 151, 'inline' => true]
        );
    }

    /**
     * Add our button near Add to cart
     */
    public function hookDisplayProductAdditionalInfo($params)
    {
        if (!isset($params['product']) || !$params['product']['id_product']) {
            return;
        }

        $idProduct = (int) $params['product']['id_product'];

        if (!$this->isEnabledForProduct($idProduct)) {
            return;
        }

        $this->context->smarty->assign([
            'availability_product' => $params['product'],
            'mb_availability_button_label' => Configuration::get('MB_AVAILABILITY_BUTTON_LABEL', $this->l('Vérifier la disponibilité')),
            'mb_availability_button_bg_color' => Configuration::get('MB_AVAILABILITY_BUTTON_BG_COLOR', '#25b9d7'),
            'mb_availability_button_text_color' => Configuration::get('MB_AVAILABILITY_BUTTON_TEXT_COLOR', '#ffffff'),
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/hook/product_button.tpl');
    }

    /**
     * Add modal HTML in product page footer
     */
    public function hookDisplayFooterProduct($params)
    {
        if (!isset($params['product']) || !$params['product']['id_product']) {
            return;
        }

        $idProduct = (int) $params['product']['id_product'];

        if (!$this->isEnabledForProduct($idProduct)) {
            return;
        }

        $product = $params['product'];

        $this->context->smarty->assign([
            'availability_product_name' => $product['name'],
            'availability_product_id'   => $idProduct,
            'availability_message'      => $this->l('The owner will contact you shortly.'),
            'availability_send_url'     => $this->context->link->getModuleLink(
                $this->name,
                'send',
                [],
                true
            ),
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/front/modal.tpl');
    }

    /**
     * Add flag on product miniature (product listing pages)
     * Note: This hook is kept for compatibility but the flag is now added via actionProductFlagsModifier
     */
    public function hookDisplayProductListReviews($params)
    {
        // Flag is now handled by actionProductFlagsModifier hook
        return '';
    }

    /**
     * Add flag to product flags array
     */
    public function hookActionProductFlagsModifier($params)
    {
        if (!isset($params['product']) || !isset($params['product']['id_product'])) {
            return;
        }

        $idProduct = (int) $params['product']['id_product'];

        if (!$this->isEnabledForProduct($idProduct)) {
            return;
        }

        // Add custom flag to the flags array
        $flagLabel = Configuration::get('MB_AVAILABILITY_FLAG_LABEL');
        if (empty($flagLabel)) {
            $flagLabel = $this->l('Sur demande');
        }

        $params['flags']['mb-availability-check'] = [
            'type' => 'mb-availability-check',
            'label' => $flagLabel,
        ];

        // If our module flag is present, remove any flag whose type is 'out_of_stock'
        // (flags array may be numerically indexed, so unset by inspecting each item's 'type').
        if (!empty($params['flags']) && is_array($params['flags'])) {
            foreach ($params['flags'] as $key => $flagItem) {
                if (is_array($flagItem) && isset($flagItem['type']) && $flagItem['type'] === 'out_of_stock') {
                    unset($params['flags'][$key]);
                }
            }
        }
    }

    /**
     * Check if enabled for product
     */
    protected function isEnabledForProduct($idProduct)
    {
        $idProduct = (int) $idProduct;

        // Priority 1: Check if product belongs to any enabled category
        $globalCategories = Configuration::get('MB_AVAILABILITY_CATEGORIES');
        if (!empty($globalCategories)) {
            $categoryIds = array_filter(array_map('intval', explode(',', $globalCategories)));
            if (!empty($categoryIds)) {
                $productCategories = Product::getProductCategories($idProduct);
                if (!empty($productCategories)) {
                    foreach ($categoryIds as $catId) {
                        if (in_array($catId, $productCategories)) {
                            return true;
                        }
                    }
                }
            }
        }

        // Priority 2: Check global product list
        $globalProducts = Configuration::get('MB_AVAILABILITY_PRODUCTS');
        if (!empty($globalProducts)) {
            $productIds = array_filter(array_map('intval', explode(',', $globalProducts)));
            if (in_array($idProduct, $productIds)) {
                return true;
            }
        }

        // Priority 3: Check per-product setting (explicit enable/disable)
        $sql = 'SELECT `enabled` FROM `' . _DB_PREFIX_ . 'mb_availability_settings` WHERE `id_product`=' . $idProduct;

        return (bool) Db::getInstance()->getValue($sql);
    }

    protected function saveProductSetting($idProduct, $enabled)
    {
        $idProduct = (int) $idProduct;
        $enabled   = (int) (bool) $enabled;

        $exists = Db::getInstance()->getValue('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'mb_availability_settings`
            WHERE `id_product`=' . $idProduct);

        if ($exists) {
            $sql = 'UPDATE `' . _DB_PREFIX_ . 'mb_availability_settings`
                    SET `enabled`=' . $enabled . '
                    WHERE `id_product`=' . $idProduct;
        } else {
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'mb_availability_settings` (`id_product`,`enabled`)
                    VALUES (' . $idProduct . ', ' . $enabled . ')';
        }

        return Db::getInstance()->execute($sql);
    }

    /**
     * Modify Symfony product form in BO
     */
    public function hookActionProductFormBuilderModifier($params)
    {
        /** @var Symfony\Component\Form\FormBuilderInterface $formBuilder */
        $formBuilder = $params['form_builder'];
        $idProduct   = (int) $params['id'];

        $enabled = $this->isEnabledForProduct($idProduct);

        $formBuilder->add('mb_availability_enabled', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
            'label'    => $this->l('Enable « Vérifier la disponibilité » button'),
            'required' => false,
            'data'     => $enabled,
        ]);

        // Inform Presta that your extra field is part of the form data
        $params['data']['mb_availability_enabled'] = $enabled;
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        // Handle AJAX requests
        if (Tools::getValue('ajax') && Tools::getValue('action')) {
            $action = Tools::getValue('action');
            switch ($action) {
                case 'searchProducts':
                    $this->ajaxProcessSearchProducts();
                    break;
                case 'getProductNames':
                    $this->ajaxProcessGetProductNames();
                    break;
                case 'getAllEnabledProducts':
                    $this->ajaxProcessGetAllEnabledProducts();
                    break;
                case 'disableProduct':
                    $this->ajaxProcessDisableProduct();
                    break;
                case 'searchCategories':
                    $this->ajaxProcessSearchCategories();
                    break;
                case 'getAllEnabledCategories':
                    $this->ajaxProcessGetAllEnabledCategories();
                    break;
                case 'disableCategory':
                    $this->ajaxProcessDisableCategory();
                    break;
            }
            return;
        }

        $output = '';

        // Add custom CSS
        $output .= $this->renderCustomCSS();

        // Handle test email
        if (Tools::isSubmit('submitTestEmail')) {
            $output .= $this->sendTestEmail();
        }

        // Handle preview email
        if (Tools::isSubmit('submitPreviewEmail')) {
            $output .= $this->previewEmail();
        }

        if (Tools::isSubmit('submitMbAvailability')) {
            $email = trim(Tools::getValue('MB_AVAILABILITY_EMAIL'));
            $sendEmail = (int) Tools::getValue('MB_AVAILABILITY_SEND_EMAIL');
            $deleteDataOnUninstall = (int) Tools::getValue('MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL');
            $products = trim(Tools::getValue('MB_AVAILABILITY_PRODUCTS'));
            $categories = trim(Tools::getValue('MB_AVAILABILITY_CATEGORIES'));
            $flagLabel = trim(Tools::getValue('MB_AVAILABILITY_FLAG_LABEL'));
            $bgColor = trim(Tools::getValue('MB_AVAILABILITY_FLAG_BG_COLOR'));
            $textColor = trim(Tools::getValue('MB_AVAILABILITY_FLAG_TEXT_COLOR'));
            $buttonLabel = trim(Tools::getValue('MB_AVAILABILITY_BUTTON_LABEL'));
            $buttonBgColor = trim(Tools::getValue('MB_AVAILABILITY_BUTTON_BG_COLOR'));
            $buttonTextColor = trim(Tools::getValue('MB_AVAILABILITY_BUTTON_TEXT_COLOR'));

            if ($email !== '' && !Validate::isEmail($email)) {
                $output .= $this->displayError($this->l('Invalid email address'));
            } else {
                Configuration::updateValue('MB_AVAILABILITY_EMAIL', $email);
                Configuration::updateValue('MB_AVAILABILITY_SEND_EMAIL', $sendEmail);
                Configuration::updateValue('MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL', $deleteDataOnUninstall);
                Configuration::updateValue('MB_AVAILABILITY_PRODUCTS', $products);
                Configuration::updateValue('MB_AVAILABILITY_CATEGORIES', $categories);
                Configuration::updateValue('MB_AVAILABILITY_FLAG_LABEL', $flagLabel);
                Configuration::updateValue('MB_AVAILABILITY_FLAG_BG_COLOR', $bgColor);
                Configuration::updateValue('MB_AVAILABILITY_FLAG_TEXT_COLOR', $textColor);
                Configuration::updateValue('MB_AVAILABILITY_BUTTON_LABEL', $buttonLabel);
                Configuration::updateValue('MB_AVAILABILITY_BUTTON_BG_COLOR', $buttonBgColor);
                Configuration::updateValue('MB_AVAILABILITY_BUTTON_TEXT_COLOR', $buttonTextColor);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        // Render statistics dashboard first
        $output .= $this->renderStatistics();

        return $output . $this->renderForm();
    }

    /**
     * Add custom CSS for better design
     */
    protected function renderCustomCSS()
    {
        return '<style>
            .mb-availability-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .mb-stat-card {
                background: #fff;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .mb-stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .mb-stat-card .icon {
                font-size: 32px;
                margin-bottom: 10px;
                display: inline-block;
            }
            .mb-stat-card .value {
                font-size: 36px;
                font-weight: bold;
                color: #25b9d7;
                margin: 10px 0;
            }
            .mb-stat-card .label {
                font-size: 14px;
                color: #6c868e;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .mb-email-test-section {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .mb-email-test-section .btn {
                margin-right: 10px;
                margin-bottom: 10px;
            }
            .mb-product-list {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                background: #f9f9f9;
            }
            .mb-help-box {
                background: #e7f3ff;
                border-left: 4px solid #25b9d7;
                padding: 15px;
                margin: 15px 0;
                border-radius: 4px;
            }
            .mb-help-box .icon {
                color: #25b9d7;
                margin-right: 8px;
            }
            .mb-section-header {
                border-bottom: 3px solid #25b9d7;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .mb-section-header h3 {
                color: #363a41;
                font-size: 18px;
                font-weight: 600;
            }
            .mb-quick-actions {
                background: #fff;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .mb-quick-actions .btn {
                margin: 5px;
            }
            .panel-heading .badge {
                margin-left: 10px;
            }
            /* Product miniature flag styling */
            .product-miniature {
                position: relative;
            }
            .mb-availability-miniature-flag {
                position: absolute;
                top: 10px;
                right: 10px;
                background: #25b9d7;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: bold;
                z-index: 10;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .mb-availability-miniature-flag .icon {
                margin-right: 4px;
            }
            /* Remove Bootstrap grid classes for products intro */
            #mb-admin-container .col-lg-8,
            #mb-admin-container .col-lg-offset-3 {
                width: 100% !important;
                margin-left: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            #mb-admin-container .control-label {
                text-align: left !important;
            }
            #mb-admin-container .switch label {
                min-width: 200px;
            }
        </style>';
    }

    /**
     * Render statistics dashboard
     */
    protected function renderStatistics()
    {
        $stats = $this->getStatistics();

        $output = '<div class="mb-availability-stats">';

        // Total Requests
        $output .= '<div class="mb-stat-card">';
        $output .= '<i class="icon icon-envelope icon"></i>';
        $output .= '<div class="value">' . (int)$stats['total_requests'] . '</div>';
        $output .= '<div class="label">' . $this->l('Total Requests') . '</div>';
        $output .= '</div>';

        // Enabled Products
        $output .= '<div class="mb-stat-card">';
        $output .= '<i class="icon icon-shopping-cart icon"></i>';
        $output .= '<div class="value">' . (int)$stats['enabled_products'] . '</div>';
        $output .= '<div class="label">' . $this->l('Enabled Products') . '</div>';
        $output .= '</div>';

        // Pending Requests
        $output .= '<div class="mb-stat-card">';
        $output .= '<i class="icon icon-clock-o icon"></i>';
        $output .= '<div class="value">' . (int)$stats['pending_requests'] . '</div>';
        $output .= '<div class="label">' . $this->l('Pending Requests') . '</div>';
        $output .= '</div>';

        // Requests Today
        $output .= '<div class="mb-stat-card">';
        $output .= '<i class="icon icon-calendar icon"></i>';
        $output .= '<div class="value">' . (int)$stats['today_requests'] . '</div>';
        $output .= '<div class="label">' . $this->l('Requests Today') . '</div>';
        $output .= '</div>';

        // Enabled Categories
        $output .= '<div class="mb-stat-card">';
        $output .= '<i class="icon icon-folder icon"></i>';
        $output .= '<div class="value">' . (int)$stats['enabled_categories'] . '</div>';
        $output .= '<div class="label">' . $this->l('Enabled Categories') . '</div>';
        $output .= '</div>';

        $output .= '</div>';

        // Quick Actions
        $output .= '<div class="mb-quick-actions">';
        $output .= '<div class="mb-section-header"><h3 style="padding:8px"><i class="icon icon-bolt"></i> ' . $this->l('Quick Actions') . '</h3></div>';
        $output .= '<a href="' . $this->context->link->getAdminLink('AdminMbAvailabilityRequests') . '" class="btn btn-primary">';
        $output .= '<i class="icon icon-list"></i> ' . $this->l('View All Requests');
        $output .= '</a>';
        $output .= '<a href="' . $this->context->link->getAdminLink('AdminProducts') . '" class="btn btn-default">';
        $output .= '<i class="icon icon-cog"></i> ' . $this->l('Manage Products');
        $output .= '</a>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Get statistics data
     */
    protected function getStatistics()
    {
        $stats = [];

        // Total requests
        $stats['total_requests'] = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mb_availability_requests`'
        );

        // Enabled products count
        $globalProducts = Configuration::get('MB_AVAILABILITY_PRODUCTS');
        $globalCount = 0;
        if (!empty($globalProducts)) {
            $globalCount = count(array_filter(explode(',', $globalProducts)));
        }
        $perProductCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mb_availability_settings` WHERE `enabled` = 1'
        );
        $stats['enabled_products'] = $globalCount + $perProductCount;
        
        // Enabled categories count
        $globalCategories = Configuration::get('MB_AVAILABILITY_CATEGORIES');
        $categoryCount = 0;
        if (!empty($globalCategories)) {
            $categoryCount = count(array_filter(explode(',', $globalCategories)));
        }
        $stats['enabled_categories'] = $categoryCount;

        // Pending requests (status = 'Nouveau')
        $stats['pending_requests'] = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mb_availability_requests` WHERE `status` = "Nouveau"'
        );

        // Today's requests
        $stats['today_requests'] = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mb_availability_requests` ' .
                'WHERE DATE(`date_add`) = CURDATE()'
        );

        return $stats;
    }

    protected function renderEmailLogs()
    {
        $logs = $this->getEmailLogs();
        $output = '';

        if (!empty($logs)) {
            $output .= '<div class="panel">';
            $output .= '<div class="panel-heading">';
            $output .= '<i class="icon icon-list-alt"></i> ' . $this->l('Recent Email Activity');
            $output .= '<span class="badge badge-info" style="margin-left: 10px;">' . count($logs) . ' ' . $this->l('logs') . '</span>';
            $output .= '<button type="button" id="clear-email-logs" class="btn btn-danger btn-xs pull-right" style="margin-left: 10px;">';
            $output .= '<i class="icon icon-trash"></i> ' . $this->l('Clear Logs');
            $output .= '</button>';
            $output .= '</div>';
            $output .= '<div class="table-responsive">';
            $output .= '<table class="table table-hover">';
            $output .= '<thead style="background-color: #f8f9fa;">';
            $output .= '<tr>';
            $output .= '<th><i class="icon icon-calendar"></i> ' . $this->l('Date') . '</th>';
            $output .= '<th><i class="icon icon-tag"></i> ' . $this->l('Request') . '</th>';
            $output .= '<th><i class="icon icon-shopping-cart"></i> ' . $this->l('Product') . '</th>';
            $output .= '<th><i class="icon icon-user"></i> ' . $this->l('Recipient') . '</th>';
            $output .= '<th><i class="icon icon-check"></i> ' . $this->l('Status') . '</th>';
            $output .= '</tr>';
            $output .= '</thead>';
            $output .= '<tbody>';

            foreach ($logs as $log) {
                $statusClass = $log['status'] === 'success' ? 'label-success' : 'label-danger';
                $statusIcon = $log['status'] === 'success' ? 'icon-check-circle' : 'icon-times-circle';
                $statusText = $log['status'] === 'success' ? $this->l('Sent') : $this->l('Failed');

                $output .= '<tr>';
                $output .= '<td style="white-space: nowrap;">' . date('Y-m-d H:i', strtotime($log['date'])) . '</td>';
                $output .= '<td><strong>#' . ($log['request_id'] ?: '-') . '</strong></td>';
                $output .= '<td>' . ($log['product'] ?: '<em>' . $this->l('N/A') . '</em>') . '</td>';
                $output .= '<td><code>' . $log['recipient'] . '</code></td>';
                $output .= '<td><span class="label ' . $statusClass . '"><i class="icon ' . $statusIcon . '"></i> ' . $statusText . '</span></td>';
                $output .= '</tr>';
            }

            $output .= '</tbody>';
            $output .= '</table>';
            $output .= '</div>';
            $output .= '<div class="panel-footer">';
            $output .= '<small><i class="icon icon-info-circle"></i> ' . $this->l('Showing last 50 email logs') . '</small>';
            $output .= '<script type="text/javascript">
                $(document).ready(function() {
                    $("#clear-email-logs").on("click", function() {
                        if (confirm("' . $this->l('Are you sure you want to clear all email logs? This action cannot be undone.') . '")) {
                            var $btn = $(this);
                            var originalText = $btn.html();
                            
                            // Disable button and show loading
                            $btn.prop("disabled", true).html("<i class=\"icon icon-spinner icon-spin\"></i> ' . $this->l('Clearing...') . '");
                            
                            $.ajax({
                                url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                                type: "POST",
                                data: {
                                    ajax: 1,
                                    action: "clearEmailLogs",
                                    token: "' . Tools::getAdminTokenLite('AdminModules') . '",
                                    configure: "' . $this->name . '"
                                },
                                success: function(response) {
                                    try {
                                        var data = JSON.parse(response);
                                        if (data.success) {
                                            // Reload the page to refresh the logs display
                                            location.reload();
                                        } else {
                                            alert("' . $this->l('Error:') . ' " + (data.message || "' . $this->l('Unknown error occurred') . '"));
                                            $btn.prop("disabled", false).html(originalText);
                                        }
                                    } catch (e) {
                                        alert("' . $this->l('Error: Invalid response from server') . '");
                                        $btn.prop("disabled", false).html(originalText);
                                    }
                                },
                                error: function() {
                                    alert("' . $this->l('Error: Failed to clear logs') . '");
                                    $btn.prop("disabled", false).html(originalText);
                                }
                            });
                        }
                    });
                });
            </script>';
            $output .= '</div>';
            $output .= '</div>';
        } else {
            $output .= '<div class="panel">';
            $output .= '<div class="panel-heading"><i class="icon icon-list-alt"></i> ' . $this->l('Email Activity Logs') . '</div>';
            $output .= '<div class="alert alert-info" style="margin: 15px;">';
            $output .= '<i class="icon icon-info-circle"></i> ';
            $output .= $this->l('No email logs found yet. Logs will appear here when customers submit availability requests and emails are sent.');
            $output .= '</div>';
            $output .= '</div>';
        }

        return $output;
    }

    protected function getEmailLogs()
    {
        $logs = [];

        try {
            // Get logs from PrestaShop log table
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'log`
                    WHERE `message` LIKE "%mb_availability_check: Notification email%"
                    ORDER BY `date_add` DESC
                    LIMIT 50';

            $dbLogs = Db::getInstance()->executeS($sql);

            if ($dbLogs) {
                foreach ($dbLogs as $log) {
                    // Parse the log message to extract information
                    $message = $log['message'];

                    $logEntry = [
                        'date' => $log['date_add'],
                        'request_id' => '',
                        'product' => '',
                        'recipient' => '',
                        'status' => 'unknown',
                        'message' => $message
                    ];

                    // Extract request ID
                    if (preg_match('/request (\d+)/', $message, $matches)) {
                        $logEntry['request_id'] = $matches[1];
                    }

                    // Extract recipient email
                    if (preg_match('/to ([^\s]+@[^\s]+)/', $message, $matches)) {
                        $logEntry['recipient'] = $matches[1];
                    }

                    // Determine status
                    if (strpos($message, 'Notification email sent') !== false) {
                        $logEntry['status'] = 'success';
                    } elseif (strpos($message, 'Failed to send') !== false || strpos($message, 'Error sending') !== false) {
                        $logEntry['status'] = 'error';
                    }

                    // Try to get product info if we have request ID
                    if (!empty($logEntry['request_id'])) {
                        $requestSql = 'SELECT p.`name`, r.`id_product`
                                      FROM `' . _DB_PREFIX_ . 'mb_availability_requests` r
                                      LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` p
                                      ON r.`id_product` = p.`id_product`
                                      AND p.`id_lang` = ' . (int)Configuration::get('PS_LANG_DEFAULT') . '
                                      WHERE r.`id_request` = ' . (int)$logEntry['request_id'];

                        $requestData = Db::getInstance()->getRow($requestSql);
                        if ($requestData) {
                            $logEntry['product'] = $requestData['name'] . ' (ID: ' . $requestData['id_product'] . ')';
                        }
                    }

                    $logs[] = $logEntry;
                }
            }
        } catch (Exception $e) {
            // If there's an error reading logs, return empty array
            $logs = [];
        }

        return $logs;
    }

    protected function renderEmailConfigInfo()
    {
        $output = '<div class="panel">';
        $output .= '<div class="panel-heading"><i class="icon icon-cogs"></i> ' . $this->l('PrestaShop Email Configuration') . '</div>';
        $output .= '<div style="padding: 15px;">';

        $mailMethod = Configuration::get('PS_MAIL_METHOD');
        $mailServer = Configuration::get('PS_MAIL_SERVER');
        $mailUser = Configuration::get('PS_MAIL_USER');
        $shopEmail = Configuration::get('PS_SHOP_EMAIL');

        $methodName = '';
        $methodIcon = '';
        $methodStatus = 'info';

        switch ($mailMethod) {
            case 1:
                $methodName = $this->l('PHP mail() function');
                $methodIcon = 'icon-code';
                $methodStatus = 'warning';
                break;
            case 2:
                $methodName = $this->l('SMTP');
                $methodIcon = 'icon-server';
                $methodStatus = 'success';
                break;
            case 3:
                $methodName = $this->l('Sendmail');
                $methodIcon = 'icon-send';
                $methodStatus = 'info';
                break;
            default:
                $methodName = $this->l('Unknown');
                $methodIcon = 'icon-question';
                $methodStatus = 'danger';
        }

        $output .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">';

        // Email Method Card
        $output .= '<div style="background: #f8f9fa; border-radius: 6px; padding: 15px; border-left: 4px solid #25b9d7;">';
        $output .= '<div style="color: #6c868e; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">' . $this->l('Email Method') . '</div>';
        $output .= '<div style="font-size: 16px; font-weight: 600;"><i class="icon ' . $methodIcon . '"></i> ' . $methodName . '</div>';
        $output .= '</div>';

        // Shop Email Card
        $output .= '<div style="background: #f8f9fa; border-radius: 6px; padding: 15px; border-left: 4px solid #25b9d7;">';
        $output .= '<div style="color: #6c868e; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">' . $this->l('Shop Email') . '</div>';
        $output .= '<div style="font-size: 16px; font-weight: 600;"><i class="icon icon-envelope"></i> ' . $shopEmail . '</div>';
        $output .= '</div>';

        if ($mailMethod == 2) { // SMTP
            // SMTP Server Card
            $output .= '<div style="background: #f8f9fa; border-radius: 6px; padding: 15px; border-left: 4px solid #25b9d7;">';
            $output .= '<div style="color: #6c868e; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">' . $this->l('SMTP Server') . '</div>';
            $output .= '<div style="font-size: 16px; font-weight: 600;"><i class="icon icon-server"></i> ' . ($mailServer ?: $this->l('Not configured')) . '</div>';
            $output .= '</div>';

            // SMTP User Card
            $output .= '<div style="background: #f8f9fa; border-radius: 6px; padding: 15px; border-left: 4px solid #25b9d7;">';
            $output .= '<div style="color: #6c868e; font-size: 12px; text-transform: uppercase; margin-bottom: 5px;">' . $this->l('SMTP User') . '</div>';
            $output .= '<div style="font-size: 16px; font-weight: 600;"><i class="icon icon-user"></i> ' . ($mailUser ?: $this->l('Not configured')) . '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';

        // Add troubleshooting tips
        $output .= '<div class="alert alert-info" style="margin-top: 15px;">';
        $output .= '<h4><i class="icon icon-lightbulb-o"></i> ' . $this->l('Troubleshooting Tips') . '</h4>';
        $output .= '<ul style="margin-bottom: 0; line-height: 1.8;">';
        $output .= '<li><i class="icon icon-check"></i> ' . $this->l('If using local development (MAMP/XAMPP), emails may not be sent. Use the Preview button to see email content.') . '</li>';
        $output .= '<li><i class="icon icon-check"></i> ' . $this->l('Check your spam/junk folder if emails appear to be sent but not received.') . '</li>';
        $output .= '<li><i class="icon icon-check"></i> ' . $this->l('For SMTP, ensure server, port, and authentication settings are correct.') . '</li>';
        $output .= '<li><i class="icon icon-check"></i> ' . $this->l('Test with a different email address to rule out recipient-side filtering.') . '</li>';
        $output .= '</ul>';
        $output .= '<div style="margin-top: 15px;">';
        $output .= '<a href="' . $this->context->link->getAdminLink('AdminEmails') . '" class="btn btn-primary" target="_blank">';
        $output .= '<i class="icon icon-cog"></i> ' . $this->l('Configure Email Settings');
        $output .= '</a>';
        $output .= '</div>';
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function sendTestEmail()
    {
        // Check if a custom test email was provided
        $testEmail = Tools::getValue('test_email_address');
        $notificationEmail = Configuration::get('MB_AVAILABILITY_EMAIL');
        if (empty($notificationEmail)) {
            $notificationEmail = Configuration::get('PS_SHOP_EMAIL');
        }

        // Use custom test email if provided, otherwise use configured email
        $recipientEmail = !empty($testEmail) ? $testEmail : $notificationEmail;

        if (empty($recipientEmail)) {
            return $this->displayError($this->l('No email address specified. Please configure a notification email or enter a test email address.'));
        }

        // Validate email format
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->displayError($this->l('Invalid email address format.'));
        }

        try {
            $subject = $this->l('Test email from Availability Check module');
            $templateVars = [
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_url}' => $this->context->link->getPageLink('index', true),
                '{date}' => date('Y-m-d H:i:s'),
            ];

            // Log email configuration details
            PrestaShopLogger::addLog('mb_availability_check: Test email config - To: ' . $recipientEmail . ', Subject: ' . $subject, 1);
            PrestaShopLogger::addLog('mb_availability_check: Email method: ' . Configuration::get('PS_MAIL_METHOD'), 1);
            PrestaShopLogger::addLog('mb_availability_check: SMTP Server: ' . Configuration::get('PS_MAIL_SERVER'), 1);

            $mailSent = Mail::Send(
                $this->context->language->id,
                'test_email',
                $subject,
                $templateVars,
                $recipientEmail,
                null, // to_name
                null, // from
                null, // from_name
                null, // file_attachment
                null, // mode_smtp
                dirname(__FILE__) . '/mails/' // template_path
            );

            if ($mailSent) {
                PrestaShopLogger::addLog('mb_availability_check: Test email sent successfully to ' . $recipientEmail . ' (Mail::Send returned true)', 1);
                $recipientType = !empty($testEmail) ? $this->l('custom test address') : $this->l('configured notification address');
                return $this->displayConfirmation($this->l('Test email sent successfully to') . ' ' . $recipientEmail . ' (' . $recipientType . ')<br>' .
                    $this->l('Check your email logs below for detailed information. If you don\'t receive the email, check your PrestaShop email configuration.'));
            } else {
                PrestaShopLogger::addLog('mb_availability_check: Test email failed - Mail::Send returned false for ' . $recipientEmail, 2);
                return $this->displayError($this->l('Test email failed to send. Check your email configuration.') . '<br>' .
                    $this->l('Common issues:') . '<br>' .
                    '- ' . $this->l('SMTP settings not configured') . '<br>' .
                    '- ' . $this->l('Using local development environment (MAMP/XAMPP)') . '<br>' .
                    '- ' . $this->l('Emails going to spam folder'));
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('mb_availability_check: Test email error: ' . $e->getMessage(), 3);
            return $this->displayError($this->l('Test email error:') . ' ' . $e->getMessage());
        }
    }

    protected function previewEmail()
    {
        // Check if a custom test email was provided
        $testEmail = Tools::getValue('test_email_address');
        $notificationEmail = Configuration::get('MB_AVAILABILITY_EMAIL');
        if (empty($notificationEmail)) {
            $notificationEmail = Configuration::get('PS_SHOP_EMAIL');
        }

        // Use custom test email if provided, otherwise use configured email
        $recipientEmail = !empty($testEmail) ? $testEmail : $notificationEmail;

        if (empty($recipientEmail)) {
            return $this->displayError($this->l('No email address specified. Please configure a notification email or enter a test email address.'));
        }

        try {
            $subject = $this->l('Test email from Availability Check module');
            $templateVars = [
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_url}' => $this->context->link->getPageLink('index', true),
                '{date}' => date('Y-m-d H:i:s'),
            ];

            // Generate email content without sending
            $templatePath = dirname(__FILE__) . '/mails/';

            // Get HTML content
            $htmlContent = '';
            if (file_exists($templatePath . $this->context->language->iso_code . '/test_email.html')) {
                $htmlContent = file_get_contents($templatePath . $this->context->language->iso_code . '/test_email.html');
            } elseif (file_exists($templatePath . 'en/test_email.html')) {
                $htmlContent = file_get_contents($templatePath . 'en/test_email.html');
            }

            // Get text content
            $textContent = '';
            if (file_exists($templatePath . $this->context->language->iso_code . '/test_email.txt')) {
                $textContent = file_get_contents($templatePath . $this->context->language->iso_code . '/test_email.txt');
            } elseif (file_exists($templatePath . 'en/test_email.txt')) {
                $textContent = file_get_contents($templatePath . 'en/test_email.txt');
            }

            // Replace variables
            foreach ($templateVars as $key => $value) {
                $htmlContent = str_replace($key, $value, $htmlContent);
                $textContent = str_replace($key, $value, $textContent);
            }

            $output = '<div class="panel">';
            $output .= '<h3><i class="icon-eye"></i> ' . $this->l('Email Preview') . '</h3>';
            $output .= '<div class="alert alert-info">';
            $output .= '<strong>' . $this->l('Subject:') . '</strong> ' . $subject . '<br>';
            $output .= '<strong>' . $this->l('To:') . '</strong> ' . $recipientEmail;
            if (!empty($testEmail)) {
                $output .= ' <em>(' . $this->l('custom test address') . ')</em>';
            } else {
                $output .= ' <em>(' . $this->l('configured notification address') . ')</em>';
            }
            $output .= '<br>';
            $output .= '<strong>' . $this->l('From:') . '</strong> ' . Configuration::get('PS_SHOP_EMAIL');
            $output .= '</div>';

            $output .= '<div class="row">';
            $output .= '<div class="col-md-6">';
            $output .= '<h4>' . $this->l('HTML Version') . '</h4>';
            $output .= '<div style="border:1px solid #ddd; padding:10px; background:#f9f9f9; max-height:300px; overflow:auto;">';
            $output .= $htmlContent;
            $output .= '</div>';
            $output .= '</div>';

            $output .= '<div class="col-md-6">';
            $output .= '<h4>' . $this->l('Text Version') . '</h4>';
            $output .= '<div style="border:1px solid #ddd; padding:10px; background:#f9f9f9; max-height:300px; overflow:auto; font-family:monospace; white-space:pre-wrap;">';
            $output .= htmlspecialchars($textContent);
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';

            $output .= '<div class="alert alert-warning" style="margin-top:15px;">';
            $output .= $this->l('This is a preview of what the email would look like. The actual email may vary depending on your email client.');
            $output .= '</div>';

            $output .= '</div>';

            return $output;
        } catch (Exception $e) {
            return $this->displayError($this->l('Error generating email preview:') . ' ' . $e->getMessage());
        }
    }

    protected function renderForm()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form = [
            'form' => [
                'tabs' => [
                    'products' => $this->l('Products'),
                    'general' => $this->l('General Settings'),
                    'general_flag' => $this->l('Flag'),
                    'general_button' => $this->l('Button'),
                    'email' => $this->l('Email & Testing'),
                    'help' => $this->l('Help & Documentation'),
                ],
                'input' => [
                    // GENERAL TAB
                    [
                        'type' => 'html',
                        'name' => 'general_intro',
                        'html_content' => '<div class="mb-help-box"><i class="icon icon-info-circle icon"></i>' .
                            '<strong>' . $this->l('Configuration Overview') . '</strong><br>' .
                            $this->l('Configure how the availability check module works on your store.') .
                            '</div>',
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Email Notifications'),
                        'name' => 'MB_AVAILABILITY_SEND_EMAIL',
                        'is_bool' => true,
                        'desc' => $this->l('Automatically send an email notification when a customer submits an availability request.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Notification Email Address'),
                        'name' => 'MB_AVAILABILITY_EMAIL',
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->l('Email address to receive availability requests. Leave empty to use the default shop email') . ' (' . Configuration::get('PS_SHOP_EMAIL') . ').',
                        'placeholder' => Configuration::get('PS_SHOP_EMAIL'),
                        'tab' => 'general',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Delete all data when uninstalling'),
                        'name' => 'MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL',
                        'is_bool' => true,
                        'desc' => $this->l('If enabled, all module data (requests, settings, configurations) will be permanently deleted when the module is uninstalled. If disabled, data will be preserved for future reinstallation.'),
                        'values' => [
                            [
                                'id' => 'delete_on',
                                'value' => 1,
                                'label' => $this->l('Yes, delete all data'),
                            ],
                            [
                                'id' => 'delete_off',
                                'value' => 0,
                                'label' => $this->l('No, preserve data'),
                            ],
                        ],
                        'tab' => 'general',
                    ],
                    // FLAG SUB-TAB
                    [
                        'type' => 'html',
                        'name' => 'flag_intro',
                        'html_content' => '<div class="mb-help-box"><i class="icon icon-flag icon"></i>' .
                            '<strong>' . $this->l('Flag Customization') . '</strong><br>' .
                            $this->l('Customize the appearance of product flags displayed on listing pages.') .
                            '</div>',
                        'tab' => 'general_flag',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Product Flag Label'),
                        'name' => 'MB_AVAILABILITY_FLAG_LABEL',
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->l('Text displayed on the product flag in listing pages. Default: "Sur demande".'),
                        'placeholder' => $this->l('Sur demande'),
                        'tab' => 'general_flag',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Flag Background Color'),
                        'name' => 'MB_AVAILABILITY_FLAG_BG_COLOR',
                        'desc' => $this->l('Background color for the product flag. Default: #25b9d7 (blue).'),
                        'tab' => 'general_flag',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Flag Text Color'),
                        'name' => 'MB_AVAILABILITY_FLAG_TEXT_COLOR',
                        'desc' => $this->l('Text color for the product flag. Default: #ffffff (white).'),
                        'tab' => 'general_flag',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'flag_preview',
                        'html_content' => $this->renderFlagPreview(),
                        'tab' => 'general_flag',
                    ],
                    // BUTTON SUB-TAB
                    [
                        'type' => 'html',
                        'name' => 'button_intro',
                        'html_content' => '<div class="mb-help-box"><i class="icon icon-hand-pointer-o icon"></i>' .
                            '<strong>' . $this->l('Button Customization') . '</strong><br>' .
                            $this->l('Customize the appearance of the availability check button on product pages.') .
                            '</div>',
                        'tab' => 'general_button',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Button Label'),
                        'name' => 'MB_AVAILABILITY_BUTTON_LABEL',
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->l('Text displayed on the availability check button. Default: "Vérifier la disponibilité".'),
                        'placeholder' => $this->l('Vérifier la disponibilité'),
                        'tab' => 'general_button',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Button Background Color'),
                        'name' => 'MB_AVAILABILITY_BUTTON_BG_COLOR',
                        'desc' => $this->l('Background color for the availability check button. Default: #25b9d7 (blue).'),
                        'tab' => 'general_button',
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Button Text Color'),
                        'name' => 'MB_AVAILABILITY_BUTTON_TEXT_COLOR',
                        'desc' => $this->l('Text color for the availability check button. Default: #ffffff (white).'),
                        'tab' => 'general_button',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'button_preview',
                        'html_content' => $this->renderButtonPreview(),
                        'tab' => 'general_button',
                    ],
                    // PRODUCTS TAB
                    [
                        'type' => 'html',
                        'name' => 'products_intro',
                        'html_content' => '<div class="mb-help-box"><i class="icon icon-lightbulb-o icon"></i>' .
                            '<strong>' . $this->l('How to enable products:') . '</strong><br>' .
                            '<ul style="margin: 10px 0; padding-left: 20px;">' .
                            '<li>' . $this->l('Use the product search box to find and select individual products for global configuration') . '</li>' .
                            '<li>' . $this->l('Use the category search box to enable all products within specific categories') . '</li>' .
                            '<li>' . $this->l('Edit individual products and enable the option in the product form for per-product settings') . '</li>' .
                            '<li>' . $this->l('All enabled products (both global and per-product) are displayed below with color coding') . '</li>' .
                            '<li>' . $this->l('Remove any product or category using the × button - blue items are global products, yellow items are per-product, green items are categories') . '</li>' .
                            '</ul></div>',
                        'tab' => 'products',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'product_search_section',
                        'html_content' => $this->renderProductSearchSection(),
                        'tab' => 'products',
                    ],
                    // [
                    //     'type' => 'html',
                    //     'name' => 'products_list',
                    //     'html_content' => $this->renderEnabledProductsList(),
                    //     'tab' => 'products',
                    // ],
                    // EMAIL TAB
                    [
                        'type' => 'html',
                        'name' => 'email_test_section',
                        'html_content' => $this->renderEmailTestSection(),
                        'tab' => 'email',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'email_config_info',
                        'html_content' => $this->renderEmailConfigInfo(),
                        'tab' => 'email',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'email_logs',
                        'html_content' => $this->renderEmailLogs(),
                        'tab' => 'email',
                    ],
                    // HELP TAB
                    [
                        'type' => 'html',
                        'name' => 'documentation',
                        'html_content' => $this->renderDocumentation(),
                        'tab' => 'help',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Configuration'),
                    'class' => 'btn btn-primary pull-right',
                    'icon' => 'process-icon-save',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Match Tools::isSubmit('submitMbAvailability') in getContent()
        $helper->submit_action = 'submitMbAvailability';

        $helper->fields_value['MB_AVAILABILITY_EMAIL'] = Configuration::get('MB_AVAILABILITY_EMAIL');
        $helper->fields_value['MB_AVAILABILITY_SEND_EMAIL'] = (int) Configuration::get('MB_AVAILABILITY_SEND_EMAIL');
        $helper->fields_value['MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL'] = (int) Configuration::get('MB_AVAILABILITY_DELETE_DATA_ON_UNINSTALL', 0);
        $helper->fields_value['MB_AVAILABILITY_PRODUCTS'] = Configuration::get('MB_AVAILABILITY_PRODUCTS', '');
        $helper->fields_value['MB_AVAILABILITY_CATEGORIES'] = Configuration::get('MB_AVAILABILITY_CATEGORIES', '');
        $helper->fields_value['MB_AVAILABILITY_FLAG_LABEL'] = Configuration::get('MB_AVAILABILITY_FLAG_LABEL', 'Sur demande');
        $helper->fields_value['MB_AVAILABILITY_FLAG_BG_COLOR'] = Configuration::get('MB_AVAILABILITY_FLAG_BG_COLOR', '#25b9d7');
        $helper->fields_value['MB_AVAILABILITY_FLAG_TEXT_COLOR'] = Configuration::get('MB_AVAILABILITY_FLAG_TEXT_COLOR', '#ffffff');
        $helper->fields_value['MB_AVAILABILITY_BUTTON_LABEL'] = Configuration::get('MB_AVAILABILITY_BUTTON_LABEL', 'Vérifier la disponibilité');
        $helper->fields_value['MB_AVAILABILITY_BUTTON_BG_COLOR'] = Configuration::get('MB_AVAILABILITY_BUTTON_BG_COLOR', '#25b9d7');
        $helper->fields_value['MB_AVAILABILITY_BUTTON_TEXT_COLOR'] = Configuration::get('MB_AVAILABILITY_BUTTON_TEXT_COLOR', '#ffffff');

        $output = $helper->generateForm([$fields_form]);

        $container = '<div id="mb-admin-container">%s</div>';
        $content = sprintf($container, $output);

        return $content;
    }

    /**
     * Render product search section with multiple selector
     */
    protected function renderProductSearchSection()
    {
        $output = '<div class="mb-product-search-section">';
        $output .= '<div class="form-group">';
        $output .= '<label for="product_search">' . $this->l('Search and Select Products') . '</label>';
        $output .= '<input type="text" id="product_search" class="form-control" placeholder="' . $this->l('Type to search products...') . '" style="max-width: 400px;">';
        $output .= '<p class="help-block">' . $this->l('Search for products by name, ID, or reference. Select multiple products to enable availability checking.') . '</p>';
        $output .= '</div>';

        // Selected products container
        $output .= '<div class="form-group">';
        $output .= '<label>' . $this->l('Selected Products') . '</label>';
        $output .= '<div id="selected_products" class="mb-selected-products" style="min-height: 100px; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f9f9f9;">';
        $output .= '<p class="text-muted" id="no_products_selected">' . $this->l('No products selected yet. Use the search above to find and select products.') . '</p>';
        $output .= '</div>';
        $output .= '<p class="help-block"><i class="icon icon-info-circle"></i> ' . $this->l('Blue items are globally configured products. Yellow items are enabled per-product. Both can be removed using the × button.') . '</p>';
        $output .= '</div>';

        // $this->renderEnabledProductsList()
        $output .= $this->renderEnabledProductsList();

        // Hidden input to store selected product IDs (global config only)
        $currentProducts = Configuration::get('MB_AVAILABILITY_PRODUCTS', '');
        $output .= '<input type="hidden" name="MB_AVAILABILITY_PRODUCTS" id="selected_product_ids" value="' . htmlspecialchars($currentProducts) . '">';

        // Add JavaScript for search functionality
        $output .= '<script type="text/javascript">
            $(document).ready(function() {
                var selectedProducts = [];
                var perProductEnabled = [];
                var searchTimeout;

                // Load currently selected products (both global and per-product)
                loadAllEnabledProducts();

                // Search input handler
                $("#product_search").on("input", function() {
                    var query = $(this).val().trim();
                    clearTimeout(searchTimeout);

                    if (query.length >= 2) {
                        searchTimeout = setTimeout(function() {
                            searchProducts(query);
                        }, 300);
                    } else {
                        $("#product_search_results").remove();
                    }
                });

                function searchProducts(query) {
                    $.ajax({
                        url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                        type: "POST",
                        data: {
                            ajax: 1,
                            action: "searchProducts",
                            query: query,
                            token: "' . Tools::getAdminTokenLite('AdminModules') . '",
                            configure: "' . $this->name . '"
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                displaySearchResults(data.products || []);
                            } catch (e) {
                                console.error("Error parsing search results:", e);
                            }
                        },
                        error: function() {
                            console.error("Search request failed");
                        }
                    });
                }

                function displaySearchResults(products) {
                    $("#product_search_results").remove();

                    if (products.length === 0) {
                        return;
                    }

                    var resultsHtml = \'<div id="product_search_results" class="mb-search-results" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; position: absolute; z-index: 1000; width: 400px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">\';
                    products.forEach(function(product) {
                        var isSelected = selectedProducts.indexOf(product.id_product) !== -1;
                        var buttonClass = isSelected ? "btn-success" : "btn-default";
                        var buttonText = isSelected ? "' . $this->l('Selected') . '" : "' . $this->l('Select') . '";
                        resultsHtml += \'<div class="product-result-item" style="padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">\';
                        resultsHtml += \'<div><strong>ID \' + product.id_product + \':</strong> \' + product.name + \'</div>\';
                        resultsHtml += \'<button type="button" class="btn btn-xs \' + buttonClass + \' select-product-btn" data-product-id="\' + product.id_product + \'" data-product-name="\' + product.name.replace(/"/g, \'&quot;\') + \'">\' + buttonText + \'</button>\';
                        resultsHtml += \'</div>\';
                    });
                    resultsHtml += \'</div>\';

                    $("#product_search").after(resultsHtml);

                    // Position the results below the search input
                    var searchPos = $("#product_search").position();
                    $("#product_search_results").css({
                        top: searchPos.top + $("#product_search").outerHeight(),
                        left: searchPos.left
                    });
                }

                // Handle product selection
                $(document).on("click", ".select-product-btn", function() {
                    var productId = parseInt($(this).data("product-id"));
                    var productName = $(this).data("product-name");

                    if (selectedProducts.indexOf(productId) === -1) {
                        // Add product
                        selectedProducts.push(productId);
                        $(this).removeClass("btn-default").addClass("btn-success").text("' . $this->l('Selected') . '");
                        addSelectedProduct(productId, productName);
                    } else {
                        // Remove product
                        selectedProducts = selectedProducts.filter(function(id) { return id !== productId; });
                        $(this).removeClass("btn-success").addClass("btn-default").text("' . $this->l('Select') . '");
                        removeSelectedProduct(productId);
                    }

                    updateHiddenInput();
                });

                function addSelectedProduct(productId, productName, type) {
                    $("#no_products_selected").hide();
                    var typeLabel = type === \'per_product\' ? \' (Per-product)\' : \' (Global)\';
                    var bgColor = type === \'per_product\' ? \'#fff3cd\' : \'#e3f2fd\';
                    var borderColor = type === \'per_product\' ? \'#ffc107\' : \'#2196f3\';
                    var productHtml = \'<div class="selected-product-item" data-product-id="\' + productId + \'" style="display: inline-block; background: \' + bgColor + \'; border: 1px solid \' + borderColor + \'; border-radius: 4px; padding: 4px 8px; margin: 2px; font-size: 12px;">\';
                    productHtml += \'<strong>ID \' + productId + \':</strong> \' + productName + \'<em style="color: #666; font-size: 10px;">\' + typeLabel + \'</em>\';
                    productHtml += \' <button type="button" class="btn btn-xs btn-danger remove-product-btn" data-product-id="\' + productId + \'" style="margin-left: 8px; padding: 0 4px;">×</button>\';
                    productHtml += \'</div>\';
                    $("#selected_products").append(productHtml);
                }

                function removeSelectedProduct(productId) {
                    $(".selected-product-item[data-product-id=\'" + productId + "\']").remove();
                    if ($(".selected-product-item").length === 0) {
                        $("#no_products_selected").show();
                    }
                }

                function loadAllEnabledProducts() {
                    $.ajax({
                        url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                        type: "POST",
                        data: {
                            ajax: 1,
                            action: "getAllEnabledProducts",
                            token: "' . Tools::getAdminTokenLite('AdminModules') . '",
                            configure: "' . $this->name . '"
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.products) {
                                    data.products.forEach(function(product) {
                                        addSelectedProduct(product.id_product, product.name, product.type);
                                        if (product.type === \'global\') {
                                            selectedProducts.push(product.id_product);
                                        }
                                    });
                                }
                            } catch (e) {
                                console.error("Error loading enabled products:", e);
                            }
                        },
                        error: function() {
                            console.error("Failed to load enabled products");
                        }
                    });
                }

                function updateHiddenInput() {
                    $("#selected_product_ids").val(selectedProducts.join(","));
                }

                // Handle product removal
                $(document).on("click", ".remove-product-btn", function() {
                    var productId = parseInt($(this).data("product-id"));
                    var $productItem = $(this).closest(".selected-product-item");
                    var isPerProduct = $productItem.find("em").text().indexOf("Per-product") !== -1;
                    var productType = isPerProduct ? "per_product" : "global";

                    // For global products, remove from selectedProducts array
                    if (productType === "global") {
                        selectedProducts = selectedProducts.filter(function(id) { return id !== productId; });
                        updateHiddenInput();
                    }

                    // Make AJAX call to disable the product
                    $.ajax({
                        url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                        type: "POST",
                        data: {
                            ajax: 1,
                            action: "disableProduct",
                            id_product: productId,
                            type: productType,
                            token: "' . Tools::getAdminTokenLite('AdminModules') . '",
                            configure: "' . $this->name . '"
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.success) {
                                    removeSelectedProduct(productId);
                                    // Update search results buttons
                                    $(".select-product-btn[data-product-id=\'" + productId + "\']").removeClass("btn-success").addClass("btn-default").text("' . $this->l('Select') . '");
                                } else {
                                    alert("' . $this->l('Failed to disable product') . '");
                                }
                            } catch (e) {
                                console.error("Error disabling product:", e);
                                alert("' . $this->l('Error occurred while disabling product') . '");
                            }
                        },
                        error: function() {
                            alert("' . $this->l('Error occurred while disabling product') . '");
                        }
                    });
                });

                // Hide search results when clicking outside
                $(document).on("click", function(e) {
                    if (!$(e.target).closest("#product_search, #product_search_results").length) {
                        $("#product_search_results").remove();
                    }
                });
            });
        </script>';

        // Add Category Selection Section
        $output .= '<hr style="margin: 30px 0; border-top: 2px solid #ddd;">';
        $output .= '<h4><i class="icon icon-folder"></i> ' . $this->l('Category Selection') . '</h4>';
        $output .= '<p class="help-block">' . $this->l('Enable the availability check for all products in selected categories. When you select a category, all products within that category will automatically have the availability check enabled.') . '</p>';
        
        $output .= '<div class="form-group">';
        $output .= '<label for="category_search">' . $this->l('Search and Select Categories') . '</label>';
        $output .= '<input type="text" id="category_search" class="form-control" placeholder="' . $this->l('Type to search categories...') . '" style="max-width: 400px;">';
        $output .= '<p class="help-block">' . $this->l('Search for categories by name or ID. All active products in the selected categories will be enabled.') . '</p>';
        $output .= '</div>';

        // Selected categories container
        $output .= '<div class="form-group">';
        $output .= '<label>' . $this->l('Selected Categories') . '</label>';
        $output .= '<div id="selected_categories" class="mb-selected-categories" style="min-height: 100px; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f9f9f9;">';
        $output .= '<p class="text-muted" id="no_categories_selected">' . $this->l('No categories selected yet. Use the search above to find and select categories.') . '</p>';
        $output .= '</div>';
        $output .= '</div>';

        // Hidden input to store selected category IDs
        $currentCategories = Configuration::get('MB_AVAILABILITY_CATEGORIES', '');
        $output .= '<input type="hidden" name="MB_AVAILABILITY_CATEGORIES" id="selected_category_ids" value="' . htmlspecialchars($currentCategories) . '">';

        // Add JavaScript for category search functionality
        $output .= '<script type="text/javascript">
            $(document).ready(function() {
                var selectedCategories = [];
                var categorySearchTimeout;

                // Load currently selected categories
                loadAllEnabledCategories();

                // Category search input handler
                $("#category_search").on("input", function() {
                    var query = $(this).val().trim();
                    clearTimeout(categorySearchTimeout);

                    if (query.length >= 2) {
                        categorySearchTimeout = setTimeout(function() {
                            searchCategories(query);
                        }, 300);
                    } else {
                        $("#category_search_results").remove();
                    }
                });

                function searchCategories(query) {
                    $.ajax({
                        url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                        type: "POST",
                        data: {
                            ajax: 1,
                            action: "searchCategories",
                            query: query,
                            token: "' . Tools::getAdminTokenLite('AdminModules') . '",
                            configure: "' . $this->name . '"
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                displayCategorySearchResults(data.categories || []);
                            } catch (e) {
                                console.error("Error parsing category search results:", e);
                            }
                        },
                        error: function() {
                            console.error("Category search request failed");
                        }
                    });
                }

                function displayCategorySearchResults(categories) {
                    $("#category_search_results").remove();

                    if (categories.length === 0) {
                        return;
                    }

                    var resultsHtml = \'<div id="category_search_results" class="mb-search-results" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; position: absolute; z-index: 1000; width: 400px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">\';
                    categories.forEach(function(category) {
                        var isSelected = selectedCategories.indexOf(category.id_category) !== -1;
                        var buttonClass = isSelected ? "btn-success" : "btn-default";
                        var buttonText = isSelected ? "' . $this->l('Selected') . '" : "' . $this->l('Select') . '";
                        resultsHtml += \'<div class="category-result-item" style="padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">\';
                        resultsHtml += \'<div><strong>ID \' + category.id_category + \':</strong> \' + category.name + \'</div>\';
                        resultsHtml += \'<button type="button" class="btn btn-xs \' + buttonClass + \' select-category-btn" data-category-id="\' + category.id_category + \'" data-category-name="\' + category.name.replace(/"/g, \'&quot;\') + \'">\' + buttonText + \'</button>\';
                        resultsHtml += \'</div>\';
                    });
                    resultsHtml += \'</div>\';

                    $("#category_search").after(resultsHtml);

                    // Position the results below the search input
                    var searchPos = $("#category_search").position();
                    $("#category_search_results").css({
                        top: searchPos.top + $("#category_search").outerHeight(),
                        left: searchPos.left
                    });
                }

                // Handle category selection
                $(document).on("click", ".select-category-btn", function() {
                    var categoryId = parseInt($(this).data("category-id"));
                    var categoryName = $(this).data("category-name");

                    if (selectedCategories.indexOf(categoryId) === -1) {
                        // Add category
                        selectedCategories.push(categoryId);
                        $(this).removeClass("btn-default").addClass("btn-success").text("' . $this->l('Selected') . '");
                        addSelectedCategory(categoryId, categoryName, 0);
                    } else {
                        // Remove category
                        selectedCategories = selectedCategories.filter(function(id) { return id !== categoryId; });
                        $(this).removeClass("btn-success").addClass("btn-default").text("' . $this->l('Select') . '");
                        removeSelectedCategory(categoryId);
                    }

                    updateCategoryHiddenInput();
                });

                function addSelectedCategory(categoryId, categoryName, productCount) {
                    $("#no_categories_selected").hide();
                    var productCountText = productCount > 0 ? \' (\' + productCount + \' \' + "' . $this->l('products') . '" + \')\' : \'\';
                    var categoryHtml = \'<div class="selected-category-item" data-category-id="\' + categoryId + \'" style="display: inline-block; background: #d4edda; border: 1px solid #28a745; border-radius: 4px; padding: 4px 8px; margin: 2px; font-size: 12px;">\';
                    categoryHtml += \'<i class="icon icon-folder" style="margin-right: 4px;"></i>\';
                    categoryHtml += \'<strong>ID \' + categoryId + \':</strong> \' + categoryName + \'<em style="color: #666; font-size: 10px;">\' + productCountText + \'</em>\';
                    categoryHtml += \' <button type="button" class="btn btn-xs btn-danger remove-category-btn" data-category-id="\' + categoryId + \'" style="margin-left: 8px; padding: 0 4px;">×</button>\';
                    categoryHtml += \'</div>\';
                    $("#selected_categories").append(categoryHtml);
                }

                function removeSelectedCategory(categoryId) {
                    $(".selected-category-item[data-category-id=\'" + categoryId + "\']").remove();
                    if ($(".selected-category-item").length === 0) {
                        $("#no_categories_selected").show();
                    }
                }

                function loadAllEnabledCategories() {
                    $.ajax({
                        url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                        type: "POST",
                        data: {
                            ajax: 1,
                            action: "getAllEnabledCategories",
                            token: "' . Tools::getAdminTokenLite('AdminModules') . '",
                            configure: "' . $this->name . '"
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.categories) {
                                    data.categories.forEach(function(category) {
                                        addSelectedCategory(category.id_category, category.name, category.product_count);
                                        selectedCategories.push(category.id_category);
                                    });
                                }
                            } catch (e) {
                                console.error("Error loading enabled categories:", e);
                            }
                        },
                        error: function() {
                            console.error("Failed to load enabled categories");
                        }
                    });
                }

                function updateCategoryHiddenInput() {
                    $("#selected_category_ids").val(selectedCategories.join(","));
                }

                // Handle category removal
                $(document).on("click", ".remove-category-btn", function() {
                    var categoryId = parseInt($(this).data("category-id"));

                    selectedCategories = selectedCategories.filter(function(id) { return id !== categoryId; });
                    updateCategoryHiddenInput();

                    // Make AJAX call to disable the category
                    $.ajax({
                        url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                        type: "POST",
                        data: {
                            ajax: 1,
                            action: "disableCategory",
                            id_category: categoryId,
                            token: "' . Tools::getAdminTokenLite('AdminModules') . '",
                            configure: "' . $this->name . '"
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.success) {
                                    removeSelectedCategory(categoryId);
                                    // Update search results buttons
                                    $(".select-category-btn[data-category-id=\'" + categoryId + "\']").removeClass("btn-success").addClass("btn-default").text("' . $this->l('Select') . '");
                                } else {
                                    alert("' . $this->l('Failed to disable category') . '");
                                }
                            } catch (e) {
                                console.error("Error disabling category:", e);
                                alert("' . $this->l('Error occurred while disabling category') . '");
                            }
                        },
                        error: function() {
                            alert("' . $this->l('Error occurred while disabling category') . '");
                        }
                    });
                });

                // Hide search results when clicking outside
                $(document).on("click", function(e) {
                    if (!$(e.target).closest("#category_search, #category_search_results").length) {
                        $("#category_search_results").remove();
                    }
                });
            });
        </script>';

        $output .= '</div>';
        return $output;
    }

    /**
     * Render flag preview section
     */
    protected function renderFlagPreview()
    {
        $label = Configuration::get('MB_AVAILABILITY_FLAG_LABEL', 'Sur demande');
        $bgColor = Configuration::get('MB_AVAILABILITY_FLAG_BG_COLOR', '#25b9d7');
        $textColor = Configuration::get('MB_AVAILABILITY_FLAG_TEXT_COLOR', '#ffffff');

        $output = '<div class="panel" style="margin-top: 20px;">';
        $output .= '<div class="panel-heading"><i class="icon icon-eye"></i> ' . $this->l('Flag Preview') . '</div>';
        $output .= '<div style="padding: 20px;">';
        $output .= '<p>' . $this->l('This is how your flag will appear on product listing pages:') . '</p>';
        $output .= '<div style="background: #f5f5f5; padding: 20px; border-radius: 4px; display: inline-block;">';
        $output .= '<div class="product-flags" style="display: flex; gap: 5px;">';
        $output .= '<span class="product-flag mb-availability-check" style="background: ' . htmlspecialchars($bgColor) . '; color: ' . htmlspecialchars($textColor) . '; padding: 5px 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;">';
        $output .= '<i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">help_outline</i> ';
        $output .= '<span class="flag-text">' . htmlspecialchars($label) . '</span>';
        $output .= '</span>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '<p class="help-block" style="margin-top: 15px;"><i class="icon icon-info-circle"></i> ' . $this->l('Changes to the label and colors will be reflected in this preview after saving the configuration.') . '</p>';
        $output .= '<script>
            $(document).ready(function() {
                // Function to update preview
                function updatePreview() {
                    var label = $("#MB_AVAILABILITY_FLAG_LABEL").val() || "Sur demande";
                    var bgColor = $("#MB_AVAILABILITY_FLAG_BG_COLOR").val() || "#25b9d7";
                    var textColor = $("#MB_AVAILABILITY_FLAG_TEXT_COLOR").val() || "#ffffff";

                    $(".product-flag.mb-availability-check").css({
                        "background": bgColor,
                        "color": textColor
                    });
                    $(".product-flag.mb-availability-check .flag-text").text(label);
                }

                // Live preview update - bind to all relevant inputs
                $("#MB_AVAILABILITY_FLAG_LABEL, #MB_AVAILABILITY_FLAG_BG_COLOR, #MB_AVAILABILITY_FLAG_TEXT_COLOR").on("change input", function() {
                    updatePreview();
                });

                // Also try with different selectors in case the IDs are different
                $(document).on("change input", "input[name=\"MB_AVAILABILITY_FLAG_LABEL\"], input[name=\"MB_AVAILABILITY_FLAG_BG_COLOR\"], input[name=\"MB_AVAILABILITY_FLAG_TEXT_COLOR\"]", function() {
                    updatePreview();
                });

                // Initialize with current values
                updatePreview();

                // Initialize color pickers with default values if empty
                setTimeout(function() {
                    if (!$("#MB_AVAILABILITY_FLAG_BG_COLOR").val()) {
                        $("#MB_AVAILABILITY_FLAG_BG_COLOR").val("#25b9d7").trigger("change");
                    }
                    if (!$("#MB_AVAILABILITY_FLAG_TEXT_COLOR").val()) {
                        $("#MB_AVAILABILITY_FLAG_TEXT_COLOR").val("#ffffff").trigger("change");
                    }
                    // Also try with name selectors
                    if (!$("input[name=\"MB_AVAILABILITY_FLAG_BG_COLOR\"]").val()) {
                        $("input[name=\"MB_AVAILABILITY_FLAG_BG_COLOR\"]").val("#25b9d7").trigger("change");
                    }
                    if (!$("input[name=\"MB_AVAILABILITY_FLAG_TEXT_COLOR\"]").val()) {
                        $("input[name=\"MB_AVAILABILITY_FLAG_TEXT_COLOR\"]").val("#ffffff").trigger("change");
                    }
                }, 500);
            });
        </script>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render button preview section
     */
    protected function renderButtonPreview()
    {
        $label = Configuration::get('MB_AVAILABILITY_BUTTON_LABEL', 'Vérifier la disponibilité');
        $bgColor = Configuration::get('MB_AVAILABILITY_BUTTON_BG_COLOR', '#25b9d7');
        $textColor = Configuration::get('MB_AVAILABILITY_BUTTON_TEXT_COLOR', '#ffffff');

        $output = '<div class="panel" style="margin-top: 20px;">';
        $output .= '<div class="panel-heading"><i class="icon icon-eye"></i> ' . $this->l('Button Preview') . '</div>';
        $output .= '<div style="padding: 20px;">';
        $output .= '<p>' . $this->l('This is how your availability check button will appear on product pages:') . '</p>';
        $output .= '<div style="background: #f5f5f5; padding: 20px; border-radius: 4px; display: inline-block;">';
        $output .= '<a href="#" class="btn btn-primary mb-availability-btn" style="background: ' . htmlspecialchars($bgColor) . '; color: ' . htmlspecialchars($textColor) . '; border-color: ' . htmlspecialchars($bgColor) . '; text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 4px; font-weight: 600;">';
        $output .= '<span class="button-text">' . htmlspecialchars($label) . '</span>';
        $output .= '</a>';
        $output .= '</div>';
        $output .= '<p class="help-block" style="margin-top: 15px;"><i class="icon icon-info-circle"></i> ' . $this->l('Changes to the label and colors will be reflected in this preview after saving the configuration.') . '</p>';
        $output .= '<script>
            $(document).ready(function() {
                // Function to update preview
                function updatePreview() {
                    var label = $("#MB_AVAILABILITY_BUTTON_LABEL").val() || "Vérifier la disponibilité";
                    var bgColor = $("#MB_AVAILABILITY_BUTTON_BG_COLOR").val() || "#25b9d7";
                    var textColor = $("#MB_AVAILABILITY_BUTTON_TEXT_COLOR").val() || "#ffffff";

                    $(".mb-availability-btn").css({
                        "background": bgColor,
                        "color": textColor,
                        "border-color": bgColor
                    });
                    $(".mb-availability-btn .button-text").text(label);
                }

                // Live preview update - bind to all relevant inputs
                $("#MB_AVAILABILITY_BUTTON_LABEL, #MB_AVAILABILITY_BUTTON_BG_COLOR, #MB_AVAILABILITY_BUTTON_TEXT_COLOR").on("change input", function() {
                    updatePreview();
                });

                // Also try with different selectors in case the IDs are different
                $(document).on("change input", "input[name=\"MB_AVAILABILITY_BUTTON_LABEL\"], input[name=\"MB_AVAILABILITY_BUTTON_BG_COLOR\"], input[name=\"MB_AVAILABILITY_BUTTON_TEXT_COLOR\"]", function() {
                    updatePreview();
                });

                // Initialize with current values
                updatePreview();

                // Initialize color pickers with default values if empty
                setTimeout(function() {
                    if (!$("#MB_AVAILABILITY_BUTTON_BG_COLOR").val()) {
                        $("#MB_AVAILABILITY_BUTTON_BG_COLOR").val("#25b9d7").trigger("change");
                    }
                    if (!$("#MB_AVAILABILITY_BUTTON_TEXT_COLOR").val()) {
                        $("#MB_AVAILABILITY_BUTTON_TEXT_COLOR").val("#ffffff").trigger("change");
                    }
                    // Also try with name selectors
                    if (!$("input[name=\"MB_AVAILABILITY_BUTTON_BG_COLOR\"]").val()) {
                        $("input[name=\"MB_AVAILABILITY_BUTTON_BG_COLOR\"]").val("#25b9d7").trigger("change");
                    }
                    if (!$("input[name=\"MB_AVAILABILITY_BUTTON_TEXT_COLOR\"]").val()) {
                        $("input[name=\"MB_AVAILABILITY_BUTTON_TEXT_COLOR\"]").val("#ffffff").trigger("change");
                    }
                }, 500);
            });
        </script>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render email test section
     */
    protected function renderEmailTestSection()
    {
        $output = '<div class="mb-email-test-section">';
        $output .= '<div class="mb-section-header"><h3><i class="icon icon-flask"></i> ' . $this->l('Test Email Configuration') . '</h3></div>';
        $output .= '<p>' . $this->l('Use these tools to verify your email configuration is working correctly.') . '</p>';

        // Test email input field
        $output .= '<div class="form-group">';
        $output .= '<label for="test_email_address">' . $this->l('Test Email Address') . '</label>';
        $configuredEmail = Configuration::get('MB_AVAILABILITY_EMAIL') ?: Configuration::get('PS_SHOP_EMAIL');
        $defaultTestEmail = Tools::getValue('test_email_address') ?: $configuredEmail;
        $output .= '<input type="email" id="test_email_address" name="test_email_address" class="form-control" style="max-width: 400px;" ';
        $output .= 'placeholder="' . $this->l('Enter email address for testing') . '" ';
        $output .= 'value="' . htmlspecialchars($defaultTestEmail) . '">';
        $output .= '<p class="help-block">' . $this->l('This will be used as the recipient for test emails. You can change it to test with different addresses.') . '</p>';
        $output .= '</div>';

        $output .= '<div class="form-group">';
        $output .= '<button type="submit" name="submitTestEmail" class="btn btn-success">';
        $output .= '<i class="icon icon-paper-plane"></i> ' . $this->l('Send Test Email');
        $output .= '</button>';
        $output .= '<button type="submit" name="submitPreviewEmail" class="btn btn-info">';
        $output .= '<i class="icon icon-eye"></i> ' . $this->l('Preview Email Template');
        $output .= '</button>';
        $output .= '</div>';

        $configuredEmail = Configuration::get('MB_AVAILABILITY_EMAIL') ?: Configuration::get('PS_SHOP_EMAIL');
        $output .= '<div class="alert alert-info">';
        $output .= '<i class="icon icon-info-circle"></i> ';
        $output .= $this->l('The test email field above is pre-filled with your configured notification email. You can change it to test with different addresses without affecting your saved configuration.');
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Render enabled products list
     */
    protected function renderEnabledProductsList()
    {
        $output = '<div class="panel" style="margin-top: 20px;">';
        $output .= '<div class="panel-heading">';
        $output .= '<i class="icon icon-list"></i> ' . $this->l('Currently Enabled Products');

        // Get all enabled products
        $enabledProducts = [];

        // Get from global config
        $globalProducts = Configuration::get('MB_AVAILABILITY_PRODUCTS');
        if (!empty($globalProducts)) {
            $productIds = array_map('intval', array_filter(explode(',', $globalProducts)));
            if (!empty($productIds)) {
                $sql = 'SELECT p.id_product, pl.name
                        FROM `' . _DB_PREFIX_ . 'product` p
                        LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                        ON p.id_product = pl.id_product
                        AND pl.id_lang = ' . (int)$this->context->language->id . '
                        WHERE p.id_product IN (' . implode(',', $productIds) . ')';
                $globalList = Db::getInstance()->executeS($sql);
                if ($globalList) {
                    foreach ($globalList as $prod) {
                        $enabledProducts[$prod['id_product']] = $prod['name'] . ' (Global)';
                    }
                }
            }
        }

        // Get from per-product settings
        $sql = 'SELECT s.id_product, pl.name
                FROM `' . _DB_PREFIX_ . 'mb_availability_settings` s
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON s.id_product = pl.id_product
                AND pl.id_lang = ' . (int)$this->context->language->id . '
                WHERE s.enabled = 1';
        $perProductList = Db::getInstance()->executeS($sql);
        if ($perProductList) {
            foreach ($perProductList as $prod) {
                if (!isset($enabledProducts[$prod['id_product']])) {
                    $enabledProducts[$prod['id_product']] = $prod['name'] . ' (Per-product)';
                }
            }
        }

        $count = count($enabledProducts);
        $output .= '<span class="badge badge-primary">' . $count . '</span>';
        $output .= '</div>';

        if ($count > 0) {
            $output .= '<div class="mb-product-list">';
            $output .= '<ul style="list-style: none; padding: 0; margin: 0;">';
            foreach ($enabledProducts as $id => $name) {
                $output .= '<li style="padding: 8px; border-bottom: 1px solid #e5e5e5;">';
                $output .= '<i class="icon icon-check-circle" style="color: #72c02c; margin-right: 8px;"></i>';
                $output .= '<strong>ID ' . (int)$id . ':</strong> ' . htmlspecialchars($name);
                $output .= '</li>';
            }
            $output .= '</ul>';
            $output .= '</div>';
        } else {
            $output .= '<div class="alert alert-warning">';
            $output .= '<i class="icon icon-warning"></i> ';
            $output .= $this->l('No products are currently enabled for availability check.');
            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render documentation section
     */
    protected function renderDocumentation()
    {
        $output = '<div class="panel">';
        $output .= '<div class="panel-heading"><i class="icon icon-book"></i> ' . $this->l('Module Documentation') . '</div>';
        $output .= '<div style="padding: 20px;">';

        $output .= '<h4 style="color: #25b9d7; margin-top: 0;"><i class="icon icon-info-circle"></i> ' . $this->l('What does this module do?') . '</h4>';
        $output .= '<p>' . $this->l('This module replaces the "Add to cart" button with a "Check availability" button for selected products. When customers click this button, they can submit a request with their contact information and desired quantity.') . '</p>';

        $output .= '<hr>';

        $output .= '<h4 style="color: #25b9d7;"><i class="icon icon-play-circle"></i> ' . $this->l('How to use this module') . '</h4>';
        $output .= '<ol style="line-height: 2;">';
        $output .= '<li><strong>' . $this->l('Enable products') . '</strong><br>' . $this->l('Use the search interface in the Products tab to find and select products for global configuration, or enable them individually when editing each product.') . '</li>';
        $output .= '<li><strong>' . $this->l('Configure notification settings') . '</strong><br>' . $this->l('Set your notification email address and enable email notifications in the General Settings tab.') . '</li>';
        $output .= '<li><strong>' . $this->l('Test email configuration') . '</strong><br>' . $this->l('Use the Email & Testing tab to send a test email and verify everything works.') . '</li>';
        $output .= '<li><strong>' . $this->l('Monitor requests') . '</strong><br>' . $this->l('View and manage customer requests in the "Availability Requests" menu.') . '</li>';
        $output .= '</ol>';

        $output .= '<hr>';

        $output .= '<h4 style="color: #25b9d7;"><i class="icon icon-question-circle"></i> ' . $this->l('Frequently Asked Questions') . '</h4>';

        $output .= '<div style="margin: 15px 0;">';
        $output .= '<strong>' . $this->l('Q: How do customers add products to cart after submitting a request?') . '</strong><br>';
        $output .= $this->l('A: Customers receive a unique link via email that allows them to add the product with the requested quantity to their cart. The quantity is locked and cannot be changed.');
        $output .= '</div>';

        $output .= '<div style="margin: 15px 0;">';
        $output .= '<strong>' . $this->l('Q: Can I enable this for all products at once?') . '</strong><br>';
        $output .= $this->l('A: Currently, you need to specify product IDs or enable products individually. This ensures you have full control over which products use this feature.');
        $output .= '</div>';

        $output .= '<div style="margin: 15px 0;">';
        $output .= '<strong>' . $this->l('Q: Why am I not receiving notification emails?') . '</strong><br>';
        $output .= $this->l('A: Check your PrestaShop email configuration in Advanced Parameters > E-mail. If using a local environment (MAMP/XAMPP), email sending may not work without proper SMTP configuration.');
        $output .= '</div>';

        $output .= '<div style="margin: 15px 0;">';
        $output .= '<strong>' . $this->l('Q: Where can I see all availability requests?') . '</strong><br>';
        $output .= $this->l('A: Go to Orders > Availability Requests in your back office menu.');
        $output .= '</div>';

        $output .= '<hr>';

        $output .= '<div class="alert alert-success">';
        $output .= '<h4><i class="icon icon-support"></i> ' . $this->l('Need Help?') . '</h4>';
        $output .= '<p>' . $this->l('If you need assistance with this module, contact Mobytic support.') . '</p>';
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Save value after update / create
     */
    public function hookActionAfterUpdateProductFormHandler($params)
    {
        $this->handleProductFormSave($params);
    }

    public function hookActionAfterCreateProductFormHandler($params)
    {
        $this->handleProductFormSave($params);
    }

    protected function handleProductFormSave($params)
    {
        $idProduct = (int) $params['id'];
        $formData = $params['form_data'];

        $enabled = isset($formData['mb_availability_enabled'])
            ? (bool) $formData['mb_availability_enabled']
            : false;

        $this->saveProductSetting($idProduct, $enabled);
    }

    /**
     * Handle AJAX requests for product search
     */
    public function ajaxProcessSearchProducts()
    {
        $query = Tools::getValue('query', '');
        $results = [];

        if (strlen($query) >= 2) {
            $sql = 'SELECT p.id_product, pl.name
                    FROM `' . _DB_PREFIX_ . 'product` p
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON p.id_product = pl.id_product
                    AND pl.id_lang = ' . (int)$this->context->language->id . '
                    WHERE (p.id_product LIKE \'%' . pSQL($query) . '%\'
                    OR pl.name LIKE \'%' . pSQL($query) . '%\'
                    OR p.reference LIKE \'%' . pSQL($query) . '%\')
                    AND p.active = 1
                    ORDER BY p.id_product ASC
                    LIMIT 20';

            $products = Db::getInstance()->executeS($sql);
            if ($products) {
                foreach ($products as $product) {
                    $results[] = [
                        'id_product' => (int)$product['id_product'],
                        'name' => $product['name']
                    ];
                }
            }
        }

        die(json_encode(['products' => $results]));
    }

    /**
     * Handle AJAX requests for getting product names by IDs
     */
    public function ajaxProcessGetProductNames()
    {
        $productIds = Tools::getValue('product_ids', '');
        $results = [];

        if (!empty($productIds)) {
            $ids = array_map('intval', array_filter(explode(',', $productIds)));
            if (!empty($ids)) {
                $sql = 'SELECT p.id_product, pl.name
                        FROM `' . _DB_PREFIX_ . 'product` p
                        LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                        ON p.id_product = pl.id_product
                        AND pl.id_lang = ' . (int)$this->context->language->id . '
                        WHERE p.id_product IN (' . implode(',', $ids) . ')';

                $products = Db::getInstance()->executeS($sql);
                if ($products) {
                    foreach ($products as $product) {
                        $results[] = [
                            'id_product' => (int)$product['id_product'],
                            'name' => $product['name']
                        ];
                    }
                }
            }
        }

        die(json_encode(['products' => $results]));
    }

    /**
     * Handle AJAX requests for getting all enabled products (both global and per-product)
     */
    public function ajaxProcessGetAllEnabledProducts()
    {
        $results = [];

        // Get global products
        $globalProducts = Configuration::get('MB_AVAILABILITY_PRODUCTS');
        if (!empty($globalProducts)) {
            $productIds = array_map('intval', array_filter(explode(',', $globalProducts)));
            if (!empty($productIds)) {
                $sql = 'SELECT p.id_product, pl.name
                        FROM `' . _DB_PREFIX_ . 'product` p
                        LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                        ON p.id_product = pl.id_product
                        AND pl.id_lang = ' . (int)$this->context->language->id . '
                        WHERE p.id_product IN (' . implode(',', $productIds) . ')';
                $globalList = Db::getInstance()->executeS($sql);
                if ($globalList) {
                    foreach ($globalList as $product) {
                        $results[] = [
                            'id_product' => (int)$product['id_product'],
                            'name' => $product['name'],
                            'type' => 'global'
                        ];
                    }
                }
            }
        }

        // Get per-product enabled products
        $sql = 'SELECT s.id_product, pl.name
                FROM `' . _DB_PREFIX_ . 'mb_availability_settings` s
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON s.id_product = pl.id_product
                AND pl.id_lang = ' . (int)$this->context->language->id . '
                WHERE s.enabled = 1';
        $perProductList = Db::getInstance()->executeS($sql);
        if ($perProductList) {
            foreach ($perProductList as $product) {
                // Check if this product is already in global list
                $exists = false;
                foreach ($results as $existing) {
                    if ($existing['id_product'] == $product['id_product']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $results[] = [
                        'id_product' => (int)$product['id_product'],
                        'name' => $product['name'],
                        'type' => 'per_product'
                    ];
                }
            }
        }

        die(json_encode(['products' => $results]));
    }

    /**
     * Handle AJAX requests for disabling a product (removes from both global and per-product settings)
     */
    public function ajaxProcessDisableProduct()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $type = Tools::getValue('type');

        if ($idProduct <= 0) {
            die(json_encode(['success' => false, 'message' => 'Invalid product ID']));
        }

        $success = false;

        if ($type === 'global') {
            // Remove from global configuration
            $currentProducts = Configuration::get('MB_AVAILABILITY_PRODUCTS');
            if (!empty($currentProducts)) {
                $productIds = array_map('intval', array_filter(explode(',', $currentProducts)));
                $productIds = array_filter($productIds, function ($id) use ($idProduct) {
                    return $id !== $idProduct;
                });
                $newProducts = implode(',', $productIds);
                $success = Configuration::updateValue('MB_AVAILABILITY_PRODUCTS', $newProducts);
            }
        } elseif ($type === 'per_product') {
            // Disable in per-product settings
            $success = $this->saveProductSetting($idProduct, false);
        }

        die(json_encode(['success' => $success]));
    }

    /**
     * Handle AJAX requests for clearing email logs
     */
    public function ajaxProcessClearEmailLogs()
    {
        try {
            // Delete logs from PrestaShop log table that match the module's email logs
            $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'log`
                    WHERE `message` LIKE "%mb_availability_check: Notification email%"';

            $result = Db::getInstance()->execute($sql);

            if ($result) {
                die(json_encode(['success' => true, 'message' => $this->l('Email logs cleared successfully')]));
            } else {
                die(json_encode(['success' => false, 'message' => $this->l('Failed to clear email logs')]));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'message' => $this->l('Error clearing email logs: ') . $e->getMessage()]));
        }
    }

    /**
     * Handle AJAX requests for category search
     */
    public function ajaxProcessSearchCategories()
    {
        $query = Tools::getValue('query', '');
        $results = [];

        if (strlen($query) >= 2) {
            $sql = 'SELECT c.id_category, cl.name
                    FROM `' . _DB_PREFIX_ . 'category` c
                    LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                    ON c.id_category = cl.id_category
                    AND cl.id_lang = ' . (int)$this->context->language->id . '
                    WHERE (c.id_category LIKE \'%' . pSQL($query) . '%\'
                    OR cl.name LIKE \'%' . pSQL($query) . '%\')
                    AND c.active = 1
                    AND c.id_category != 1
                    AND c.id_category != 2
                    ORDER BY cl.name ASC
                    LIMIT 20';

            $categories = Db::getInstance()->executeS($sql);
            if ($categories) {
                foreach ($categories as $category) {
                    $results[] = [
                        'id_category' => (int)$category['id_category'],
                        'name' => $category['name']
                    ];
                }
            }
        }

        die(json_encode(['categories' => $results]));
    }

    /**
     * Handle AJAX requests for getting all enabled categories
     */
    public function ajaxProcessGetAllEnabledCategories()
    {
        $results = [];

        // Get global categories
        $globalCategories = Configuration::get('MB_AVAILABILITY_CATEGORIES');
        if (!empty($globalCategories)) {
            $categoryIds = array_map('intval', array_filter(explode(',', $globalCategories)));
            if (!empty($categoryIds)) {
                $sql = 'SELECT c.id_category, cl.name
                        FROM `' . _DB_PREFIX_ . 'category` c
                        LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                        ON c.id_category = cl.id_category
                        AND cl.id_lang = ' . (int)$this->context->language->id . '
                        WHERE c.id_category IN (' . implode(',', $categoryIds) . ')';
                $categoryList = Db::getInstance()->executeS($sql);
                if ($categoryList) {
                    foreach ($categoryList as $category) {
                        // Count products in this category
                        $productCount = (int) Db::getInstance()->getValue(
                            'SELECT COUNT(DISTINCT cp.id_product)
                            FROM `' . _DB_PREFIX_ . 'category_product` cp
                            INNER JOIN `' . _DB_PREFIX_ . 'product` p ON cp.id_product = p.id_product
                            WHERE cp.id_category = ' . (int)$category['id_category'] . '
                            AND p.active = 1'
                        );
                        
                        $results[] = [
                            'id_category' => (int)$category['id_category'],
                            'name' => $category['name'],
                            'product_count' => $productCount
                        ];
                    }
                }
            }
        }

        die(json_encode(['categories' => $results]));
    }

    /**
     * Handle AJAX requests for disabling a category
     */
    public function ajaxProcessDisableCategory()
    {
        $idCategory = (int) Tools::getValue('id_category');

        if ($idCategory <= 0) {
            die(json_encode(['success' => false, 'message' => 'Invalid category ID']));
        }

        // Remove from global configuration
        $currentCategories = Configuration::get('MB_AVAILABILITY_CATEGORIES');
        if (!empty($currentCategories)) {
            $categoryIds = array_map('intval', array_filter(explode(',', $currentCategories)));
            $categoryIds = array_filter($categoryIds, function ($id) use ($idCategory) {
                return $id !== $idCategory;
            });
            $newCategories = implode(',', $categoryIds);
            $success = Configuration::updateValue('MB_AVAILABILITY_CATEGORIES', $newCategories);
            
            die(json_encode(['success' => $success]));
        }

        die(json_encode(['success' => false, 'message' => 'Category not found']));
    }
}
