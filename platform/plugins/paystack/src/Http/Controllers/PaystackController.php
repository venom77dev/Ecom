<?php
namespace Botble\Paystack\Http\Controllers;

use App\Classes\PaymentGateway\PlusPeDirect;
use App\Classes\PaymentGateway\RazorpayPG;
use App\Classes\ResponseHelper;
use App\Classes\TelegramBot;
use App\Classes\TelegramResponse;
use App\Constants\Status;
use App\Http\Controllers\Gateway\PaymentController;
use App\Models\Deposit;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\PgLists;
use Botble\Payment\Models\PgLog;
use Botble\Payment\Supports\PaymentHelper;
use Botble\Paystack\Providers\PaymentStatus;
use Botble\Paystack\Providers\StarpaisaManagar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Botble\Payment\Models\Payment;

class PaystackController extends BaseController
{

    public function getPaymentStatus(Request $request, BaseHttpResponse $response)
    {
        Log::info('asd');
        $pgRefNumber = $request->pgRefNumber;
        $metadata = Cache::get('StarpaisaUtilsMetaDataInfo'.$pgRefNumber);
        $telegramDetail = (new TelegramResponse());
        if (!isset($metadata)){
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage('Please Try again..');
        }
        $amount = isset($metadata) ? json_decode($metadata)->amount : null;
        $telegramDetail->ip = $request->ip();
        $telegramDetail->amount = $amount;
        $telegramDetail->payment_method = json_decode($metadata)->ref_pg_name;
        $telegramDetail->order_id = isset($metadata) ? json_decode($metadata)->order_id : null;
        $telegramDetail->userId = isset($metadata) ? json_decode($metadata)->customer_id : null;
        Log::info('asdadadasd');
        if (!isset($pgRefNumber)){
            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'amount' => $amount,
                'currency' => 'INR',
                'charge_id' => $pgRefNumber,
                'payment_channel' => PAYSTACK_PAYMENT_METHOD_NAME,
                'status' => PaymentStatusEnum::PENDING,
                'customer_id' => isset($metadata) ? json_decode($metadata)->customer_id : null,
                'customer_type' => isset($metadata) ? json_decode($metadata)->customer_type : null,
                'payment_type' => 'direct',
                'order_id' => (array) isset($metadata) ? json_decode($metadata)->order_id : null,
            ], $request);

