<?php

class AdminMbAvailabilityRequestsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();

        // Ensure module model class is loaded (avoid autoload issues)
        if (!class_exists('MbAvailabilityRequest')) {
            require_once dirname(__FILE__) . '/../../classes/MbAvailabilityRequest.php';
        }

        $this->bootstrap = true;
        $this->table = 'mb_availability_requests';
        $this->className = 'MbAvailabilityRequest';
        $this->context = Context::getContext();
        $this->identifier = 'id_request';
        $this->deleted = false;
        $this->allow_export = true;
        // Disable clickable table rows - we will add explicit edit action so row clicks won't redirect
        $this->list_no_link = true;

        // Force default ordering to avoid unexpected orderBy parameters
        $this->_orderBy = 'a.id_request';
        $this->_defaultOrderBy = 'a.id_request';
        $this->_defaultOrderWay = 'DESC';
        $this->orderBy = 'a.id_request';
        $this->orderWay = 'DESC';

        $this->fields_list = [
            'id_request' => ['title' => $this->l('ID'), 'align' => 'center', 'width' => 30, 'orderby' => true, 'filter_key' => 'a!id_request'],
            'product_name' => ['title' => $this->l('Product'), 'callback' => 'printProductName', 'orderby' => true, 'filter_key' => 'pl!name'],
            'firstname' => ['title' => $this->l('First name'), 'orderby' => true, 'filter_key' => 'a!firstname'],
            'lastname' => ['title' => $this->l('Last name'), 'orderby' => true, 'filter_key' => 'a!lastname'],
            'email' => ['title' => $this->l('Email'), 'orderby' => true, 'filter_key' => 'a!email'],
            'qty' => ['title' => $this->l('Quantity'), 'align' => 'center', 'width' => 50, 'orderby' => true, 'filter_key' => 'a!qty'],
            'status' => ['title' => $this->l('Status'), 'callback' => 'printStatus', 'orderby' => true, 'search' => true, 'filter_key' => 'a!status'],
            'phone' => ['title' => $this->l('Phone'), 'orderby' => true, 'filter_key' => 'a!phone'],
            'date_add' => ['title' => $this->l('Date'), 'type' => 'datetime', 'orderby' => true, 'filter_key' => 'a!date_add'],
            'add_link' => ['title' => $this->l('Add-to-cart link'), 'callback' => 'printAddLink', 'search' => false, 'orderby' => false, 'filter_key' => 'a!token', 'width' => 460],
        ];

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Delete selected'),
                'icon' => 'icon-trash',
                'confirm' => $this->l('Delete selected items?')
            ]
        ];
        // add bulk action to mark link sent
        $this->bulk_actions['marklink'] = [
            'text' => $this->l('Mark link sent'),
            'icon' => 'icon-share',
        ];

        $this->addRowAction('resend');
        // Keep an explicit edit action, but disable whole-row click navigation so inline editing (status select) works
        $this->addRowAction('edit');
        $this->addRowAction('copy');
        // Quick action to mark that the add-to-cart link was sent
        $this->addRowAction('marklink');
        $this->addRowAction('delete');

        // Define the form used for editing a request in the BO
        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Edit availability request'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('First name'),
                    'name' => 'firstname',
                    'required' => false, // default: optional; made required only when editing
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Last name'),
                    'name' => 'lastname',
                    'required' => false, // default: optional; made required only when editing
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Email'),
                    'name' => 'email',
                    'required' => false, // default: optional; made required only when editing
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Phone'),
                    'name' => 'phone',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Quantity'),
                    'name' => 'qty',
                    'required' => true,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Status'),
                    'name' => 'status',
                    'options' => [
                        'query' => [
                            ['id' => 'Nouveau', 'name' => $this->l('Nouveau')],
                            ['id' => 'En cours', 'name' => $this->l('En cours')],
                            ['id' => 'Traité', 'name' => $this->l('Traité')],
                            ['id' => 'Refusé', 'name' => $this->l('Refusé')],
                        ],
                        'id' => 'id',
                        'name' => 'name'
                    ]
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Token'),
                    'name' => 'token',
                    'readonly' => true,
                    'disabled' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Product ID'),
                    'name' => 'id_product',
                    'readonly' => true,
                    'disabled' => true,
                ],
                [
                    'type' => 'datetime',
                    'label' => $this->l('Date'),
                    'name' => 'date_add',
                    'readonly' => true,
                    'disabled' => true,
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];
    }

    public function renderForm()
    {
        // Determine whether we are adding or editing
        // Prefer to detect add vs edit by checking loaded object; this is more reliable than GET params
        // Robust detection of add vs edit: check request params + loaded object
        $isAdd = false;
        try {
            $object = $this->loadObject(true);
        } catch (Exception $e) {
            $object = null;
        }
        if (Tools::getIsset('add' . $this->table) || Tools::getIsset('add' . $this->className) || Tools::getValue('action') === 'add' || Tools::isSubmit('submitAdd' . $this->table) || Tools::isSubmit('submitAdd' . $this->className)) {
            $isAdd = true;
        } elseif (empty($object) || empty($object->{$this->identifier})) {
            $isAdd = true;
        } else {
            $isAdd = false;
        }
        // Titles: add = 'Ajouter...', edit = 'Modifier...'
        if ($isAdd) {
            $this->fields_form['legend']['title'] = $this->l('Ajouter la demande de disponibilité');
            // Ensure personal fields remain optional when adding (they are default optional in constructor)
            foreach ($this->fields_form['input'] as $idx => $input) {
                if (isset($input['name']) && in_array($input['name'], ['firstname', 'lastname', 'email'])) {
                    $this->fields_form['input'][$idx]['required'] = false;
                }
            }

            // Remove token and date_add fields from the form during add (they are auto-generated)
            $fieldsToRemove = ['token', 'date_add'];
            foreach ($this->fields_form['input'] as $idx => $input) {
                if (isset($input['name']) && in_array($input['name'], $fieldsToRemove)) {
                    unset($this->fields_form['input'][$idx]);
                }
            }

            // Replace product id readonly field by a select allowing product choice
            // Build product list for select (limited to 2000 to avoid performance issues)
            $productsRaw = Product::getProducts($this->context->language->id, 0, 2000, 'name', 'ASC');
            $products = [];
            foreach ($productsRaw as $p) {
                $products[] = ['id' => (int)$p['id_product'], 'name' => $p['name']];
            }

            foreach ($this->fields_form['input'] as $idx => $input) {
                if (isset($input['name']) && $input['name'] === 'id_product') {
                    $this->fields_form['input'][$idx] = [
                        'type' => 'select',
                        'label' => $this->l('Product'),
                        'name' => 'id_product',
                        'required' => true,
                        'options' => [
                            'query' => $products,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ];
                    break;
                }
            }
        } else {
            // Edit: set title and ensure firstname/lastname/email are required
            $this->fields_form['legend']['title'] = $this->l('Modifier la demande de disponibilité');
            foreach ($this->fields_form['input'] as $idx => $input) {
                if (isset($input['name']) && in_array($input['name'], ['firstname', 'lastname', 'email'])) {
                    $this->fields_form['input'][$idx]['required'] = true;
                }
            }
            // Ensure id_product is readonly text (default in constructor)
            foreach ($this->fields_form['input'] as $idx => $input) {
                if (isset($input['name']) && $input['name'] === 'id_product') {
                    $this->fields_form['input'][$idx] = [
                        'type' => 'text',
                        'label' => $this->l('Product ID'),
                        'name' => 'id_product',
                        'readonly' => true,
                        'disabled' => true,
                    ];
                    break;
                }
            }
        }
        return parent::renderForm();
    }

    /**
     * Ensure token and default status are set when adding a new request from BO
     */
    public function processAdd()
    {
        // Ensure fields 'firstname', 'lastname', 'email' are treated as optional for Add (server-side validation)
        foreach ($this->fields_form['input'] as $idx => $input) {
            if (isset($input['name']) && in_array($input['name'], ['firstname', 'lastname', 'email'])) {
                $this->fields_form['input'][$idx]['required'] = false;
            }
        }

        // Provide default values for missing fields to avoid validation blocking in Add
        // Use shop settings for email fallback
        $defaultEmail = Configuration::get('PS_SHOP_EMAIL');
        if (empty($defaultEmail)) {
            $defaultEmail = 'no-reply@localhost';
        }
        if (empty(Tools::getValue('firstname'))) {
            $_POST['firstname'] = 'Client';
            $_REQUEST['firstname'] = 'Client';
        }
        if (empty(Tools::getValue('lastname'))) {
            $_POST['lastname'] = 'Client';
            $_REQUEST['lastname'] = 'Client';
        }
        if (empty(Tools::getValue('email'))) {
            $_POST['email'] = $defaultEmail;
            $_REQUEST['email'] = $defaultEmail;
        }
        // Always generate a secure token for new requests
        $token = Tools::passwdGen(32);
        $_POST['token'] = $token;
        $_REQUEST['token'] = $token;
        
        // Default status to Nouveau if empty
        if (empty(Tools::getValue('status'))) {
            $_POST['status'] = 'Nouveau';
            $_REQUEST['status'] = 'Nouveau';
        }

        return parent::processAdd();
    }

    public function getList($id_lang, $orderBy = null, $orderWay = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        // Join product_lang to allow sorting by product name and include product name in select
        $idLang = (int) $id_lang ?: (int) $this->context->language->id;
        $this->_join = ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (pl.id_product = a.id_product AND pl.id_lang = ' . $idLang . ') ';
        // Include product name and token; alias token as add_link so callback receives it directly
        $this->_select = "pl.name AS product_name, a.token AS add_link";

        // Define allowed columns and map them to SQL columns to avoid SQL injection from 'orderBy' param
        $allowedOrder = [
            'id_request' => 'a.id_request',
            'a.id_request' => 'a.id_request',
            'a!id_request' => 'a.id_request',
            'product_name' => 'pl.name',
            'pl.name' => 'pl.name',
            'pl!name' => 'pl.name',
            'firstname' => 'a.firstname',
            'a.firstname' => 'a.firstname',
            'a!firstname' => 'a.firstname',
            'lastname' => 'a.lastname',
            'a.lastname' => 'a.lastname',
            'a!lastname' => 'a.lastname',
            'email' => 'a.email',
            'a.email' => 'a.email',
            'a!email' => 'a.email',
            'qty' => 'a.qty',
            'a.qty' => 'a.qty',
            'a!qty' => 'a.qty',
            'status' => 'a.status',
            'a.status' => 'a.status',
            'a!status' => 'a.status',
            'phone' => 'a.phone',
            'a.phone' => 'a.phone',
            'a!phone' => 'a.phone',
            'date_add' => 'a.date_add',
            'a.date_add' => 'a.date_add',
            'a!date_add' => 'a.date_add',
        ];

        // Safe default
        $this->_orderBy = 'a.id_request';
        $this->_orderWay = 'DESC';

        // If provided, sanitize and set orderBy/orderWay
        if ($orderBy) {
            // Allow characters used by PrestaShop order keys, including '.' and '!'
            $orderKey = preg_replace('/[^a-zA-Z0-9_\.\!]/', '', $orderBy);
            if (isset($allowedOrder[$orderKey])) {
                $this->_orderBy = $allowedOrder[$orderKey];
            }
        }
        if ($orderWay) {
            $orderW = strtoupper($orderWay);
            if (in_array($orderW, ['ASC', 'DESC'])) {
                $this->_orderWay = $orderW;
            }
        }

        parent::getList($id_lang, $orderBy, $orderWay, $start, $limit, $id_lang_shop);
    }

    public function printProductName($echo, $row)
    {
        // If product name is already in the SQL row (via join), use it to avoid extra DB queries
        if (!empty($row['product_name'])) {
            return htmlspecialchars($row['product_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $idProduct = (int) $row['id_product'];
        $product = new Product($idProduct, false, $this->context->language->id);
        return htmlspecialchars($product->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function printAddLink($token, $row)
    {
        // Prefer the token provided as the field value (aliased as add_link); fallback to row['token'] if needed
        $token = !empty($token) ? $token : (isset($row['token']) ? $row['token'] : '');
        if (!$token) {
            // No token available, show a placeholder
            return '<span class="text-muted">' . htmlspecialchars($this->l('No link'), ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</span>';
        }
        $link = $this->context->link->getModuleLink(
            $this->module->name,
            'addtocart',
            ['token' => $token],
            true
        );

        $html = ' <input type="text" readonly class="form-control mb-copy-url" style="display:inline-block;width:350px;margin-left:6px;" value="' . htmlspecialchars($link, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" />';

        return $html;
    }

    public function printStatus($status, $row)
    {
        $current = $status ?: 'Nouveau';
        $options = [
            'Nouveau',
            'Lien envoyé',
            'En cours',
            'Traité',
            'Refusé',
        ];

        $id = (int) $row['id_request'];

        $html = '<select class="mb-status-select" data-id="' . $id . '" style="min-width:120px;">';
        foreach ($options as $opt) {
            $sel = ($opt === $current) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($opt, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($opt, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    public function displayAjaxSetStatus()
    {
        // Direct AJAX handler for status updates
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/json');
        
        try {
            $idRequest = (int) Tools::getValue('id_request');
            $status = Tools::getValue('status');
            
            PrestaShopLogger::addLog('mb_availability_check: displayAjaxSetStatus called: id=' . $idRequest . ' status=' . $status, 1);
            
            if (!$this->access('edit')) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            
            if (!$idRequest || !$status) {
                echo json_encode(['success' => false, 'message' => 'Invalid request or status']);
                exit;
            }
            
            $updated = Db::getInstance()->update(
                $this->table,
                ['status' => pSQL($status)],
                'id_request = ' . $idRequest
            );
            
            echo json_encode(['success' => (bool)$updated]);
            PrestaShopLogger::addLog('mb_availability_check: displayAjaxSetStatus response: success=' . ($updated ? '1' : '0'), 1);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('mb_availability_check: displayAjaxSetStatus exception: ' . $e->getMessage(), 3);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    public function processSetstatusMbAvailabilityRequests()
    {
        // Accept AJAX POST or normal POST; respond with JSON when AJAX
        $idRequest = (int) Tools::getValue($this->identifier);
        $status = Tools::getValue('status');
        $isAjax = (bool) Tools::getIsset('ajax');
        // permission check
        if ($isAjax && !$this->access('edit')) {
            PrestaShopLogger::addLog('mb_availability_check: No permission to edit');
            while (ob_get_level()) { @ob_end_clean(); }
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        try {
        // If AJAX, clear any pre-existing output to prevent partial/invalid JSON responses
        if ($isAjax) {
            while (ob_get_level()) {
                @ob_end_clean();
            }
        }
        PrestaShopLogger::addLog('mb_availability_check: processSetstatusMbAvailabilityRequests called: id=' . $idRequest . ' status=' . $status . ' ajax=' . ($isAjax ? '1' : '0'), 1);
        // Log POST for debug
        PrestaShopLogger::addLog('mb_availability_check: _POST: ' . substr(var_export($_POST, true), 0, 1000), 1);

        if (!$idRequest || !$status) {
            PrestaShopLogger::addLog('mb_availability_check: Invalid request or status: ' . var_export(['id' => $idRequest, 'status' => $status], true), 2);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $this->l('Invalid request or status')]);
                PrestaShopLogger::addLog('mb_availability_check: sending JSON error invalid request', 1);
                exit;
            }
            $this->errors[] = $this->l('Invalid request or status');
            return;
        }

        try {
            $updated = Db::getInstance()->update(
                $this->table,
                ['status' => pSQL($status)],
                'id_request = ' . $idRequest
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog('mb_availability_check: Exception while updating status for id ' . $idRequest . ': ' . $e->getMessage(), 3);
            $updated = false;
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
                PrestaShopLogger::addLog('mb_availability_check: sending JSON exception: ' . $e->getMessage(), 3);
                exit;
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            $payload = json_encode(['success' => (bool)$updated]);
            echo $payload;
            PrestaShopLogger::addLog('mb_availability_check: processSetstatusMbAvailabilityRequests response payload: ' . $payload, 1);
            PrestaShopLogger::addLog('mb_availability_check: processSetstatusMbAvailabilityRequests response: success=' . ($updated ? '1' : '0') . ' id=' . $idRequest, 1);
            exit;
        }

        if ($updated) {
            Tools::redirectAdmin(self::$currentIndex . '&conf=4&token=' . $this->token);
        } else {
            $this->errors[] = $this->l('Could not update status');
        }
        } catch (Exception $e) {
            // If anything unexpected happens, log it and return JSON if AJAX
            PrestaShopLogger::addLog('mb_availability_check: Unhandled exception in processSetstatusMbAvailabilityRequests: ' . $e->getMessage(), 3);
            if ($isAjax) {
                while (ob_get_level()) { @ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
                exit;
            }
            throw $e;
        }
    }

    public function displayResendLink($token = null, $id_request)
    {
        $this->context->smarty->assign([
            'href' => 'javascript:void(0);',
            'action' => $this->l('Send'),
            'icon' => 'icon-envelope',
            'class' => 'mb-send-action',
            'id' => $id_request
        ]);

        return $this->context->smarty->fetch('module:' . $this->module->name . '/views/templates/admin/list_action_default.tpl');
    }

    public function displayCopyLink($token = null, $id_request)
    {
        $this->context->smarty->assign([
            'href' => 'javascript:void(0);',
            'action' => $this->l('Copy URL'),
            'icon' => 'icon-copy',
            'class' => 'mb-copy-action',
            'id' => $id_request
        ]);

        return $this->context->smarty->fetch('module:' . $this->module->name . '/views/templates/admin/list_action_default.tpl');
    }

    public function displayMarkLink($token = null, $id_request)
    {
        $this->context->smarty->assign([
            'href' => self::$currentIndex .
                '&' . $this->identifier . '=' . $id_request .
                '&marklink' . $this->table .
                '&token=' . $this->token,
            'action' => $this->l('Lien envoyé'),
            'icon' => 'icon-share'
        ]);

        return $this->context->smarty->fetch('module:' . $this->module->name . '/views/templates/admin/list_action_default.tpl');
    }

    public function displayDelete($token = null, $id_request)
    {
        $this->context->smarty->assign([
            'href' => self::$currentIndex .
                '&' . $this->identifier . '=' . $id_request .
                '&delete' . $this->table .
                '&token=' . $this->token,
            'action' => $this->l('Delete'),
            'icon' => 'icon-trash'
        ]);

        return $this->context->smarty->fetch('module:' . $this->module->name . '/views/templates/admin/list_action_default.tpl');
    }

    public function processMarklinkMbAvailabilityRequests()
    {
        if (!$this->access('edit')) {
            $this->errors[] = $this->l('You do not have permission to edit this.');
            return;
        }

        $idRequest = (int) Tools::getValue($this->identifier);
        if (!$idRequest) {
            $this->errors[] = $this->l('Invalid request');
            return;
        }

        $updated = Db::getInstance()->update(
            $this->table,
            ['status' => pSQL('Lien envoyé')],
            'id_request = ' . $idRequest
        );

        if ($updated) {
            Tools::redirectAdmin(self::$currentIndex . '&conf=4&token=' . $this->token);
        } else {
            $this->errors[] = $this->l('Could not update status');
        }
    }

    public function processBulkMarklink()
    {
        if ($this->access('edit')) {
            if (is_array($this->boxes) && !empty($this->boxes)) {
                $success = true;
                foreach ($this->boxes as $id) {
                    $success = $success && Db::getInstance()->update($this->table, ['status' => pSQL('Lien envoyé')], 'id_request = ' . (int)$id);
                }
                if ($success) {
                    $this->confirmations[] = $this->l('Successfully updated status');
                } else {
                    $this->errors[] = $this->l('An error occurred during update');
                }
            } else {
                $this->errors[] = $this->l('You must select at least one element to update.');
            }
        } else {
            $this->errors[] = $this->l('You do not have permission to edit this.');
        }
    }

    public function processBulkDelete()
    {
        if ($this->access('delete')) {
            if (is_array($this->boxes) && !empty($this->boxes)) {
                $success = true;
                foreach ($this->boxes as $id) {
                    $success = $success && Db::getInstance()->delete($this->table, 'id_request = ' . (int)$id);
                }
                if ($success) {
                    $this->confirmations[] = $this->l('Successfully deleted');
                } else {
                    $this->errors[] = $this->l('An error occurred during deletion');
                }
            } else {
                $this->errors[] = $this->l('You must select at least one element to delete.');
            }
        } else {
            $this->errors[] = $this->l('You do not have permission to delete this.');
        }
    }

    public function processDelete()
    {
        if ($this->access('delete')) {
            $idRequest = (int) Tools::getValue($this->identifier);
            if ($idRequest) {
                if (Db::getInstance()->delete($this->table, 'id_request = ' . $idRequest)) {
                    Tools::redirectAdmin(self::$currentIndex . '&conf=1&token=' . $this->token);
                } else {
                    $this->errors[] = $this->l('An error occurred during deletion');
                }
            }
        } else {
            $this->errors[] = $this->l('You do not have permission to delete this.');
        }
    }

    public function displayAjaxResendMbAvailabilityRequests()
    {
        PrestaShopLogger::addLog('mb_availability_check: displayAjaxResendMbAvailabilityRequests called', 1);

        if (!$this->access('edit')) {
            if (true) {
                if (ob_get_length()) { @ob_clean(); }
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $this->l('You do not have permission to edit this.')]);
                exit;
            }
            $this->errors[] = $this->l('You do not have permission to edit this.');
            return;
        }

        $idRequest = (int) Tools::getValue($this->identifier);
        PrestaShopLogger::addLog('mb_availability_check: Processing request ID: ' . $idRequest, 1);

        $clientResponse = Tools::getValue('client_response', 'ok');
        $customMessage = Tools::getValue('custom_message', '');
        $notificationEmail = Tools::getValue('notification_email', '');

        $sendToUser = ($clientResponse === 'ok');

        $request = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . $this->table . '` WHERE id_request = ' . $idRequest);
        if (!$request) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $this->l('Request not found')]);
            exit;
        }

        try {
            $link = $this->context->link->getModuleLink(
                $this->module->name,
                'addtocart',
                ['token' => $request['token']],
                true
            );

            $product = new Product((int)$request['id_product'], false, $this->context->language->id);

            $templateVars = [
                '{firstname}'   => $request['firstname'],
                '{lastname}'    => $request['lastname'],
                '{customer_email}' => $request['email'],
                '{phone}'       => $request['phone'],
                '{product_name}' => $product->name,
                '{product_id}'   => $request['id_product'],
                '{quantity}'     => $request['qty'],
                '{request_id}'   => $idRequest,
                '{add_link}'     => $link,
                '{shop_name}'    => Configuration::get('PS_SHOP_NAME'),
                '{shop_url}'     => $this->context->link->getPageLink('index', true),
                '{custom_message}' => nl2br(htmlspecialchars($customMessage)),
            ];

            $notifyEmail = $notificationEmail ?: Configuration::get('MB_AVAILABILITY_EMAIL');
            if (!$notifyEmail) {
                $notifyEmail = Configuration::get('PS_SHOP_EMAIL');
            }

            if (!$notifyEmail) {
                $errorMsg = $this->l('No notification email configured');
                PrestaShopLogger::addLog('mb_availability_check: Send failed - no notification email configured', 2);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            }

            // Log send attempt
            PrestaShopLogger::addLog('mb_availability_check: Sending request ' . $idRequest . ' to ' . $notifyEmail, 1);

            $idLang = (int) $this->context->language->id;
            $idShop = (int) $this->context->shop->id;

            $templatePath = dirname(__FILE__) . '/../../mails/';
            PrestaShopLogger::addLog('mb_availability_check: Template path: ' . $templatePath, 1);

            $subject = sprintf($this->module->l('New availability request for %s'), $product->name);
            if ($clientResponse === 'no') {
                $subject = $this->module->l('Availability request notification');
            }

            $emailsSent = 0;
            $errors = [];

            // Send to notification email (admin/shop)
            if ($notifyEmail) {
                PrestaShopLogger::addLog('mb_availability_check: Sending to notification email ' . $notifyEmail . ' for request ' . $idRequest, 1);

                $send = Mail::Send(
                    $idLang,
                    'availability_request',
                    $subject,
                    $templateVars,
                    $notifyEmail,
                    null, // to_name
                    null, // from
                    null, // from_name
                    null, // file_attachment
                    null, // mode_smtp
                    $templatePath
                );

                PrestaShopLogger::addLog('mb_availability_check: Mail::Send returned: ' . ($send ? 'true' : 'false') . ' for notification email', 1);

                if ($send) {
                    $emailsSent++;
                    PrestaShopLogger::addLog('mb_availability_check: Successfully sent to notification email ' . $notifyEmail . ' for request ' . $idRequest, 1);
                } else {
                    $errors[] = $this->l('Failed to send to notification email');
                    PrestaShopLogger::addLog('mb_availability_check: Failed to send to notification email ' . $notifyEmail . ' for request ' . $idRequest, 2);
                }
            }

            // Send to customer email if requested
            if ($sendToUser && !empty($request['email'])) {
                PrestaShopLogger::addLog('mb_availability_check: Sending to customer email ' . $request['email'] . ' for request ' . $idRequest, 1);

                $customerSubject = $this->module->l('Your availability request has been processed');
                if ($clientResponse === 'no') {
                    $customerSubject = $this->module->l('Availability notification');
                }

                $sendToCustomer = Mail::Send(
                    $idLang,
                    'availability_request',
                    $customerSubject,
                    $templateVars,
                    $request['email'],
                    $request['firstname'] . ' ' . $request['lastname'], // to_name
                    null, // from
                    null, // from_name
                    null, // file_attachment
                    null, // mode_smtp
                    $templatePath
                );

                if ($sendToCustomer) {
                    $emailsSent++;
                    PrestaShopLogger::addLog('mb_availability_check: Successfully sent to customer email ' . $request['email'] . ' for request ' . $idRequest, 1);
                } else {
                    $errors[] = $this->l('Failed to send to customer email');
                    PrestaShopLogger::addLog('mb_availability_check: Failed to send to customer email ' . $request['email'] . ' for request ' . $idRequest, 2);
                }
            }

            if ($emailsSent > 0) {
                // mark as link sent
                Db::getInstance()->update($this->table, ['status' => pSQL('Lien envoyé')], 'id_request = ' . $idRequest);
                PrestaShopLogger::addLog('mb_availability_check: Send successful for request ' . $idRequest . ' (' . $emailsSent . ' emails sent)', 1);

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $this->l('Email sent successfully')]);
                exit;
            } else {
                PrestaShopLogger::addLog('mb_availability_check: Send failed for request ' . $idRequest . ' - no emails sent successfully', 2);
                $errorMsg = $this->l('Could not send email. Check your email configuration.');
                if (!empty($errors)) {
                    $errorMsg .= ' ' . implode(', ', $errors);
                }

                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('mb_availability_check: Send error for request ' . $idRequest . ': ' . $e->getMessage(), 3);
            $errorMsg = $this->l('Error sending email:') . ' ' . $e->getMessage();

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMsg]);
            exit;
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        
        $this->addJS(_PS_MODULE_DIR_ . $this->module->name . '/views/js/admin_requests.js');
    }

    public function renderList()
    {
        $output = parent::renderList();

        // Wrap the list inside a module-specific container so we can target it from JS to prevent row clicks
        // Provide admin URL and token explicitly so JS can reliably post AJAX back to this controller
        $adminUrl = self::$currentIndex . '&token=' . $this->token;
        $output = '<div class="mb-availability-requests-wrapper" data-admin-url="' . htmlspecialchars($adminUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">' . $output . '</div>';

        // Add the send modal
        $output .= $this->renderSendModal();

        return $output;
    }

    public function renderSendModal()
    {
        $notifyEmail = Configuration::get('MB_AVAILABILITY_EMAIL');
        if (!$notifyEmail) {
            $notifyEmail = Configuration::get('PS_SHOP_EMAIL');
        }

        $this->context->smarty->assign([
            'send_modal_title' => $this->l('Send availability request notification'),
            'send_modal_client_response_label' => $this->l('Client response'),
            'send_modal_client_ok_label' => $this->l('Client OK: Send the link to customer'),
            'send_modal_client_no_label' => $this->l('Client No: Custom message'),
            'send_modal_message_label' => $this->l('Custom message'),
            'send_modal_notification_email_label' => $this->l('Notification email'),
            'send_modal_default_notification_email' => $notifyEmail,
            'send_modal_notification_email_placeholder' => $this->l('Enter email address'),
            'send_modal_notification_email_help' => $this->l('Leave empty to use default shop email'),
            'send_modal_send_button' => $this->l('Send'),
            'send_modal_cancel_button' => $this->l('Cancel'),
            'send_modal_preview_button' => $this->l('Preview'),
            'send_modal_preview_title' => $this->l('Email Preview'),
            'send_modal_loading_preview' => $this->l('Loading preview...'),
            'send_modal_close_button' => $this->l('Close'),
            'send_modal_placeholder' => $this->l('Add a custom message to include in the email...'),
            'preview_modal_title' => $this->l('Email Preview'),
            'preview_modal_close_button' => $this->l('Close'),
        ]);

        return $this->context->smarty->fetch('module:' . $this->module->name . '/views/templates/admin/send_modal.tpl');
    }

    public function displayAjaxPreviewEmail()
    {
        PrestaShopLogger::addLog('mb_availability_check: displayAjaxPreviewEmail called', 1);

        if (!$this->access('view')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        $idRequest = (int) Tools::getValue('id_request');
        $clientResponse = Tools::getValue('client_response', 'ok');
        $customMessage = ($clientResponse === 'no') ? Tools::getValue('custom_message', '') : '';
        $notificationEmail = Tools::getValue('notification_email', '');

        if (!$idRequest) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
            exit;
        }

        $request = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . $this->table . '` WHERE id_request = ' . $idRequest);
        if (!$request) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Request not found']);
            exit;
        }

        try {
            $link = $this->context->link->getModuleLink(
                $this->module->name,
                'addtocart',
                ['token' => $request['token']],
                true
            );

            $product = new Product((int)$request['id_product'], false, $this->context->language->id);

            $templateVars = [
                '{firstname}'   => $request['firstname'],
                '{lastname}'    => $request['lastname'],
                '{customer_email}' => $request['email'],
                '{phone}'       => $request['phone'],
                '{product_name}' => $product->name,
                '{product_id}'   => $request['id_product'],
                '{quantity}'     => $request['qty'],
                '{request_id}'   => $idRequest,
                '{add_link}'     => $link,
                '{shop_name}'    => Configuration::get('PS_SHOP_NAME'),
                '{shop_url}'     => $this->context->link->getPageLink('index', true),
                '{custom_message}' => nl2br(htmlspecialchars($customMessage)),
            ];

            $subject = sprintf($this->module->l('New availability request for %s'), $product->name);
            if ($clientResponse === 'no') {
                $subject = $this->module->l('Availability request notification');
            }

            // Manually render templates
            $templatePath = dirname(__FILE__) . '/../../mails/';

            // Read and render HTML template
            $htmlTemplatePath = $templatePath . $this->context->language->iso_code . '/availability_request.html';
            if (!file_exists($htmlTemplatePath)) {
                $htmlTemplatePath = $templatePath . 'en/availability_request.html';
            }

            $htmlContent = '<p>Template not found</p>';
            if (file_exists($htmlTemplatePath)) {
            $htmlContent = file_get_contents($htmlTemplatePath);

            // Handle conditional custom message display
            if (!empty($customMessage)) {
                $customMessageHtml = "\n    <p><strong>Message personnalisé :</strong></p>\n    <p>" . nl2br(htmlspecialchars($customMessage)) . "</p>\n    ";
                $htmlContent = str_replace("{if \$custom_message}\n    <p><strong>Message personnalisé :</strong></p>\n    <p>{custom_message}</p>\n    {/if}", $customMessageHtml, $htmlContent);
            } else {
                $htmlContent = str_replace("{if \$custom_message}\n    <p><strong>Message personnalisé :</strong></p>\n    <p>{custom_message}</p>\n    {/if}", "", $htmlContent);
            }                // Replace template variables
                foreach ($templateVars as $key => $value) {
                    $htmlContent = str_replace($key, $value, $htmlContent);
                }
            }

            // Read and render text template
            $txtTemplatePath = $templatePath . $this->context->language->iso_code . '/availability_request.txt';
            if (!file_exists($txtTemplatePath)) {
                $txtTemplatePath = $templatePath . 'en/availability_request.txt';
            }

            $txtContent = 'Template not found';
            if (file_exists($txtTemplatePath)) {
            $txtContent = file_get_contents($txtTemplatePath);

            // Handle conditional custom message display for text version
            if (!empty($customMessage)) {
                $customMessageText = "\n\nMessage personnalisé :\n" . $customMessage . "\n\n";
                $txtContent = str_replace("{if \$custom_message}\nMessage personnalisé :\n{custom_message}\n\n{/if}", $customMessageText, $txtContent);
            } else {
                $txtContent = str_replace("{if \$custom_message}\nMessage personnalisé :\n{custom_message}\n\n{/if}", "", $txtContent);
            }                // Replace template variables
                foreach ($templateVars as $key => $value) {
                    $txtContent = str_replace($key, $value, $txtContent);
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'subject' => $subject,
                'html_content' => $htmlContent,
                'text_content' => $txtContent
            ]);
            exit;

        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
            exit;
        }
    }
}
