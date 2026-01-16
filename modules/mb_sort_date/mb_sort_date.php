<?php
/**
 * Module: MB Sort by Date
 * Description: Add sort by availability date for selected categories
 * Author: Mobytic
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mb_Sort_Date extends Module
{
    public function __construct()
    {
        $this->name = 'mb_sort_date';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mobytic';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Sort by Availability Date');
        $this->description = $this->l('Add sort options by availability date (ascending/descending) for selected categories');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    /**
     * Install module
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('actionProductSearchProviderRunQueryBefore')
            && $this->registerHook('actionSearch')
            && $this->registerHook('displayHeader')
            && Configuration::updateValue('MB_SORT_DATE_CATEGORIES', '');
    }

    /**
     * Uninstall module
     */
    public function uninstall()
    {
        return Configuration::deleteByName('MB_SORT_DATE_CATEGORIES')
            && parent::uninstall();
    }



    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitMbSortDateConfig')) {
            $categories = Tools::getValue('MB_SORT_DATE_CATEGORIES');

            if (is_array($categories)) {
                Configuration::updateValue('MB_SORT_DATE_CATEGORIES', json_encode($categories));
                $output .= $this->displayConfirmation($this->l('Settings updated successfully'));
            } else {
                Configuration::updateValue('MB_SORT_DATE_CATEGORIES', '');
                $output .= $this->displayConfirmation($this->l('Settings updated successfully'));
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Display configuration form
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Get all categories
        $categories = Category::getCategories($default_lang, true, false);
        
        // Format categories for tree select
        $category_tree = $this->formatCategoryTree($categories);

        // Get selected categories
        $selected_categories = json_decode(Configuration::get('MB_SORT_DATE_CATEGORIES'), true);
        if (!is_array($selected_categories)) {
            $selected_categories = array();
        }

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'categories',
                        'label' => $this->l('Select Categories'),
                        'name' => 'MB_SORT_DATE_CATEGORIES',
                        'desc' => $this->l('Select categories where you want to enable sort by availability date'),
                        'tree' => array(
                            'id' => 'categories-tree',
                            'selected_categories' => $selected_categories,
                            'disabled_categories' => array(),
                            'root_category' => Category::getRootCategory()->id,
                            'use_checkbox' => true,
                            'use_search' => true
                        )
                    ),
                    array(
                        'type' => 'html',
                        'name' => 'sort_info',
                        'html_content' => '<div class="alert alert-info">' .
                            $this->l('This module adds two sort options:') . '<br>' .
                            '• ' . $this->l('Availability Date: Oldest first (ascending)') . '<br>' .
                            '• ' . $this->l('Availability Date: Newest first (descending)') .
                            '</div>'
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                )
            )
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMbSortDateConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    /**
     * Format category tree
     */
    protected function formatCategoryTree($categories, $id_parent = 1)
    {
        $result = array();
        
        if (is_array($categories) && isset($categories[$id_parent])) {
            foreach ($categories[$id_parent] as $category) {
                if (is_array($category) && isset($category['id_category']) && isset($category['name'])) {
                    $result[] = array(
                        'id' => $category['id_category'],
                        'name' => $category['name'],
                        'children' => $this->formatCategoryTree($categories, $category['id_category'])
                    );
                }
            }
        }
        
        return $result;
    }

    /**
     * Get configuration form values
     */
    protected function getConfigFormValues()
    {
        $selected_categories = json_decode(Configuration::get('MB_SORT_DATE_CATEGORIES'), true);
        if (!is_array($selected_categories)) {
            $selected_categories = array();
        }

        return array(
            'MB_SORT_DATE_CATEGORIES[]' => $selected_categories,
        );
    }

    /**
     * Hook: Add sort options to search
     */
    public function hookActionSearch($params)
    {
        // Check if we're in a category that has sort enabled
        if (!$this->isSortEnabledForCurrentCategory()) {
            return;
        }

        // Validate params
        if (!isset($params['searchVariables']['result']) || !$params['searchVariables']['result']) {
            return;
        }

        $searchResult = $params['searchVariables']['result'];

        // Add custom sort options
        $availableSortOrders = $searchResult->getAvailableSortOrders();
        
        // Add ascending sort by availability date
        $availableSortOrders[] = new PrestaShop\PrestaShop\Core\Product\Search\SortOrder(
            'product',
            'available_date',
            'asc',
            'Date de disponibilité: Plus ancienne en premier'
        );
        
        // Add descending sort by availability date
        $availableSortOrders[] = new PrestaShop\PrestaShop\Core\Product\Search\SortOrder(
            'product',
            'available_date',
            'desc',
            'Date de disponibilité: Plus récente en premier'
        );
        
        $searchResult->setAvailableSortOrders($availableSortOrders);
    }

    /**
     * Hook: Modify query before execution to fix table alias
     */
    public function hookActionProductSearchProviderRunQueryBefore($params)
    {
        // Check if we're in a category that has sort enabled
        if (!$this->isSortEnabledForCurrentCategory()) {
            return;
        }

        // Check if query exists
        if (!isset($params['query'])) {
            return;
        }

        $query = $params['query'];
        $sortOrder = $query->getSortOrder();
        
        if ($sortOrder && $sortOrder->toLegacyOrderBy(false) === 'available_date') {
            // Change the sort order to use proper table alias
            $newSortOrder = new PrestaShop\PrestaShop\Core\Product\Search\SortOrder(
                'p',
                'available_date',
                $sortOrder->toLegacyOrderWay(),
                $sortOrder->getLabel()
            );
            $query->setSortOrder($newSortOrder);
        }
    }

    /**
     * Hook: Add custom CSS if needed
     */
    public function hookDisplayHeader()
    {
        // Add any custom CSS or JS if needed
    }

    /**
     * Check if sort is enabled for current category
     */
    protected function isSortEnabledForCurrentCategory()
    {
        $selected_categories = json_decode(Configuration::get('MB_SORT_DATE_CATEGORIES'), true);
        
        if (!is_array($selected_categories) || empty($selected_categories)) {
            return false;
        }

        // Get current category ID
        $id_category = (int)Tools::getValue('id_category');
        
        if (!$id_category && isset($this->context->controller->category)) {
            $id_category = (int)$this->context->controller->category->id;
        }

        if (!$id_category) {
            return false;
        }

        // Check if current category is in selected categories
        return in_array($id_category, $selected_categories);
    }
}
