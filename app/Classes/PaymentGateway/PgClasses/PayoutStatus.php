<?php

namespace App\Classes\PaymentGateway\PgClasses;

class PayoutStatus
{

    const SUCCESS = "Success";
    const FAILED = "Failed";
    const INITIALIZED = "Initialized";
    const LOWBAL = "LOWBAL";
    const PROCESSING = "Processing";
    const PENDING = "Pending";
    const CANCELLED = "Cancelled";
}
