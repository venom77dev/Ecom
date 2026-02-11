<?php
namespace App\Classes\PaymentGateway\PgClasses;

class PaymentStatus
{
    const SUCCESS = "Success";
    const FAILED = "Failed";
    const INITIALIZED = "Initialized";
    const PROCESSING = "Processing";
    const PENDING = "Pending";
    const REJECT = "Reject";
    const OnHold = "OnHold";
    const EXPIRED = "Expired";
    const PARTIAL_REFUND = "Partial Refund";
    const FULL_REFUND = "Full Refund";
    const NOT_ATTEMPTED = "Not Attempted";
}

