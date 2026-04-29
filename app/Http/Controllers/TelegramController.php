<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected $token = "8793332771:AAHGpL83PANP6rUbdzsohV3z4vODwkpXLic"; 
    protected $adminChatId = "1464023815"; 

    public function handle(Request $request)
    {
        $data = $request->all();

        try {
            // 1. معالجة الضغط على الأزرار
            if (isset($data['callback_query'])) {
                $chatId = $data['callback_query']['message']['chat']['id'] ?? null;
                $callbackData = $data['callback_query']['data'] ?? null;
                if ($chatId && $callbackData) {
                    $this->handleButtons($chatId, $callbackData);
                }
            } 
            
            // 2. معالجة الرسائل العادية
            elseif (isset($data['message'])) {
                $message = $data['message'];
                $chatId = $message['chat']['id'] ?? null;
                $text = $message['text'] ?? '';

                if (!$chatId) return response()->json(['status' => 'no chat id']);

                if ($text == '/start') {
                    Cache::forget("user_state_{$chatId}");
                    $this->sendFireWelcome($chatId);
                } 
                elseif ($chatId == $this->adminChatId && str_starts_with($text, 'reply:')) {
                    $this->processAdminReply($text);
                } 
                else {
                    $this->forwardEverythingToAdmin($chatId, $message);
                }
            }
        } catch (\Exception $e) {
            // تسجيل الخطأ في اللوج لتعرف سببه لاحقاً
            Log::error("Telegram Bot Error: " . $e->getMessage());
        }
        
        return response()->json(['status' => 'success']);
    }

    private function forwardEverythingToAdmin($studentId, $message)
    {
        if ($studentId == $this->adminChatId) return;

        $selectedSection = Cache::get("user_state_{$studentId}", "غير محدد");
        
        // إشعار للطالب
        $this->sendRequest($studentId, "✅ **وصلت رسالتك!**\nالمهندس فادي يراجع طلبك الآن.. ⏳");

        // استخراج النص أو الكابشن (لأنه في الصور لا يوجد text بل يوجد caption)
        $userInput = $message['text'] ?? ($message['caption'] ?? 'أرسل مرفق (صورة/ملف)');
        
        $adminNotice = "🚀 **طلب جديد [$selectedSection]**\n"
                     . "👤 **ID الطالب:** `{$studentId}`\n"
                     . "📝 **المحتوى:** {$userInput}";

        $this->sendRequest($this->adminChatId, $adminNotice);

        // إذا أرسل المستخدم صورة أو ملف (أي شيء ليس نصاً صرفاً)
        if (!isset($message['text'])) {
            $this->forwardMedia($this->adminChatId, $studentId, $message['message_id']);
        }
    }

    private function handleButtons($chatId, $data)
    {
        $response = "";
        $section = "";

        if ($data == 'p_hw') {
            $section = "الواجبات";
            $response = "📥 **أرسل تفاصيل واجبك الآن (صورة أو نص)**..";
        } elseif ($data == 'p_ex') {
            $section = "الاختبارات";
            $response = "📥 **أرسل اسم المادة والموعد هلقيت**..";
        } elseif ($data == 'p_pr') {
            $section = "المشاريع";
            $response = "📥 **أرسل ملف المتطلبات هان**..";
        }

        if ($section) Cache::put("user_state_{$chatId}", $section, 3600);
        if ($response) $this->sendRequest($chatId, $response);
    }

    private function forwardMedia($to, $from, $msgId)
    {
        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/forwardMessage", [
            'chat_id' => $to,
            'from_chat_id' => $from,
            'message_id' => $msgId
        ]);
    }

    private function sendRequest($chatId, $text, $buttons = null)
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($buttons) $payload['reply_markup'] = $buttons;

        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }

    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **UniTask**\n\n👇 **اختر وجهتك الآن:**";
        $buttons = ['inline_keyboard' => [
            [['text' => '📚 قسم الواجبات', 'callback_data' => 'p_hw']],
            [['text' => '📝 قسم الاختبارات', 'callback_data' => 'p_ex']],
            [['text' => '💻 المشاريع البرمجية', 'callback_data' => 'p_pr']],
        ]];
        $this->sendRequest($chatId, $msg, $buttons);
    }

    private function processAdminReply($text)
    {
        $parts = explode(':', $text, 3);
        if (count($parts) === 3) {
            $this->sendRequest(trim($parts[1]), "👨‍🏫 **رد من المهندس:**\n\n" . trim($parts[2]));
            $this->sendRequest($this->adminChatId, "✅ تم إرسال ردك.");
        }
    }
}