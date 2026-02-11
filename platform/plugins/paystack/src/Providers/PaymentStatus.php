<?php


namespace Botble\Paystack\Providers;

class PaymentStatus
{

    const SUCCESS = "Success";
    const FAILED = "Failed";
    const INITIALIZED = "Initialized";
    const OPEN = "Open";
    const PROCESSING = "Processing";
    const PENDING = "Pending";
    const OnHold = "OnHold";
    const EXPIRED = "Expired";
    const PARTIAL_REFUND = "Partial Refund";
    const FULL_REFUND = "Full Refund";
    const NOT_ATTEMPTED = "Not Attempted";
    const BANKDOWN = "BankDown";
}

