<?php

namespace App\Classes\Provider;

class MySmsMantra
{
    public $text, $phone, $otp, $method,$baseUrl,$api_key, $client_id, $sender_id, $is_unicode, $is_flash;
    public function __construct($phone, $otp = null, $slug = null)
    {
        $this->method = 'GET';
        $this->baseUrl = 'https://api.mylogin.co.in/api/v2/SendSMS';
        $this->api_key = 'Lvr3toQeXKSNoVmSv+hhuZ+9F6TL3GcunjKRkPA5egw=';
        $this->client_id = '5cfaabdd-73af-45d9-a965-6781d47cb95d';
        $this->sender_id = $this->slugSender();
        $this->otp = $otp;
        $this->is_unicode = true;
        $this->is_flash = true;
        $this->text = 'Dear customer, Your One time password for verifying mobile number is '.$otp.', DO NOT share this OTP with anyone. '.$this->slugSender();
        $this->phone = '91'.$phone;
    }
    public function slugSender()
    {
        $host = request()->getHost();
        $mapping = [
            'trenddrape.com' => 'TECREV',
            'couturezip.com'    => 'TECREV',
            'trendeleg.com'    => 'TECREV',
            'silksyndi.com'    => 'TECREV',


            'coralkart.shop'    => 'IQUETE',
            'dynamozing.com'    => 'IQUETE',
            'apexgearhut.com'    => 'IQUETE',
            'zestverve.com'    => 'IQUETE',
            'styleglint.com'    => 'IQUETE',
        ];
        return $mapping[$host] ?? 'TECREV';
    }
}
