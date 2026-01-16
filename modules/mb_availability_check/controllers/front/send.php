<?php

class Mb_Availability_CheckSendModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        $this->processRequest();
    }

    private function processRequest()
    {
        header('Content-Type: application/json; charset=utf-8');

        PrestaShopLogger::addLog(
            'mb_availability_check: Request received: ' . json_encode($_POST),
            1
        );

        if (!$this->module) {
            $this->returnError('Module not initialized.');
        }

        if (!Tools::getValue('id_product')) {
            PrestaShopLogger::addLog('mb_availability_check: Missing id_product', 2);
            $this->returnError('Missing product.');
        }

        $idProduct = (int) Tools::getValue('id_product');
        $qty = (int) Tools::getValue('qty');
        $firstname = trim(Tools::getValue('firstname'));
        $lastname  = trim(Tools::getValue('lastname'));
        $email     = trim(Tools::getValue('email'));
        $phone     = trim(Tools::getValue('phone'));

        // Firstname, lastname and email are optional now — only validate when provided
        if ($firstname !== '' && !Validate::isName($firstname)) {
            PrestaShopLogger::addLog('mb_availability_check: Invalid firstname', 2);
            $this->returnError('Invalid firstname.');
        }
        if ($lastname !== '' && !Validate::isName($lastname)) {
            PrestaShopLogger::addLog('mb_availability_check: Invalid lastname', 2);
            $this->returnError('Invalid lastname.');
        }
        if ($email !== '' && !Validate::isEmail($email)) {
            PrestaShopLogger::addLog('mb_availability_check: Invalid email: ' . $email, 2);
            $this->returnError('Invalid email.');
        }

        // Qty is required
        if ($qty <= 0) {
            PrestaShopLogger::addLog('mb_availability_check: Invalid qty: ' . $qty, 2);
            $this->returnError('Invalid quantity.');
        }

        $token = Tools::passwdGen(32);
        $now   = date('Y-m-d H:i:s');

        // --- SQL INSERT ---
        $sqlInsert = sprintf(
            "INSERT INTO `%smb_availability_requests`
            (`id_product`, `qty`, `firstname`, `lastname`, `email`, `phone`, `token`, `date_add`)
            VALUES (%d, %d, '%s', '%s', '%s', '%s', '%s', '%s')",
            _DB_PREFIX_,
            (int)$idProduct,
            (int)$qty,
            pSQL($firstname),
            pSQL($lastname),
            pSQL($email),
            pSQL($phone),
            pSQL($token),
            pSQL($now)
        );

        try {
            $insertResult = Db::getInstance()->execute($sqlInsert);
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'mb_availability_check: SQL exception: ' . $e->getMessage() . ' -- SQL: ' . $sqlInsert,
                3
            );
            $this->returnError('Could not save request.');
        }

        if (!$insertResult) {
            PrestaShopLogger::addLog(
                'mb_availability_check: DB insert failed: ' . Db::getInstance()->getMsgError()
                    . ' -- SQL: ' . $sqlInsert,
                3
            );
            $this->returnError('Could not save request.');
        }

        PrestaShopLogger::addLog(
            'mb_availability_check: Request saved for product ' . $idProduct,
            1
        );

        // --- GENERATE ADD-TO-CART LINK ---
        $link = $this->context->link->getModuleLink(
            $this->module->name,
            'addtocart',
            ['token' => $token],
            true
        );

        $product = new Product($idProduct, false, $this->context->language->id);

        $templateVars = [
            '{firstname}'     => $firstname,
            '{lastname}'      => $lastname,
            '{email}'         => $email,
            '{phone}'         => $phone,
            '{product_name}'  => $product->name,
            '{product_id}'    => $idProduct,
            '{add_link}'      => $link,
        ];

        // No email sending or notification queuing: we only save the request in DB
        $idRequest = (int) Db::getInstance()->Insert_ID();

        PrestaShopLogger::addLog('mb_availability_check: Request saved (id_request=' . $idRequest . ', product=' . $idProduct . ', email=' . $email . ')', 1);

        // Send notification email if enabled
        if (Configuration::get('MB_AVAILABILITY_SEND_EMAIL')) {
            $this->sendNotificationEmail($idRequest, $idProduct, $qty, $firstname, $lastname, $email, $phone, $link);
        }

        // Return success to the frontend — only persisted to database
        $this->returnSuccess('Votre demande a été enregistrée. Nous vous contacterons dès que le produit sera disponible.');
    }

    private function returnError($message)
    {
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'message' => $message,
        ]));
    }

    private function returnSuccess($message)
    {
        http_response_code(200);
        die(json_encode([
            'success' => true,
            'message' => $message,
        ]));
    }

    private function sendNotificationEmail($idRequest, $idProduct, $qty, $firstname, $lastname, $customerEmail, $phone, $link)
    {
        try {
            $notificationEmail = Configuration::get('MB_AVAILABILITY_EMAIL');
            if (empty($notificationEmail)) {
                $notificationEmail = Configuration::get('PS_SHOP_EMAIL');
            }

            if (empty($notificationEmail)) {
                PrestaShopLogger::addLog('mb_availability_check: No notification email configured', 2);
                return;
            }

            $product = new Product($idProduct, false, $this->context->language->id);

            $subject = sprintf(
                $this->module->l('New availability request for %s', 'send'),
                $product->name
            );

            $templateVars = [
                '{firstname}' => $firstname,
                '{lastname}' => $lastname,
                '{customer_email}' => $customerEmail,
                '{phone}' => $phone,
                '{product_name}' => $product->name,
                '{product_id}' => $idProduct,
                '{quantity}' => $qty,
                '{request_id}' => $idRequest,
                '{add_link}' => $link,
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_url}' => $this->context->link->getPageLink('index', true),
            ];

            $templateContent = $this->getEmailTemplateContent();

            // Replace variables in template
            foreach ($templateVars as $key => $value) {
                $templateContent = str_replace($key, $value, $templateContent);
            }

            $mailSent = Mail::Send(
                $this->context->language->id,
                'availability_request', // template name
                $subject,
                $templateVars,
                $notificationEmail,
                null, // to_name
                null, // from
                null, // from_name
                null, // file_attachment
                null, // mode_smtp
                dirname(__FILE__) . '/../../mails/' // template_path
            );

            if ($mailSent) {
                PrestaShopLogger::addLog('mb_availability_check: Notification email sent to ' . $notificationEmail . ' for request ' . $idRequest, 1);
            } else {
                PrestaShopLogger::addLog('mb_availability_check: Failed to send notification email for request ' . $idRequest, 2);
            }

        } catch (Exception $e) {
            PrestaShopLogger::addLog('mb_availability_check: Error sending notification email: ' . $e->getMessage(), 3);
        }
    }

    private function getEmailTemplateContent()
    {
        return '
Bonjour,

Une nouvelle demande de disponibilité a été soumise :

Client : {firstname} {lastname}
Email : {customer_email}
Téléphone : {phone}

Produit demandé : {product_name} (ID: {product_id})
Quantité souhaitée : {quantity}

Lien pour ajouter au panier : {add_link}

Cordialement,
{shop_name}
{shop_url}
        ';
    }
}
