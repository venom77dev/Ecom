<?php

namespace App\Classes\Provider;

class SmsHelper
{
    public $user, $pass, $sender, $text, $priority, $stype, $phone, $otpDigit, $otp, $newOtpGenerateDuration, $secondTimeNumberOfSms,$method,$baseUrl,$reset_password_text,$number_of_time_send_sms;
    public function __construct($phone, $otp = null, $slug = null)
    {
        $this->method = 'GET';
        $this->baseUrl = 'http://trans.smsfresh.co/api/sendmsg.php';
        $this->user = 'MITTIONESMS';
        $this->pass = 'Mittione2024';
        $this->sender = 'MITONE';
        $this->otp = $otp;
        $this->text = 'Dear customer, Your One time password for verifying mobile number is '.$this->otp.', DO NOT share this OTP with anyone Regards MOSP';
        $this->priority = 'ndnd';
        $this->stype = 'normal';
        $this->phone = $phone;
        $this->otpDigit = 6;
        $this->number_of_time_send_sms = 12;
        $this->secondTimeNumberOfSms = 2; // After
        $this->newOtpGenerateDuration = 24; // After 24 Hours New Otp Generate
    }
}
