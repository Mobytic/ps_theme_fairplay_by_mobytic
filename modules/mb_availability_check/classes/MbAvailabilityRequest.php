<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MbAvailabilityRequest extends ObjectModel
{
    /** @var int */
    public $id_request;

    /** @var int Product ID */
    public $id_product;
    /** @var int Quantity requested */
    public $qty;

    /** @var string */
    public $firstname;

    /** @var string */
    public $lastname;

    /** @var string */
    public $email;

    /** @var string|null */
    public $phone;

    /** @var string */
    public $token;

    /** @var string Datetime */
    public $date_add;
    /** @var string Status */
    public $status;

    /** @see ObjectModel::$definition */
    public static $definition = [
        'table' => 'mb_availability_requests',
        'primary' => 'id_request',
        'fields' => [
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'qty' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'firstname' => ['type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 255, 'required' => true],
            'lastname' => ['type' => self::TYPE_STRING, 'validate' => 'isName', 'size' => 255, 'required' => true],
            'email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 255, 'required' => true],
            'phone' => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 64],
            'token' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 64, 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 32, 'required' => true],
        ],
    ];

    public function add($autodate = true, $null_values = false)
    {
        if ($autodate && empty($this->date_add)) {
            $this->date_add = date('Y-m-d H:i:s');
        }

        return parent::add($autodate, $null_values);
    }
}
