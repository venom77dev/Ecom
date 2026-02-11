<?php

namespace Botble\Paystack\Providers;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;

class StarpaisaUtils
{

    private $orderUrl;
    private $username;
    private $password;
    private $statusUrl;

    public function __construct()
    {
        $this->orderUrl = 'https://api.starpaisa.in/v1/merchant/generateQR';
        $this->statusUrl = 'https://api.starpaisa.in/v1/merchant/qrStatus';
        $this->username = 'perackentp@gmail.com';
        $this->password = 'ramboes@788';
    }

    public function createStarpaisaOrder($amount)
    {
        $data =[
            'amount'=>$amount
        ];
        Log::channel('starpaisa')->info('createHostedOrder',[$data]);
        $response = $this->executeStarPaisaApi($this->orderUrl, $data, '[ TXN CREATE ] ');
        return $response;
    }

    public function PaymentStatus($pgOrderId)
    {
        $data =[
            'extTransactionId'=> $pgOrderId
        ];
        $response = $this->executeStarPaisaApi($this->statusUrl, $data, '[ TXN STATUS ] ');
        return $response;
    }

    private function executeStarPaisaApi($orderUrl, $data, $logRemark = null)
    {
        $start = time();
        try {
            $resdata = Http::timeout(30)->withBasicAuth($this->username, $this->password);
            $header = [
                "Content-Type: application/json",
                "X-Content-Type-Options:nosniff",
                "Accept:Application/json",
                "Cache-Control:no-cache"
            ];

            $req = $resdata->withHeaders($header)->post($orderUrl, $data);
            $stateCode = $req->status();
            $resData = $req->body();
            $end = time();
            $executionTime = $end - $start;
            try {
                $datab = [
                    'response_body' => $resData,
                    'response_http_code' => $stateCode,
                    'seconds' => round($executionTime, 4)
                ];
                Log::channel('pgresponse')->info($logRemark.' STARPAISA_PGRESPONSE', $datab);
            } catch (\Exception $exception) {

            }
            if ($stateCode === 201) {
                return json_decode($resData, false);
            } else if ($stateCode === 200) {
                return json_decode($resData, false);
            }
            Log::channel('pgresponse')->info('starpaisa Error', ['executeApi' => $resData, 'stateCode' => $stateCode]);
            return null;
        } catch (RequestException $ex) {
            $end = time();
            $executionTime = $end - $start;
            try {
                $datab = [
                    'response_body' => $ex->getMessage(),
                    'response_http_code' => 0,
                    'seconds' => round($executionTime, 4)
                ];
                Log::channel('starpaisa')->info('starpaisa Error', $datab);
            } catch (\Exception $exception) {

            }

            Log::channel('pgresponse')->critical('starpaisa error', ['executeApi' => $ex->getMessage()]);
            return null;

        } catch (\Exception $ex) {
            $end = time();
            $executionTime = $end - $start;
            try {
                $datab = [
                    'response_body' => $ex->getMessage(),
                    'response_http_code' => 0,
                    'seconds' => round($executionTime, 4)
                ];
                Log::channel('pgresponse')->info('STARPAISA_PGRESPONSE', $datab);
            } catch (\Exception $exception) {

            }
            $exception = $ex->getMessage();
            Log::channel('pgresponse')->critical('starpaisa Error', ['executeApi' => $exception]);
            return null;
        }
    }
}

