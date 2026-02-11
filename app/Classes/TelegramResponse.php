<?php

namespace App\Classes;

use Botble\Payment\Enums\PaymentStatusEnum;

class TelegramResponse
{
    public $ip = null;
    public $amount = null;
    public $payment_status = PaymentStatusEnum::PENDING;
    public $payment_method = null;
    public $order_id = null;
    public $userId = null;
    public $name = null;
    public $mobile = null;
    public $domainName = null;
    public $domainBaseUrl = null;
    public $date = null;
    public $email = null;
    public $id = null;

}
