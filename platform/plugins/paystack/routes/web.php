<?php
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Botble\Paystack\Http\Controllers', 'middleware' => ['web', 'core']], function () {
    Route::get('paystack/payment/callback', [
        'as' => 'paystack.payment.callback',
        'uses' => 'PaystackController@getPaymentStatus',
    ]);
    Route::post('paystack/payment/status', [
        'as' => 'paystack.payment.status',
        'uses' => 'PaystackController@pgPaymentStatus',
    ]);
    Route::get('paystack/payment/callback2', [
        'as' => 'paystack.payment.callback2',
        'uses' => 'PaystackController@getPaymentStatus2',
    ]);
    Route::post('pg/payment/qr', [
        'as' => 'starpaisa.payment.qr',
        'uses' => 'PaystackController@getQrCode',
    ]);
    Route::post('pg/payment/link', [
        'as' => 'starpaisa.payment.link',
        'uses' => 'PaystackController@geLinkCode',
    ]);
    Route::post('pg/payment/raz', [
        'as' => 'starpaisa.payment.raz',
        'uses' => 'PaystackController@getRaz',
    ]);
    Route::post('pg/payment/success', [
        'as' => 'starpaisa.payment.success',
        'uses' => 'PaystackController@getRazSuccess',
    ]);
});