            $payment = Payment::query()
                ->where('charge_id', $pgRefNumber)
                ->first();
            if ($payment) {
                $payment->pg_name_el = json_decode($metadata)->ref_pg_name ;
                $payment->save();
            }
            try {
                $this->sendMessage($telegramDetail);
            }catch (\Exception $ex){
                Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Query Exception', [
                    'error_message' => $ex->getMessage(),
                    'error_at_line' => $ex->getLine(),
                    'error_file' => $ex->getFile()
                ]);
            }
            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL())
                ->setMessage(__('We Are Checking Our Payments, You Will Receive A Confirmation Shortly'));
        }
        Log::info('asdadadasd.........');
        $checkCurrentRequest = PgLog::where('extTransactionId', $pgRefNumber)->exists();
        if (!$checkCurrentRequest){
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage('Order Id Invalid. Re Generate Transaction');
        }
        $pgResponse = (new PlusPeDirect())->GetTransactionStatus($pgRefNumber);
        if(!($pgResponse->status === true && $pgResponse->paymentStatus === PaymentStatus::SUCCESS)){
            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'amount' => $amount,
                'currency' => 'INR',
                'charge_id' => $pgRefNumber,
                'payment_channel' => PAYSTACK_PAYMENT_METHOD_NAME,
                'status' => PaymentStatusEnum::PENDING,
                'customer_id' => isset($metadata) ? json_decode($metadata)->customer_id : null,
                'customer_type' => isset($metadata) ? json_decode($metadata)->customer_type : null,
                'payment_type' => 'direct',
                'order_id' => (array) isset($metadata) ? json_decode($metadata)->order_id : null,
            ], $request);

            $payment = Payment::query()
                ->where('charge_id', $pgRefNumber)
                ->first();
            if ($payment) {
                $payment->pg_name_el = json_decode($metadata)->ref_pg_name ;
                $payment->save();
            }
            $this->sendMessage($telegramDetail);
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getRedirectURL())
                ->setMessage(__('We Are Checking Our Payments, You Will Receive A Confirmation Shortly'));
        }
        $checkUtrExistOrNot = Payment::where('charge_id', $pgRefNumber)->exists();
        if ($checkUtrExistOrNot){
            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'amount' => $amount,
                'currency' => 'INR',
                'charge_id' => $pgRefNumber,
                'payment_channel' => PAYSTACK_PAYMENT_METHOD_NAME,
                'status' => PaymentStatusEnum::FAILED,
                'customer_id' => isset($metadata) ? json_decode($metadata)->customer_id : null,
                'customer_type' => isset($metadata) ? json_decode($metadata)->customer_type : null,
                'payment_type' => 'direct',
                'order_id' => (array) isset($metadata) ? json_decode($metadata)->order_id : null,
            ], $request);

            $payment = Payment::query()
                ->where('charge_id', $pgRefNumber)
                ->first();
            if ($payment) {
                $payment->pg_name_el = json_decode($metadata)->ref_pg_name ;
                $payment->save();
            }
            $telegramDetail->payment_status = PaymentStatusEnum::FAILED;
            $this->sendMessage($telegramDetail);
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getRedirectURL())
                ->setMessage(__('We Are Checking Our Payments, You Will Receive A Confirmation Shortly'));
        }
        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'amount' => $amount,
            'currency' => 'INR',
            'charge_id' => $pgRefNumber,
            'payment_channel' => PAYSTACK_PAYMENT_METHOD_NAME,
            'status' => PaymentStatusEnum::COMPLETED,
            'customer_id' => isset($metadata) ? json_decode($metadata)->customer_id : null,
            'customer_type' => isset($metadata) ? json_decode($metadata)->customer_type : null,
            'payment_type' => 'direct',
            'order_id' => (array) isset($metadata) ? json_decode($metadata)->order_id : null,
        ], $request);
        $payment = Payment::query()
            ->where('charge_id', $pgRefNumber)
            ->first();
        if ($payment) {
            $payment->extTransactionId = $pgResponse->extTransactionId ;
            $payment->pg_name_el = json_decode($metadata)->ref_pg_name ;
            $payment->utr_number = $pgResponse->customerVpa;
            $payment->save();
        }
        $checkCurrentRequest = PgLog::where('extTransactionId', $pgResponse->extTransactionId)->first();
        if ($checkCurrentRequest){
            $checkCurrentRequest->lable = 'SUCCESS';
            $checkCurrentRequest->customerVpa = $pgResponse->customerVpa ;
            $checkCurrentRequest->customerName = $pgResponse->customerName ;
            $checkCurrentRequest->remark = $pgResponse->remark ;
            $checkCurrentRequest->user_id = isset($metadata) ? json_decode($metadata)->customer_id : null ;
            $checkCurrentRequest->save();
        }
        $telegramDetail->payment_status = PaymentStatusEnum::COMPLETED;
        $this->sendMessage($telegramDetail);
        return $response
            ->setNextUrl(PaymentHelper::getRedirectURL())
            ->setMessage(__('Checkout successfully!'));
    }

    public function pgPaymentStatus(Request $request)
    {
        Log::info('=== pgPaymentStatus Called ===');
        Log::info('Request Data:', $request->all());

        try {
            $validator = Validator::make($request->all(), [
                'transaction_id' => 'required|string',
            ]);
            if ($validator->fails()) {
                $error = $validator->errors()->first();
                return (new ResponseHelper(false, $error, 400, $error))->get();
            }

            $result = (new PlusPeDirect())->GetTransactionStatus($request->transaction_id);

            return (new ResponseHelper(true, trans('transaction get'), 200, [
                'payment_status' => $result->paymentStatus
            ]))->get();
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return (new ResponseHelper(false, trans('internal server error'), 500))->get();
        }
    }
    public function sendMessage($details)
    {
        try {
            if (isset($details) && !empty($details)){
                if (!empty($details->order_id)){
                    $details->order_id = $details->order_id[0];
                }
                $userData = DB::table('ec_customers')->where("id", $details->userId)->first();
                if (!empty($userData)){
                    $details->name = $userData->name;
                    $details->mobile = $userData->phone;
                }
            }
            $details->domainName = url('/');
            $details->domainBaseUrl = parse_url(url('/'), PHP_URL_HOST);
            $details->date = Carbon::now('Asia/Kolkata')->format('Y-m-d h:i A');
            (new TelegramBot())->crateOrder($details);
        }catch (\Exception $ex){
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
        }
    }
    public function getQrCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required',
                'email' => 'nullable|email',
                'name' => 'nullable|string',
            ], [
                'amount.required' => trans('Amount Is Required'),
            ]);
            if ($validator->fails()) {
                $error = $validator->errors()->first();
                return response()->json(
                    [
                        'status' => false,
                        'message' => $error,
                    ]
                )->setStatusCode(400);
            }
            $amount = intval($request->amount);
            $pgData = PgLists::where('name', $request->pg)->where('status', 1)->first();
            if (!isset($pgData)){
                return response()->json(
                    [
                        'status' => false,
                        'message' => 'data not found !!',
                    ]
                )->setStatusCode(400);
            }
            if ($pgData->name == 'UNLIMIT'){
                if ($amount > 20000) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Amount above 20K is not allowed in this channel'
                    ], 400);
                }
            }else{
                if ($amount > 10000) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Amount above 10K is not allowed in this channel'
                    ], 400);
                }
            }
            $result = (new PlusPeDirect())->CreateTransaction($amount, Auth::id(), $pgData->pg_name_id, $pgData->pg_meta_id);
