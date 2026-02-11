<?php

namespace App\Classes\PaymentGateway;

use App\Classes\PaymentGateway\PgClasses\RazorpayStatus;
use Botble\Payment\Models\PgLists;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayPG
{
    public $api_key = null;
    public $api_secret = null;
    public $currency = 'INR';
    public $callback_url = '';
    public $color_code = '#f489f4';
    public $comapany_name = 'WE MAINTAIN ONLINE SERVICE';
    public $image = 'https://styleglint.com/storage/new/favicon-logo.png';
    public function __construct()
    {
        $pgData = PgLists::where('name', 'Razorpay')->where('status', 1)->first();
        if (isset($pgData) && !empty($pgData)) {
            $this->api_key = $pgData->pg_name_id;
            $this->api_secret = $pgData->pg_meta_id;
        }
        $this->callback_url = route('starpaisa.payment.success');
    }
    public function CreateTransaction($amount)
    {
        Log::channel('razorpay')->info('REQUEST_', ['api_key' => $this->api_key,'amount'=>$amount]);
        $result = (new RazorpayStatus());
        try {
            $api = new Api($this->api_key, $this->api_secret);
            $order = $api->order->create([
                'amount' => $amount * 100,
                'currency' => 'INR',
                'receipt' => 'order_receipt_'.\Str::random(6)
            ]);
            Log::channel('razorpay')->info('RESPONSE_', ['api_key' => $this->api_key,'order_id'=>$order->id,'order_amount'=>$order->amount]);
            $result->order_id = $order->id;
            $result->amount = $order->amount;
        } catch (\Exception $exception) {
        }
        return $result;
    }
    public function StatusCheck($attributes)
    {
        $status = false;
        $api = new Api($this->api_key, $this->api_secret);
        try {
            $api->utility->verifyPaymentSignature($attributes);
            $status = true;
        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            $status = false;
        }
        Log::channel('razorpay')->info('STATUS_', ['api_key' => $this->api_key,'status' => $status,'razorpay_order_id'=>$attributes['razorpay_order_id'],'razorpay_payment_id'=>$attributes['razorpay_payment_id']]);
        return $status;
    }
}
