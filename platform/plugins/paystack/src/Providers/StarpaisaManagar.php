<?php

namespace Botble\Paystack\Providers;
class StarpaisaManagar
{

    public function initPaymentForUpi($amount)
    {
        $pg_request_res = new PGRequestRes();
        $pg_request_res->status = false;
        $paymentAmount = number_format((float)$amount, 2, '.', '');
        $paymentAmount = (string)$paymentAmount;
        $starpaisa_order = (new StarpaisaUtils())->createStarpaisaOrder($paymentAmount);
        if(isset($starpaisa_order))
        {
            try {
                if (isset($starpaisa_order->statusCode)) {
                    if ($starpaisa_order->statusCode == 200) {
                        if ($starpaisa_order->data->status === 'SUCCESS'){
                            $pg_request_res->status = true;
                            $pg_request_res->action_url = $starpaisa_order->data->qrString;
                            $pg_request_res->extTransactionId = $starpaisa_order->data->extTransactionId;
                            $pg_request_res->amount = $starpaisa_order->data->amount;
                        }
                        $pg_request_res->respMessage = $starpaisa_order->data->respMessage;
                    }
                }
            }catch (\Exception $exception)
            {

            }
        }
        return $pg_request_res;
    }
    public function checkTransactionStatus($pg_ref_id, $amount)
    {
        $pgStatusRes = new PGStatusRes();
        $starpaisa_order =  (new StarpaisaUtils())->PaymentStatus($pg_ref_id);
        $paymentAmount = $amount;
        $paymentAmount = number_format((float)$paymentAmount, 2, '.', '');
        $paymentAmount = (string)$paymentAmount;
        if(isset($starpaisa_order->statusCode))
        {
            if($starpaisa_order->statusCode == 200)
            {
                $pgStatusRes->status=true;

                if($starpaisa_order->data->status == 'SUCCESS' && $starpaisa_order->data->amount == $paymentAmount)
                {
                    $pgStatusRes->paymentStatus=PaymentStatus::SUCCESS;
                    $pgStatusRes->bankRRN=$starpaisa_order->data->custRefNo;
                    $pgStatusRes->extTransactionId=$starpaisa_order->data->extTransactionId;
                    $pgStatusRes->remark=$starpaisa_order->data->remark;
                    $pgStatusRes->customerName=$starpaisa_order->data->customerName;
                    $pgStatusRes->customerVpa=$starpaisa_order->data->customerVpa;
                }else{
                    $pgStatusRes->pgResMessage = 'Amount Mismatch';
                }
            }else{
                $pgStatusRes->pgResMessage=$starpaisa_order->data->respMessage;
            }
        }
        return $pgStatusRes;
    }

}

