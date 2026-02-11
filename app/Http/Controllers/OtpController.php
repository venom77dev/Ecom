<?php

namespace App\Http\Controllers;

use App\Classes\DateHelper;
use App\Classes\Provider\MySmsMantra;
use App\Classes\Provider\SmsHelper;
use App\Classes\ResponseHelper;
use App\Classes\StaticClasses;
use App\Models\MobileVerification;
use App\Models\User;
use Botble\Ecommerce\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as RequestClient;


class OtpController extends Controller
{
    public $user, $mobileVerification;
    public function __construct()
    {
        $this->user = new Customer();
        $this->mobileVerification = new MobileVerification();
    }

    public function sendOpt(Request $request){
        try {
            $validator = Validator::make($request->all(), [
                'mobile' => 'required|digits:10',
            ]);
            if ($validator->fails()) {
                $error = $validator->errors()->first();
                return (new ResponseHelper(false, trans('validation.error'), 400, $error))->get();
            }
            $clientIp = $request->ip();
            $userExistStatus = $this->user->checkMobileNumberExistOrNot($request->mobile);
            if ($userExistStatus){
                return (new ResponseHelper(false, trans('mobile already exist'), 400))->get();
            }
            if ($this->user->checkUserExist($request->mobile)) {
                return (new ResponseHelper(false, trans('username already register'), 400))->get();
            }
            $smsHelper = new SmsHelper($request->mobile);
            $mobile = $request->mobile;
            $otp = self::generateOtp($smsHelper->otpDigit);
            if ($this->mobileVerification->checkOtpDuplication($otp)) {
                $otp = self::generateOtp($smsHelper->otpDigit);
            }
            // 1.Resend OTP Code. [ First Execute Resend Code & after run Send Code... ]
            $mobileDetail = $this->mobileVerification->getOtpByPhoneNumber($mobile);
            if (isset($mobileDetail) && !empty($mobileDetail)) {
                if ($mobileDetail->count_sms >= $smsHelper->number_of_time_send_sms) {
                    // Start Calculate Re Generate OTP Duration
                    $lastDate = $this->mobileVerification->getLastUpdateDate($mobile);
                    $currentDate = DateHelper::currentDate();
                    $differHours = DateHelper::diffBetweenInHour($lastDate, $currentDate);
                    $maximumTimeDuration = $smsHelper->newOtpGenerateDuration;
                    if ($differHours >= $maximumTimeDuration){
                        $this->mobileVerification->resetSmsCount($mobile);
                    }
                    $tryAfterHours =  $maximumTimeDuration - $differHours;
                    // END Calculate Re Generate OTP Duration
                    return (new ResponseHelper(false, trans('maximum time send otp, please try again after '. $tryAfterHours. ' Hours.'), 400))->get();
                }
                $slug = 'register';
                $otpUpdate = $this->mobileVerification->otpUpdate($mobile, $otp, $slug);
                if (!$otpUpdate){
                    return (new ResponseHelper(false, trans('auth.error_while_send_otp'), 400))->get();
                }

                //1. RE-SEND OTP
                $statusOtp = $this->otpSendByApi($mobile, $otp, $slug);

                if (!$statusOtp){
                    return (new ResponseHelper(false, trans('auth.error_while_send_otp'), 400))->get();
                }
                $this->mobileVerification->updatePhoneCount($mobile, $otp);
                return (new ResponseHelper(true, trans('user.mobile_otp_sent'), 200))->get();
            }

            // 2.Send OTP Code.
            $slug = 'register';
            if ($this->mobileVerification->addRecord($mobile, $otp, $clientIp, $slug)) {
                //1. SEND OTP
                $statusOtp = $this->otpSendByApi($mobile, $otp, $slug);
                if (!$statusOtp){
                    return (new ResponseHelper(false, trans('auth.error_while_send_otp'), 400))->get();
                }
                return (new ResponseHelper(true, trans('user.mobile_otp_sent'), 200))->get();
            }
            return (new ResponseHelper(false, trans('user.message_not_send_some_things_are_wrong'), 400))->get();
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return (new ResponseHelper(false, trans('auth.error_while_register'), 500))->get();
        }
    }
    public function optValidate($mobile, $otp){
        try {
            if ( request()->session()->has('mobile_tries') &&  request()->session()->get('mobile_tries') >= 3) {
                return false;
            }
            $result = $this->mobileVerification->getOtp($mobile, $otp);
            if (!isset($result)) {
                $tries = request()->session()->get('mobile_tries', 0);
                request()->session()->put('mobile_tries', $tries + 1);
                return false;
            }
            if (strcmp($result->otp , $otp)) {
                $tries = request()->session()->get('mobile_tries', 0);
                request()->session()->put('mobile_tries', $tries + 1);
                return false;
            }
            request()->session()->forget('mobile_tries');
            $receive_ip = self::getClientIp();
            $verifyPhoneStatus = $this->mobileVerification->verifyPhone($result->phone, $otp, $receive_ip);
            if ($verifyPhoneStatus){
                return true;
            }
            return false;
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }
    public function otpSendByApi($phone, $otp, $slug){
        try {
            request()->session()->forget('mobile_tries');

            $otp_provider = StaticClasses::OTP_PROVIDER;
            if ($otp_provider == 'smsfresh'){
                return $this->smsfreshOtp($phone, $otp, $slug);
            }else{
                return $this->mySmsMantraOtp($phone, $otp, $slug);
            }
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }

    public function smsfreshOtp($phone, $otp, $slug){
        try {
            $client = new Client();
            $smsHelper = new SmsHelper($phone, $otp, $slug);
            $request = new RequestClient(
                $smsHelper->method,
                $smsHelper->baseUrl.
                '?user='.$smsHelper->user.
                '&pass='.$smsHelper->pass.
                '&sender='.$smsHelper->sender.
                '&phone='.$smsHelper->phone.
                '&text='.$smsHelper->text.
                '&priority='.$smsHelper->priority.
                '&stype='.$smsHelper->stype,
                ['timeout' => 10]
            );
            $res = $client->sendAsync($request)->wait();
            if ($res->getStatusCode() == 200){
                return true;
            }
            return false;
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }
    public function mySmsMantraOtp($phone, $otp, $slug){
        try {
            $client = new Client();
            $smsHelper = new MySmsMantra($phone, $otp, $slug);
            $request = new RequestClient(
                $smsHelper->method,
                $smsHelper->baseUrl.
                '?ApiKey='.$smsHelper->api_key.
                '&ClientId='.$smsHelper->client_id.
                '&SenderId='.$smsHelper->sender_id.
                '&Message='.$smsHelper->text.
                '&MobileNumbers='.$smsHelper->phone.
                '&Is_Unicode='.$smsHelper->is_unicode.
                '&Is_Flash='.$smsHelper->is_flash,
                ['timeout' => 10]
            );
            $res = $client->sendAsync($request)->wait();
            if ($res->getStatusCode() == 200){
                return true;
            }
            return false;
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return false;
        }
    }


    public function commonSendOtp($mobile, $slug, $clientIp){
        try {
            $smsHelper = new SmsHelper($mobile);
            $otp = Common::generateOtp($smsHelper->otpDigit);
            $mobileVerification = new MobileVerification();
            if ($mobileVerification->checkOtpDuplication($otp)) {
                $otp = Common::generateOtp($smsHelper->otpDigit);
            }
            $otpManager = new OtpController();
            $mobileDetail = $mobileVerification->getOtpByPhoneNumber($mobile);
            // 1.Resend OTP Code. [ First Execute Resend Code & after run Send Code... ]
            if (isset($mobileDetail) && !empty($mobileDetail)) {

                //START First Time Login OTP Update Count
                if ($mobileDetail->is_page == OtpSlug::PAGE_REGISTER){
                    $mobileVerification->resetSmsCount($mobile);
                }
                $mobileDetail = $mobileVerification->getOtpByPhoneNumber($mobile);
                //END Update Count

                if ($mobileDetail->count_sms >= $smsHelper->number_of_time_send_sms) {
                    // Start Calculate Re Generate OTP Duration
                    $lastDate = $mobileVerification->getLastUpdateDate($mobile);
                    $currentDate = DateHelper::currentDate();
                    $differHours = DateHelper::diffBetweenInHour($lastDate, $currentDate);
                    $maximumTimeDuration = $smsHelper->newOtpGenerateDuration;
                    if ($differHours >= $maximumTimeDuration){
                        $mobileVerification->resetSmsCount($mobile);
                    }
                    $tryAfterHours =  $maximumTimeDuration - $differHours;
                    // END Calculate Re Generate OTP Duration
                    return (new ResponseHelper(false, trans('maximum time send otp, please try again after '. $tryAfterHours. ' Hours.'), 400))->get();
                }
                $otpUpdate = $mobileVerification->otpUpdate($mobile, $otp, $slug);
                if (!$otpUpdate){
                    return (new ResponseHelper(false, trans('auth.error_while_send_otp'), 400))->get();
                }
                //1. RE-SEND OTP
                $statusOtp = $otpManager->otpSendByApi($mobile, $otp, $slug);

                if (!$statusOtp){
                    return (new ResponseHelper(false, trans('auth.error_while_send_otp'), 400))->get();
                }
                $mobileVerification->updatePhoneCount($mobile, $otp);
                return (new ResponseHelper(true, trans('user.mobile_otp_sent'), 200))->get();
            }

//
//            // 2.Send OTP Code.
            if ($mobileVerification->addRecord($mobile, $otp, $clientIp, $slug)) {
                //1. SEND OTP
                $statusOtp = $otpManager->otpSendByApi($mobile, $otp, $slug);
                if (!$statusOtp){
                    return (new ResponseHelper(false, trans('auth.error_while_send_otp'), 400))->get();
                }
                return (new ResponseHelper(true, trans('user.mobile_otp_sent'), 200))->get();
            }
            return (new ResponseHelper(false, trans('user.message_not_send_some_things_are_wrong'), 400))->get();
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return (new ResponseHelper(false, trans('auth.error_while_send_otp'), 500))->get();
        }
    }
    public static function generateOtp($length = 4): ?string
    {
        try {
            $characters = '1234567890123456789123456789';
            $charactersLength = strlen($characters);
            $randomId = '';
            for ($i = 0; $i < $length; $i++) {
                if ($i == 0) {
                    $randomId .= random_int(1, 9);
                } else {
                    $randomId .= $characters[rand(0, $charactersLength - 1)];
                }
            }
            return $randomId;
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return null;
        }
    }
    public static function getClientIp()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}
