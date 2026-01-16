<?php
/**
 * Override for CategoryProductSearchProvider to add availability date sorting
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

class CategoryProductSearchProvider extends CategoryProductSearchProviderCore
{
    /**
     * Get available sort orders including availability date
     */
    public function getAvailableSortOrders()
    {
        $sortOrders = parent::getAvailableSortOrders();
        
        // DEBUG: Log entry
        error_log('MB_SORT_DATE: getAvailableSortOrders called');
        
        // Check if mb_sort_date module is active
        if (!Module::isInstalled('mb_sort_date') || !Module::isEnabled('mb_sort_date')) {
            error_log('MB_SORT_DATE: Module not installed or not enabled');
            return $sortOrders;
        }
        
        error_log('MB_SORT_DATE: Module is active');

        // Get module instance
        $module = Module::getInstanceByName('mb_sort_date');
        if (!$module) {
            error_log('MB_SORT_DATE: Could not get module instance');
            return $sortOrders;
        }

        // Get selected categories from configuration
        $selected_categories = json_decode(Configuration::get('MB_SORT_DATE_CATEGORIES'), true);
        error_log('MB_SORT_DATE: Selected categories config: ' . Configuration::get('MB_SORT_DATE_CATEGORIES'));
        error_log('MB_SORT_DATE: Selected categories decoded: ' . print_r($selected_categories, true));
        
        if (!is_array($selected_categories) || empty($selected_categories)) {
            error_log('MB_SORT_DATE: No categories selected');
            return $sortOrders;
        }

        // Get current category ID
        $id_category = (int)Tools::getValue('id_category');
        error_log('MB_SORT_DATE: Category from URL: ' . $id_category);
        
        // Try to get from context if not in URL
        if (!$id_category) {
            $context = Context::getContext();
            if (isset($context->controller) && method_exists($context->controller, 'getCategory')) {
                $category = $context->controller->getCategory();
                if ($category) {
                    $id_category = (int)$category->id;
                    error_log('MB_SORT_DATE: Category from getCategory(): ' . $id_category);
                }
            } elseif (isset($context->controller->category)) {
                $id_category = (int)$context->controller->category->id;
                error_log('MB_SORT_DATE: Category from controller->category: ' . $id_category);
            }
        }
        
        error_log('MB_SORT_DATE: Final category ID: ' . $id_category);
        error_log('MB_SORT_DATE: Is in array? ' . (in_array($id_category, $selected_categories) ? 'YES' : 'NO'));

        // Check if current category is in selected categories
        if (!$id_category || !in_array($id_category, $selected_categories)) {
            error_log('MB_SORT_DATE: Category not in selected list or no category found');
            return $sortOrders;
        }

        error_log('MB_SORT_DATE: Adding sort orders!');
        
        // Add availability date sort orders
        $sortOrders[] = new SortOrder(
            'product',
            'available_date',
            'asc',
            $module->l('Availability Date: Oldest first')
        );
        
        $sortOrders[] = new SortOrder(
            'product',
            'available_date',
            'desc',
            $module->l('Availability Date: Newest first')
        );

        error_log('MB_SORT_DATE: Total sort orders: ' . count($sortOrders));
        
        return $sortOrders;
    }

    /**
     * Run product search query with availability date support
     */
    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $products = parent::runQuery($context, $query);
        
        // Check if we need to sort by availability date
        $sortOrder = $query->getSortOrder();
        
        if (!$sortOrder || $sortOrder->toLegacyOrderBy(false) !== 'available_date') {
            return $products;
        }

        // Check if mb_sort_date module is active
        if (!Module::isInstalled('mb_sort_date') || !Module::isEnabled('mb_sort_date')) {
            return $products;
        }

        // Re-sort products by availability date from stock table
        $productIds = array();
        foreach ($products as $product) {
            $productIds[] = (int)$product['id_product'];
        }

        if (empty($productIds)) {
            return $products;
        }

        // Get availability dates
        $sql = 'SELECT id_product, available_date 
                FROM ' . _DB_PREFIX_ . 'product
                WHERE id_product IN (' . implode(',', $productIds) . ')';
        
        $availabilityDates = array();
        $results = Db::getInstance()->executeS($sql);
        
        if ($results) {
            foreach ($results as $row) {
                $availabilityDates[$row['id_product']] = $row['available_date'];
            }
        }

        // Sort products array by availability date
        $orderWay = $sortOrder->toLegacyOrderWay();
        
        usort($products, function($a, $b) use ($availabilityDates, $orderWay) {
            $dateA = isset($availabilityDates[$a['id_product']]) ? $availabilityDates[$a['id_product']] : '0000-00-00';
            $dateB = isset($availabilityDates[$b['id_product']]) ? $availabilityDates[$b['id_product']] : '0000-00-00';
            
            if ($orderWay === 'asc') {
                return strcmp($dateA, $dateB);
            } else {
                return strcmp($dateB, $dateA);
            }
        });

        return $products;
    }
}