//            $result = (object) [
//                'status'  => false,
//                'action_url' => "upi://pay?pa=delphyretailpri349202@ypbiz&pn=DELPHY+RETAIL+PRIVATE+LIMITED&cu=INR&tn=Pay+to+DELPHY+RETAIL+PRIVATE+LIMITED&am=300&mam=300&mc=5691&mode=04&tr=AIRPAY1777107933&ver=1",
//                'respMessage' => "Payment order created successfully",
//                'extTransactionId' => "26021991491197",
//                'amount' => "300",
//            ];
            if (isset($result)){
                if (isset($result->action_url)){
                    $renderer = new ImageRenderer(
                        new RendererStyle(400),
                        new SvgImageBackEnd()
                    );
                    $writer = new Writer($renderer);
                    $qrCode = $writer->writeString($result->action_url);

                    PgLog::query()->create([
                        'amount' => $result->amount,
                        'extTransactionId' => $result->extTransactionId,
                        'qrString' => $result->action_url,
                        'respMessage' => $result->respMessage,
                        'lable' => 'QR_GENERATE',
                    ]);
                    return response()->json(
                        [
                            'status' => true,
                            'message' => 'data retrieve success',
                            'data' => [
                                'order_id' => $result->extTransactionId,
                                'qr_code' => $qrCode,
                            ]
                        ]
                    )->setStatusCode(200);
                }
            }
            return response()->json(
                [
                    'status' => false,
                    'message' => 'data not found',
                ]
            )->setStatusCode(400);
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return response()->json(
                [
                    'status' => false,
                    'message' => 'some thing we wrong!',
                ]
            )->setStatusCode(500);
        }
    }
    public function geLinkCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required',
                'email' => 'nullable|email',
                'name' => 'nullable|string',
            ], [
                'amount.required' => trans('Amount Is Required'),
            ]);
            if ($validator->fails()) {
                $error = $validator->errors()->first();
                return response()->json(
                    [
                        'status' => false,
                        'message' => $error,
                    ]
                )->setStatusCode(400);
            }
            $amount = intval($request->amount);
            $pgData = PgLists::where('name', $request->pg)->where('status', 1)->first();
            if ($pgData->name == 'UNLIMIT'){
                if ($amount > 20000) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Amount above 20K is not allowed in this channel'
                    ], 400);
                }
            }else{
                if ($amount > 10000) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Amount above 10K is not allowed in this channel'
                    ], 400);
                }
            }
            if (!isset($pgData)){
                return response()->json(
                    [
                        'status' => false,
                        'message' => 'data not found !!',
                    ]
                )->setStatusCode(400);
            }
            $result = (new PlusPeDirect())->CreateTransaction($amount, Auth::id(), $pgData->pg_name_id, $pgData->pg_meta_id);
