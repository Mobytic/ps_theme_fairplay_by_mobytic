<?php
/**
 * Helper class for sort by date functionality
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SortByDate
{
    /**
     * Get products sorted by availability date
     * 
     * @param int $id_category Category ID
     * @param int $id_lang Language ID
     * @param int $p Page number
     * @param int $n Number of products per page
     * @param string $orderBy Order by field
     * @param string $orderWay Order way (ASC or DESC)
     * @return array Products
     */
    public static function getProductsByAvailabilityDate(
        $id_category,
        $id_lang,
        $p = 1,
        $n = 10,
        $orderBy = 'available_date',
        $orderWay = 'ASC'
    ) {
        $context = Context::getContext();
        
        if (!Validate::isOrderBy($orderBy) || !Validate::isOrderWay($orderWay)) {
            return array();
        }

        $sql = new DbQuery();
        $sql->select('p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity,
            pl.`description`, pl.`description_short`, pl.`link_rewrite`, pl.`meta_description`,
            pl.`meta_keywords`, pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`,
            image_shop.`id_image` id_image, il.`legend`, m.`name` AS manufacturer_name,
            cl.`name` AS category_default,
            DATEDIFF(p.`date_add`, DATE_SUB(NOW(), INTERVAL ' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY)) > 0 AS new,
            product_shop.`date_add` > "' . date('Y-m-d', strtotime('-' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY')) . '" AS new');
        
        $sql->from('product', 'p');
        $sql->join(Shop::addSqlAssociation('product', 'p'));
        $sql->leftJoin('product_lang', 'pl', 'p.`id_product` = pl.`id_product`
            AND pl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('pl'));
        $sql->leftJoin('category_lang', 'cl', 'product_shop.`id_category_default` = cl.`id_category`
            AND cl.`id_lang` = ' . (int)$id_lang . Shop::addSqlRestrictionOnLang('cl'));
        $sql->leftJoin('product_stock_available', 'stock', 'stock.`id_product` = p.`id_product`');
        $sql->leftJoin('image_shop', 'image_shop', 'image_shop.`id_product` = p.`id_product`
            AND image_shop.cover=1 AND image_shop.id_shop=' . (int)$context->shop->id);
        $sql->leftJoin('image_lang', 'il', 'image_shop.`id_image` = il.`id_image`
            AND il.`id_lang` = ' . (int)$id_lang);
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');
        $sql->leftJoin('category_product', 'cp', 'cp.`id_product` = p.`id_product`');

        $sql->where('cp.`id_category` = ' . (int)$id_category);
        $sql->where('product_shop.`active` = 1');
        $sql->where('product_shop.`visibility` IN ("both", "catalog")');

        $sql->groupBy('p.id_product');
        
        // Sort by availability date from stock table
        if ($orderBy === 'available_date') {
            $sql->orderBy('stock.`available_date` ' . pSQL($orderWay) . ', p.`id_product` ASC');
        } else {
            $sql->orderBy(pSQL($orderBy) . ' ' . pSQL($orderWay));
        }

        $sql->limit($n, ($p - 1) * $n);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Get total products count for category
     * 
     * @param int $id_category Category ID
     * @return int Product count
     */
    public static function getProductsCount($id_category)
    {
        $context = Context::getContext();
        
        $sql = new DbQuery();
        $sql->select('COUNT(DISTINCT p.`id_product`)');
        $sql->from('product', 'p');
        $sql->join(Shop::addSqlAssociation('product', 'p'));
        $sql->leftJoin('category_product', 'cp', 'cp.`id_product` = p.`id_product`');
        $sql->where('cp.`id_category` = ' . (int)$id_category);
        $sql->where('product_shop.`active` = 1');
        $sql->where('product_shop.`visibility` IN ("both", "catalog")');

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }
}
