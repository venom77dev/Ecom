<?php

namespace App\Classes\PaymentGateway\PgClasses;

class PayoutInfo
{
    public $payout_id;
    public $pg_payout_id;
    public $amount;
    public $payout_status;
    public $bank_rrn=null;
    public $pg_res_msg=null;
}