//            $result = (object) [
//                'status'  => false,
//                'action_url' => "upi://pay?pa=delphyretailpri349202@ypbiz&pn=DELPHY+RETAIL+PRIVATE+LIMITED&cu=INR&tn=Pay+to+DELPHY+RETAIL+PRIVATE+LIMITED&am=300&mam=300&mc=5691&mode=04&tr=AIRPAY1779073176&ver=1",
//                'respMessage' => "Payment order created successfully",
//                'extTransactionId' => "26022053653496",
//                'amount' => "300",
//            ];

            if (isset($result)){
                if (isset($result->action_url)){
                    PgLog::query()->create([
                        'amount' => $result->amount,
                        'extTransactionId' => $result->extTransactionId,
                        'qrString' => $result->action_url,
                        'respMessage' => $result->respMessage,
                        'lable' => 'QR_GENERATE',
                    ]);
                    return response()->json(
                        [
                            'status' => true,
                            'message' => 'data retrieve success',
                            'data' => [
                                'link' => $result->action_url,
                                'order_id' => $result->extTransactionId,
                            ]
                        ]
                    )->setStatusCode(200);
                }
            }
            return response()->json(
                [
                    'status' => false,
                    'message' => 'data not found',
                ]
            )->setStatusCode(400);
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return response()->json(
                [
                    'status' => false,
                    'message' => 'some thing we wrong!',
                ]
            )->setStatusCode(500);
        }
    }
    public function getPaymentStatus2(Request $request, BaseHttpResponse $response)
    {
        $pgRefNumber = $request->pgRefNumber;
        $metadata = Cache::get('StarpaisaUtilsMetaDataInfo'.$pgRefNumber);
        $telegramDetail = (new TelegramResponse());
        if (!isset($metadata)){
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage('Please Try again..');
        }
        $amount = isset($metadata) ? json_decode($metadata)->amount : null;
        $telegramDetail->ip = $request->ip();
        $telegramDetail->amount = $amount;
        $telegramDetail->payment_method = json_decode($metadata)->ref_pg_name;
        $telegramDetail->order_id = isset($metadata) ? json_decode($metadata)->order_id : null;
        $telegramDetail->userId = isset($metadata) ? json_decode($metadata)->customer_id : null;

        if (!isset($pgRefNumber)){
            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'amount' => $amount,
                'currency' => 'INR',
                'charge_id' => $pgRefNumber,
                'payment_channel' => PAYSTACK_PAYMENT_METHOD_NAME,
                'status' => PaymentStatusEnum::PENDING,
                'customer_id' => isset($metadata) ? json_decode($metadata)->customer_id : null,
                'customer_type' => isset($metadata) ? json_decode($metadata)->customer_type : null,
                'payment_type' => 'direct',
                'order_id' => (array) isset($metadata) ? json_decode($metadata)->order_id : null,
            ], $request);
            $this->sendMessage($telegramDetail);
            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL())
                ->setMessage(__('We Are Checking Our Payments, You Will Receive A Confirmation Shortly'));
        }

        $payment_id = isset($metadata) ? json_decode($metadata)->razorpay_payment_id : null;
        $razorpay_signature = isset($metadata) ? json_decode($metadata)->razorpay_signature : null;

        $attributes = array(
            'razorpay_order_id' => isset($metadata) ? json_decode($metadata)->razorpay_order_id : null,
            'razorpay_payment_id' => $payment_id,
            'razorpay_signature' => $razorpay_signature
        );
        $status = (new RazorpayPG())->StatusCheck($attributes);
        if ($status){
            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'amount' => $amount,
                'currency' => 'INR',
                'charge_id' => $pgRefNumber,
                'payment_channel' => PAYSTACK_PAYMENT_METHOD_NAME,
                'status' => PaymentStatusEnum::COMPLETED,
                'customer_id' => isset($metadata) ? json_decode($metadata)->customer_id : null,
                'customer_type' => isset($metadata) ? json_decode($metadata)->customer_type : null,
                'payment_type' => 'direct',
                'order_id' => (array) isset($metadata) ? json_decode($metadata)->order_id : null,
            ], $request);
            $telegramDetail->payment_status = PaymentStatusEnum::COMPLETED;
            $this->sendMessage($telegramDetail);
            return $response
                ->setNextUrl(PaymentHelper::getRedirectURL())
                ->setMessage(__('Checkout successfully!'));
        }else{

            do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                'amount' => $amount,
                'currency' => 'INR',
                'charge_id' => $pgRefNumber,
                'payment_channel' => PAYSTACK_PAYMENT_METHOD_NAME,
                'status' => PaymentStatusEnum::FAILED,
                'customer_id' => isset($metadata) ? json_decode($metadata)->customer_id : null,
                'customer_type' => isset($metadata) ? json_decode($metadata)->customer_type : null,
                'payment_type' => 'direct',
                'order_id' => (array) isset($metadata) ? json_decode($metadata)->order_id : null,
            ], $request);
            $this->PaymentUpdate($pgRefNumber, $metadata);
            $telegramDetail->payment_status = PaymentStatusEnum::FAILED;
            $this->sendMessage($telegramDetail);
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getRedirectURL())
                ->setMessage(__('We Are Checking Our Payments, You Will Receive A Confirmation Shortly'));
        }

    }
    public function getRaz(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required',
            ], [
                'amount.required' => trans('Amount Is Required'),
            ]);
            if ($validator->fails()) {
                $error = $validator->errors()->first();
                return response()->json(
                    [
                        'status' => false,
                        'message' => $error,
                    ]
                )->setStatusCode(400);
            }
            $amount = intval($request->amount);
            $pgData = PgLists::where('name', $request->pg)->where('status', 1)->first();
            if (!isset($pgData)){
                return response()->json(
                    [
                        'status' => false,
                        'message' => 'data not found !!',
                    ]
                )->setStatusCode(400);
            }

            $orderResult = (new RazorpayPG())->CreateTransaction($amount);
            Cache::put('RAZ_SUCCESS_URL', $request->current_url, now()->addSeconds(600));

            $razorpayPG = (new RazorpayPG());
            if (isset($orderResult) && !empty($orderResult)){
                return response()->json(
                    [
                        'status' => true,
                        'message' => 'data retrieve success',
                        'data' => [
                            'key' => $razorpayPG->api_key,
                            'amount' => $orderResult->amount,
                            'currency' => $razorpayPG->currency,
                            'name' => $razorpayPG->comapany_name,
                            'description' => 'Payment for your order',
                            'image' => $razorpayPG->image,
                            'order_id' =>$orderResult->order_id,
                            'callback_url' => $razorpayPG->callback_url,
                            'color_code' => $razorpayPG->color_code,
                        ]
                    ]
                )->setStatusCode(200);
            }
            return response()->json(
                [
                    'status' => false,
                    'message' => 'data not found',
                ]
            )->setStatusCode(400);
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return response()->json(
                [
                    'status' => false,
                    'message' => 'some thing we wrong!',
                ]
            )->setStatusCode(500);
        }
    }
    public function getRazSuccess(Request $request)
    {
        try {
            $data = $request->all();
            if (isset($data) && !empty($data)){
                $url = Cache::get('RAZ_SUCCESS_URL');
                if (isset($url) && !empty($url)){
                    header('Location: ' . $url. '?pg=Razorpay&slug=redirect&razorpay_payment_id='.$data['razorpay_payment_id'].'&razorpay_order_id='.$data['razorpay_order_id'].'&razorpay_signature='.$data['razorpay_signature']);
                    exit;
                }
            }
            if (isset($url) && !empty($url)){
                header('Location: ' . $url. '?pg=Razorpay&slug=redirect');
                exit;
            }
        } catch (\Exception $ex) {
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
            return response()->json(
                [
                    'status' => false,
                    'message' => 'some thing we wrong!',
                ]
            )->setStatusCode(500);
        }
    }
    public function PaymentUpdate($pgRefNumber, $metadata)
    {
        $payment = Payment::query()
            ->where('charge_id', $pgRefNumber)
            ->first();
        if ($payment) {
            $payment->pg_name_el = json_decode($metadata)->ref_pg_name ;
            $payment->save();
        }
        return;
    }
}
