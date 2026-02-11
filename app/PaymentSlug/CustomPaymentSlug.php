<?php

namespace App\PaymentSlug;

use Illuminate\Support\Facades\Log;

class CustomPaymentSlug
{
    //{{ payment_method == 'Paystack' ? 'Starpaisa' : payment_method }}
    const CUSTOM_PG_NAME = 'Online Pay';

    public static function getPgNameLabel($objectData){
        try {
            if (isset($objectData->payment_channel) && $objectData->payment_channel->label() == 'Cash on delivery (COD)'){
                return 'Cash on delivery (COD)';
            }
            if (isset($objectData)){
                return  $objectData->pg_name_el;
            }
            return 'Online Pay';
        }catch (\Exception $ex){
            return 'Online Pay';
        }
    }
}



