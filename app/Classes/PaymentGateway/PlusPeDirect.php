<?php

namespace App\Classes\PaymentGateway;

use App\Classes\PaymentGateway\PgClasses\PaymentStatus;
use Botble\Paystack\Providers\PGRequestRes;
use Botble\Paystack\Providers\PGStatusRes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Botble\Payment\Models\PgLists;

class PlusPeDirect
{

    private $merchannt_id = null;
    private $merchant_key = null;
    private $siteurl = "https://pay2.pluspedirect.com";

    public function HttpRequestManager($url, $data, $slug = null)
    {
        $reqid=Str::random(20);
        try {
            $this->merchannt_id="ac_R40BQSKO";
            $this->merchant_key="key_53evynsGUEBcWXBn7Scavg3TBXE4rU9KA2Ta3CHVXsqyfvi6Sw";
            Log::channel('pluspedirect')->info($slug.'_REQUEST', ['$data' => $data,'url'=>$url]);
            $response = Http::timeout(15)->withHeaders([
                'accountid' => $this->merchannt_id,
                'accountkey' => $this->merchant_key,
            ])->post($this->siteurl . $url, $data);
            $resCode=$response->status();
            $resBody=$response->body();
            Log::channel('pluspedirect')->info($slug.'_RESPONSE', ['req_id' => $reqid,'url'=>$url,'res_code'=>$resCode, 'res' => $resBody]);
            if ($resCode == 200) {
                $data = json_decode($resBody, false);
                if ($data->status) {
                    return $data;
                }
            }
        } catch (\Exception $exception) {
            Log::channel('pluspedirect')->info($slug.'_ERROR', ['req_id' => $reqid, 'res' => $exception->getMessage()]);
            Log::info('CreateTransaction', [$exception->getMessage()]);
        }
        return null;
    }

    public function CreateTransaction($payment_amount, $customer_id, $pg_name, $meta_id)
    {
		     $pgData = PgLists::where('name',$pg_name)->first();
        $pg_name = $pgData->pg_name_id; 
        $customer_id = isset($customer_id) ? $customer_id : 'guest';
        $result = (new PGRequestRes());
        try {
            $data = self::HttpRequestManager('/AUTO/CreateSeamlessOrder', [
                'payment_ref_id' => Str::random(20),
                'payment_amount' => $payment_amount,
                'return_url' => request()->root(),
                'customer_id' => (string) $customer_id,
                'pg_name' => $pg_name,
                'meta_id' => $meta_id,
            ], 'CREATE_TRANSACTION');

            if (isset($data) && $data->status && isset($data->data)) {
                $result->action_url = $data->data->AUTO->deeplink;
                $result->amount = $data->data->amount;
                $result->extTransactionId = $data->data->order_id;
                $result->respMessage = $data->message;
            }
        } catch (\Exception $exception) {
            Log::info('CREATE_TRANSACTION', [$exception->getMessage()]);
        }
       return $result;
    }
    public function GetTransactionStatus($merchant_order_id)
    {
        $txninfo = new PGStatusRes();
        try {
            $responseData = self::HttpRequestManager('/PaymentStatus', [
                'order_id' => $merchant_order_id
            ], 'TRANSACTION_STATUS');
            if (isset($responseData) && isset($responseData->data)) {
                $data = $responseData->data;
                $txninfo->extTransactionId = $data->order_id;
                $txninfo->paymentStatus = PaymentStatus::INITIALIZED;
                if (strcmp($data->payment_status, PaymentStatus::SUCCESS)==0) {
                    $txninfo->status = true;
                    $txninfo->paymentStatus = PaymentStatus::SUCCESS;
                }
                if (strcmp($data->payment_status, PaymentStatus::FAILED)==0) {
                    $txninfo->paymentStatus = PaymentStatus::FAILED;
                }
                if (strcmp($data->payment_status, PaymentStatus::PENDING)==0) {
                    $txninfo->paymentStatus = PaymentStatus::PENDING;
                }
                if (strcmp($data->payment_status, PaymentStatus::PROCESSING)==0) {
                    $txninfo->paymentStatus = PaymentStatus::PENDING;
                }
                $txninfo->bankRRN = $data->bank_rrn;
            }

        } catch (\Exception $exception) {
            Log::info('CreateTransaction', [$exception->getMessage()]);
        }
        return $txninfo;
    }
}
