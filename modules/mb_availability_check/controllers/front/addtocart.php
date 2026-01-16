<?php

class Mb_Availability_CheckAddtocartModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $token = Tools::getValue('token');
        if (!$token) {
            Tools::redirect('index.php'); // or 404
        }

        $row = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'mb_availability_requests`
            WHERE `token` = "'.pSQL($token).'"');

        if (!$row) {
            Tools::redirect('index.php');
        }

        $idProduct = (int) $row['id_product'];
        $qty = isset($row['qty']) ? (int)$row['qty'] : 1;

        if (!$this->context->cart->id) {
            $this->context->cart = new Cart();
            $this->context->cart->id_currency = (int) $this->context->currency->id;
            $this->context->cart->id_lang = (int) $this->context->language->id;
            $this->context->cart->id_shop = (int) $this->context->shop->id;
            $this->context->cart->id_customer = (int) $this->context->customer->id;
            $this->context->cart->add();
            $this->context->cookie->id_cart = (int) $this->context->cart->id;
        }

        $added = $this->context->cart->updateQty(
            $qty,
            $idProduct,
            0,
            false,
            'up',
            0,
            new Shop($this->context->shop->id)
        );

        // Optional: mark token as used or delete row here if you want one-time links
        // Db::getInstance()->delete('mb_availability_requests', 'token = "'.pSQL($token).'"');

        // mark product as locked for quantity changes by storing in cookie
        try {
            $locked = [];
            if ($this->context->cookie->mb_locked_products) {
                $locked = @json_decode($this->context->cookie->mb_locked_products, true) ?: [];
            }
            $locked[$idProduct] = $qty;
            $this->context->cookie->mb_locked_products = json_encode($locked);
        } catch (Exception $e) {
            // ignore cookie errors
        }

        if ($added < 0) {
            // error codes etc.; just redirect to product
            Tools::redirect($this->context->link->getProductLink($idProduct));
        } else {
            // redirect to cart
            Tools::redirect($this->context->link->getPageLink('cart', true, null, ['action' => 'show']));
        }
    }
}
