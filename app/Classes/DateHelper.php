<?php

namespace App\Classes;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DateHelper
{
    public static function currentDate(): ?string
    {
        try {
            return Carbon::now()->toDateTimeString();
        }catch (\Exception $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return null;
        }
    }
    public static function diffBetweenInHour($toDate, $fromDate): int
    {
        try {
            $to = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $toDate);
            $from = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $fromDate);
            return  $to->diffInHours($from);
        }catch (\Exception $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return 0;
        }
    }
}
