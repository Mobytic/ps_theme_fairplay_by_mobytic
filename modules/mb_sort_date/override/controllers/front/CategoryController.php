<?php
/**
 * Override for CategoryController to add availability date sorting
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

class CategoryController extends CategoryControllerCore
{
    /**
     * Get default sort orders with availability date options
     */
    protected function getProductSearchQuery()
    {
        $query = parent::getProductSearchQuery();
        
        // Check if mb_sort_date module is installed and active
        if (!Module::isInstalled('mb_sort_date') || !Module::isEnabled('mb_sort_date')) {
            return $query;
        }

        // Get module instance
        $module = Module::getInstanceByName('mb_sort_date');
        
        if (!$module) {
            return $query;
        }

        // Check if sort is enabled for current category
        $selected_categories = json_decode(Configuration::get('MB_SORT_DATE_CATEGORIES'), true);
        
        if (!is_array($selected_categories) || empty($selected_categories)) {
            return $query;
        }

        $id_category = (int)Tools::getValue('id_category');
        if (!$id_category && isset($this->category)) {
            $id_category = (int)$this->category->id;
        }

        if (!in_array($id_category, $selected_categories)) {
            return $query;
        }

        return $query;
    }

    /**
     * Get available sort orders
     */
    protected function getDefaultProductSearchProvider()
    {
        $provider = parent::getDefaultProductSearchProvider();
        
        // The actual sort options will be added through the module hooks
        
        return $provider;
    }
}
