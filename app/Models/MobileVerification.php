<?php

namespace App\Models;

use App\Classes\Helper\DateHelper;
use App\Classes\StaticClasses;
use App\Constant\OtpSlug;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
/**
 * @mixin Builder
 */
class MobileVerification extends Model
{
    protected $table = "tbl_mobile_verification";
    protected $primaryKey = 'phone';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'is_enabled' => 'boolean',
        'count' => 'integer',
        'is_activated' =>  'boolean'
    ];


    public function addRecord($phone, $otp, $send_ip, $slug): bool
    {
        try{
            $this->phone = $phone;
            $this->otp = $otp;
            $this->is_enabled = true;
            $this->is_activated = false;
            $this->send_date =  Carbon::now()->toDateTimeString();
            $this->send_ip = $send_ip;
            $this->count_sms = 1;
            $this->is_page = $slug;
            $this->provider = StaticClasses::OTP_PROVIDER;
            if($this->save()){
                return true;
            }
            return false;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }

    public function getOtpByPhoneNumber($phone)
    {
        try{
            $result = $this->where('phone', $phone)->where('is_enabled', true)->first();
            if($result){
                return  $result;
            }
            return null;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return null;
        }
    }

    public function getOtp($phone,$otp)
    {
        try{
            $result = $this->where('phone', $phone)->where('otp', $otp)->where('is_enabled',true)->where('is_activated',false)->first();
            if($result){
                return $result;
            }
            return null;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return null;
        }
    }

    public function verifyPhone($phone, $otp, $receive_ip): bool
    {
        try{
            $result = $this->where('phone', $phone)->where('otp', $otp)
                ->update(['is_activated' => true,'count_sms'=>0,'receive_date' => Carbon::now()->toDateTimeString(),'receive_ip' => $receive_ip]);
            if($result){
                return true;
            }
            return false;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }

    public function checkIsVerify($email): bool
    {
        try{
            $result = $this->where('email',$email)->where('is_enabled',true)->where('is_activated',true)->exists();
            if($result){
                return true;
            }
            return false;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }

    public function updatePhoneCount($phone,$otp): bool
    {
        try{
            $result = $this->where('phone', $phone)->where('otp', $otp)->increment('count_sms', 1, [
                'last_update_at' => Carbon::now()->toDateTimeString()
            ]);
            if($result){
                return true;
            }
            return false;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }

    public function checkOtpDuplication($otp): bool
    {
        try{
            return $this->where('otp', $otp)->exists();
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }
    public function getLastUpdateDate($phone){
        try{
            $result = $this->where('phone', $phone)->first();
            if ($result){
                return $result->last_update_at;
            }
            return null;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return null;
        }
    }
    public function resetSmsCount($phone){
        try{
            $result = $this->where('phone', $phone)->update([
                'count_sms' => 0
            ]);
            if ($result){
                return true;
            }
            return false;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }
    public function otpUpdate($phone, $otp, $slug){
        try{
            $result =  $this->where('phone', $phone)->update([
                'otp' => $otp,
                'is_page' => $slug,
                'is_activated' => false,
                'provider' => StaticClasses::OTP_PROVIDER
            ]);
            if ($result){
                return true;
            }
            return false;
        }catch (QueryException $ex){
            Log::error(__CLASS__.'::'.__FUNCTION__.' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }
}
