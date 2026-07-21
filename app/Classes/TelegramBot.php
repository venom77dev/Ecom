<?php

namespace App\Classes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBot
{

    private $telegramToken = '8988461907:AAHNnBUE15FiLzHGJociXbot-hrSkfb2vuA';
    private $blockChatIds = [];
    private $version = 'V1';
    private $trustedToken = 'JF0wqCIWynrQOdTgv2wY12';

    public function sendMessage($chatId, $data)
    {
        try {
            $statusIcon = match($data->payment_status) {
                'completed' => '✅',
                'pending' => '🟡',
                default => '🔴'
            };
            $params = [
                'chat_id' => $chatId,
                'text' => "
*🛍 Order Create! 🛍*

[{$data->domainBaseUrl}]({$data->domainName})

Name: *{$data->name}*
Mobile Number: *{$data->mobile}*
Order Id: `{$data->order_id}`
Amount : *{$data->amount} INR*
Payment Method : *{$data->payment_method}*
Payment Status: * $statusIcon {$data->payment_status}*
IP: `{$data->ip}`
Date: *{$data->date}*

            ",
                'parse_mode' => 'Markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => "🔗 {$data->domainBaseUrl} 🔗", 'url' => "{$data->domainName}"],
                        ],
                        [
                            ['text' => '🧿 View Order Detail🧿', 'url' => "{$data->domainName}/admin/ecommerce/orders/edit/{$data->order_id}"],
                        ]
                    ]
                ],
            ];
            Http::post("https://api.telegram.org/bot{$this->telegramToken}/sendMessage", $params);
            Log::channel('telegram_bot')->info('Message Send Success', ['chat_id' => $chatId, 'Slug' => 'Order Create', 'Order Id' => $data->order_id]);
        }catch (\Exception $ex){
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
        }
    }
    public function crateOrder($orderDetail)
    {
        try {
            $this->getUpdates();
            $chanelIds = DB::table('tbl_telegram_bot_ids')->pluck('chat_id');
            if ($chanelIds->isNotEmpty()) {
                foreach ($chanelIds as $chanelId) {
                    if (!in_array($chanelId, $this->blockChatIds)) {
                        $this->sendMessage($chanelId, $orderDetail);
                    }
                }
            }
        }catch (\Exception $ex){
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
        }
    }

    public function getUpdates()
    {
        try {
            $lastUpdateId = cache()->get('telegram_last_update_id'.$this->version, 0);
            $response = Http::get("https://api.telegram.org/bot{$this->telegramToken}/getUpdates", [
                'offset' => $lastUpdateId + 1,
            ]);
            $updates = $response->json();
            if (isset($updates['result']) && !empty($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    $updateId = $update['update_id'];
                    $chatId = $update['message']['chat']['id'] ?? null;
                    $messageText = $update['message']['text'] ?? null;

                    if (!empty($chatId) && is_string($messageText) && strpos($messageText, '/start') === 0) {
                        $exists = DB::table('tbl_telegram_bot_ids')->where('chat_id', $chatId)->exists();
                        if (!$exists) {
                            DB::table('tbl_telegram_bot_ids')->insert(['chat_id' => $chatId]);
                        }
                    }
                    cache()->put('telegram_last_update_id'.$this->version, $updateId);
                }
            }
        }catch (\Exception $ex){
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
        }
    }
    public function crateNewUser($userDetail)
    {
        try {
            $this->getUpdates();
            $chanelIds = DB::table('tbl_telegram_bot_ids')->pluck('chat_id');
            if ($chanelIds->isNotEmpty()) {
                foreach ($chanelIds as $chanelId) {
                    if (!in_array($chanelId, $this->blockChatIds)) {
                        $this->newUserRegister($chanelId, $userDetail);
                    }
                }
            }
        }catch (\Exception $ex){
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
        }
    }
    public function newUserRegister($chatId, $data)
    {
        try {

            $params = [
                'chat_id' => $chatId,
                'text' => "
*👤 New User Register! 👤*

[{$data->domainBaseUrl}]({$data->domainName})

Name: *{$data->name}*
Mobile Number: *{$data->mobile}*
User Id: `{$data->id}`
IP: `{$data->ip}`
Date: *{$data->date}*

            ",
                'parse_mode' => 'Markdown',
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            ['text' => "🔗 {$data->domainBaseUrl} 🔗", 'url' => "{$data->domainName}"],
                        ],
                        [
                            ['text' => '🧿 View User Detail🧿', 'url' => "{$data->domainName}/admin/customers/edit/{$data->id}"],
                        ]
                    ]
                ],
            ];
            Http::post("https://api.telegram.org/bot{$this->telegramToken}/sendMessage", $params);
            Log::channel('telegram_bot')->info('Message Send Success', ['chat_id' => $chatId, 'Slug' => 'New User Create', 'User Id' => $data->id]);
        }catch (\Exception $ex){
            Log::error(__CLASS__ . '::' . __FUNCTION__ . ' Query Exception', [
                'error_message' => $ex->getMessage(),
                'error_at_line' => $ex->getLine(),
                'error_file' => $ex->getFile()
            ]);
        }
    }
}
