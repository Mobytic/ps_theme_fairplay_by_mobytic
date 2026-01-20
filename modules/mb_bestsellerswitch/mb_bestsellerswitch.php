<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Mb_Bestsellerswitch extends Module implements WidgetInterface
{
    const CFG_MODE = 'MB_BS_MODE'; // 'units' or 'revenue'
    const CFG_LIMIT = 'MB_BS_LIMIT';
    const CFG_USE_TAX_EXCL = 'MB_BS_TAX_EXCL'; // 1=HT, 0=TTC
    const CFG_DAYS = 'MB_BS_DAYS'; // 0 = all time

    public function __construct()
    {
        $this->name = 'mb_bestsellerswitch';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'mobytic';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Best sellers: switch revenue/units');
        $this->description = $this->l('Displays best sellers sorted by units sold or revenue, configurable in back office.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHome')
            && $this->registerHook('displayHeader')
            && Configuration::updateValue(self::CFG_MODE, 'units')
            && Configuration::updateValue(self::CFG_LIMIT, 12)
            && Configuration::updateValue(self::CFG_USE_TAX_EXCL, 1)
            && Configuration::updateValue(self::CFG_DAYS, 0);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CFG_MODE)
            && Configuration::deleteByName(self::CFG_LIMIT)
            && Configuration::deleteByName(self::CFG_USE_TAX_EXCL)
            && Configuration::deleteByName(self::CFG_DAYS);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitMbBestsellerSwitch')) {
            $mode = Tools::getValue(self::CFG_MODE);
            $limit = (int) Tools::getValue(self::CFG_LIMIT);
            $taxExcl = (int) Tools::getValue(self::CFG_USE_TAX_EXCL);
            $days = (int) Tools::getValue(self::CFG_DAYS);

            if (!in_array($mode, ['units', 'revenue'], true)) {
                $mode = 'units';
            }
            if ($limit <= 0) {
                $limit = 12;
            }
            if ($days < 0) {
                $days = 0;
            }
            $taxExcl = $taxExcl ? 1 : 0;

            Configuration::updateValue(self::CFG_MODE, $mode);
            Configuration::updateValue(self::CFG_LIMIT, $limit);
            Configuration::updateValue(self::CFG_USE_TAX_EXCL, $taxExcl);
            Configuration::updateValue(self::CFG_DAYS, $days);

            $output .= $this->displayConfirmation($this->l('Settings updated.'));
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Best sellers settings'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'radio',
                        'label' => $this->l('Ranking mode'),
                        'name' => self::CFG_MODE,
                        'required' => true,
                        'values' => [
                            ['id' => 'mb_bs_units', 'value' => 'units', 'label' => $this->l('Units sold')],
                            ['id' => 'mb_bs_revenue', 'value' => 'revenue', 'label' => $this->l('Revenue')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Revenue type'),
                        'name' => self::CFG_USE_TAX_EXCL,
                        'values' => [
                            ['id' => 'mb_bs_tax_excl_on', 'value' => 1, 'label' => $this->l('Tax excl. (HT)')],
                            ['id' => 'mb_bs_tax_excl_off', 'value' => 0, 'label' => $this->l('Tax incl. (TTC)')],
                        ],
                        'desc' => $this->l('Only used when ranking mode is Revenue.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Number of products'),
                        'name' => self::CFG_LIMIT,
                        'class' => 'fixed-width-sm',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Period (days)'),
                        'name' => self::CFG_DAYS,
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('0 = all time. Example: 365 = last 12 months (approx).'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMbBestsellerSwitch';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value = [
            self::CFG_MODE => Configuration::get(self::CFG_MODE),
            self::CFG_LIMIT => (int) Configuration::get(self::CFG_LIMIT),
            self::CFG_USE_TAX_EXCL => (int) Configuration::get(self::CFG_USE_TAX_EXCL),
            self::CFG_DAYS => (int) Configuration::get(self::CFG_DAYS),
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function hookDisplayHeader($params)
    {
        // Optional: you can add CSS here if needed
        return;
    }

    /* ========== WidgetInterface ========== */

    public function renderWidget($hookName, array $configuration)
    {
        $vars = $this->getWidgetVariables($hookName, $configuration);
        if (empty($vars['products'])) {
            return '';
        }

        $this->smarty->assign($vars);
        return $this->fetch('module:' . $this->name . '/views/templates/hook/mb_bestsellerswitch.tpl');
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        $mode = Configuration::get(self::CFG_MODE);
        $limit = (int) Configuration::get(self::CFG_LIMIT);
        $useTaxExcl = (int) Configuration::get(self::CFG_USE_TAX_EXCL);
        $days = (int) Configuration::get(self::CFG_DAYS);

        $rows = $this->getBestSellersRows($idLang, $idShop, $limit, $mode, $useTaxExcl, $days);

        // Convert ids to full product arrays (prices, images, links, etc.)
        $products = [];
        foreach ($rows as $r) {
            $products[] = (int) $r['id_product'];
        }

        if (empty($products)) {
            return ['products' => []];
        }

        // Load full product data
        $productDatas = [];
        foreach ($products as $idProduct) {
            $product = new Product($idProduct, true, $idLang, $idShop);
            $productDatas[] = (array) Product::getProductProperties(
                $idLang,
                $product->getFields()
            );
        }

        // Ensure required keys exist to avoid core warnings, then map metric into product array
        foreach ($productDatas as &$pd) {
            if (!isset($pd['out_of_stock'])) {
                $pd['out_of_stock'] = 0;
            }
            if (!isset($pd['link_rewrite'])) {
                $pd['link_rewrite'] = '';
            }
            if (!isset($pd['cover'])) {
                $pd['cover'] = ['id_image' => null, 'legend' => '', 'bySize' => []];
            }
            if (!isset($pd['main_variants'])) {
                $pd['main_variants'] = [];
            }
            if (!isset($pd['url'])) {
                $pd['url'] = $this->context->link->getProductLink((int) $pd['id_product']);
            }
            if (!isset($pd['has_discount'])) {
                $pd['has_discount'] = false;
            }
        }
        unset($pd);

        // Map metric into product array so you can display it if you want
        $metricById = [];
        foreach ($rows as $r) {
            $metricById[(int) $r['id_product']] = $r;
        }

        foreach ($productDatas as &$p) {
            $id = (int) $p['id_product'];
            if (isset($metricById[$id])) {
                $p['mb_units_sold'] = (int) $metricById[$id]['units_sold'];
                $p['mb_revenue'] = (float) $metricById[$id]['revenue'];
            }
        }
        unset($p);

        return [
            'title' => ($mode === 'revenue') ? $this->l('Best Sellers (Revenue)') : $this->l('Best Sellers (Units)'),
            'mode' => $mode,
            'products' => $productDatas,
            'allBestSellers' => $this->context->link->getPageLink('best-sales'),
        ];
    }

    protected function getBestSellersRows($idLang, $idShop, $limit, $mode, $useTaxExcl, $days)
    {
        $limit = max(1, (int) $limit);
        $mode = in_array($mode, ['units', 'revenue'], true) ? $mode : 'units';

        $priceField = $useTaxExcl ? 'od.unit_price_tax_excl' : 'od.unit_price_tax_incl';

        $dateFilter = '';
        if ((int) $days > 0) {
            // last N days
            $date = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days'));
            $dateFilter = " AND o.date_add >= '" . pSQL($date) . "' ";
        }

        // NOTE:
        // - o.valid=1: only valid orders
        // - restrict to current shop via o.id_shop
        // - restrict to active + visible products via product_shop
        $sql = "
            SELECT
                od.product_id AS id_product,
                SUM(od.product_quantity) AS units_sold,
                SUM(od.product_quantity * {$priceField}) AS revenue
            FROM " . _DB_PREFIX_ . "order_detail od
            INNER JOIN " . _DB_PREFIX_ . "orders o
                ON o.id_order = od.id_order
            INNER JOIN " . _DB_PREFIX_ . "product_shop ps
                ON ps.id_product = od.product_id
                AND ps.id_shop = " . (int) $idShop . "
            WHERE
                o.valid = 1
                AND o.id_shop = " . (int) $idShop . "
                {$dateFilter}
                AND ps.active = 1
                AND ps.visibility IN ('both','catalog')
            GROUP BY od.product_id
        ";

        if ($mode === 'revenue') {
            $sql .= " ORDER BY revenue DESC, units_sold DESC, id_product ASC ";
        } else {
            $sql .= " ORDER BY units_sold DESC, revenue DESC, id_product ASC ";
        }

        $sql .= " LIMIT " . (int) $limit;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }
}
