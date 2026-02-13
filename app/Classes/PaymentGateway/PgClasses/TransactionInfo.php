<?php

namespace App\Classes\PaymentGateway\PgClasses;

class TransactionInfo
{
        public $transaction_id;
        public $pg_transaction_id;
        public $amount;
        public $payment_status;
        public $bank_rrn=null;
        public $pg_res_msg=null;
}
