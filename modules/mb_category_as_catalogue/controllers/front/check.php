<?php
class mb_category_as_catalogueCheckModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $id_product = (int)Tools::getValue('id_product');
        $blocked = 0;
        if ($id_product) {
            $product = new Product($id_product);
            $cats = $product->getCategories();
            $blockedCats = json_decode(Configuration::get(mb_category_as_catalogue::CONFIG_KEY), true) ?: [];
            $blocked = !empty(array_intersect($cats, $blockedCats)) ? 1 : 0;
        }
        header('Content-Type: application/json');
        echo json_encode(['blocked' => (int)$blocked]);
        exit;
    }
}
