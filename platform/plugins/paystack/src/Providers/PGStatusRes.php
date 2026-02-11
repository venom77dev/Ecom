<?php
namespace Botble\Paystack\Providers;

class PGStatusRes
{

    public $status = false;
    public $paymentStatus = PaymentStatus::PENDING;
    public $bankRRN = null;
    public $pgResMessage = null;
    public $extTransactionId = null;
    public $remark = null;
    public $customerName = null;
    public $customerVpa = null;

}

