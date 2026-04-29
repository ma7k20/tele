<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; // أضفنا الكاش لحفظ القسم

class TelegramController extends Controller
{
    protected $token = "8793332771:AAHGpL83PANP6rUbdzsohV3z4vODwkpXLic"; 
    protected $adminChatId = "1464023815"; 

    public function handle(Request $request)
    {
        $data = $request->all();

        if (isset($data['callback_query'])) {
            $chatId = $data['callback_query']['message']['chat']['id'];
            $callbackData = $data['callback_query']['data'];
            $this->handleButtons($chatId, $callbackData);
        } elseif (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            
            // انتبه هنا: أخذنا النص أو وصف الصورة لتجنب خطأ الـ 500
            $text = $data['message']['text'] ?? $data['message']['caption'] ?? '';

            if ($text == '/start') {
                Cache::forget("user_state_{$chatId}"); // تصفير القسم عند البداية
                $this->sendFireWelcome($chatId);
            } elseif ($chatId == $this->adminChatId && str_contains($text, 'reply:')) {
                $this->processAdminReply($text);
            } else {
                // نرسل مصفوفة الرسالة كاملة للأدمن لدعم الصور والملفات
                $this->forwardToAdmin($chatId, $data['message']);
            }
        }
        return response()->json(['status' => 'success']);
    }

    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **UniTask | رفيقك نحو الامتياز**\n\n"
             . "👇 **اختر وجهتك الآن وباشر بالإنجاز:**";

        $buttons = [
            'inline_keyboard' => [
                [['text' => '📚 قسم الواجبات', 'callback_data' => 'p_hw']],
                [['text' => '📝 قسم الاختبارات', 'callback_data' => 'p_ex']],
                [['text' => '💻 المشاريع البرمجية', 'callback_data' => 'p_pr']],
            ]
        ];
        $this->sendRequest($chatId, $msg, $buttons);
    }

    private function handleButtons($chatId, $data)
    {
        $response = "";
        if ($data == 'p_hw') {
            Cache::put("user_state_{$chatId}", "📚 الواجبات", 3600);
            $response = "📥 **أرسل تفاصيل واجبك الآن (صورة أو نص)** وسيرد المهندس فادي عليك فوراً! ⚡";
        } elseif ($data == 'p_ex') {
            Cache::put("user_state_{$chatId}", "📝 الاختبارات", 3600);
            $response = "📥 **أرسل اسم المادة والموعد هلقيت**.. ودع الباقي للمهندسين! 🎯";
        } elseif ($data == 'p_pr') {
            Cache::put("user_state_{$chatId}", "💻 المشاريع", 3600);
            $response = "📥 **أرسل ملف المتطلبات (Requirements) هان.. ونحن لها!** 🛠️";
        }
        $this->sendRequest($chatId, $response);
    }

    private function forwardToAdmin($studentId, $message)
    {
        if ($studentId == $this->adminChatId) return;

        // جلب القسم المخزن
        $section = Cache::get("user_state_{$studentId}", "غير محدد");
        $text = $message['text'] ?? $message['caption'] ?? '(ملف أو صورة بدون نص)';

        $this->sendRequest($studentId, "✅ **تم استلام طلبك بنجاح!**\nالمهندس فادي قيد المراجعة الآن. ⏳");

        $adminNotice = "🚀 **طلب جديد [$section]**\n\n"
                     . "👤 **ID الطالب:** `{$studentId}`\n"
                     . "📝 **التفاصيل:** {$text}\n\n"
                     . "👇 **للرد:**\n"
                     . "`reply:{$studentId}:نص الرد`";

        // إرسال الإشعار النصي للأدمن
        $this->sendRequest($this->adminChatId, $adminNotice);

        // إذا أرسل المستخدم (صورة أو ملف أو فويس)، أعد توجيهه للأدمن فوراً
        if (!isset($message['text'])) {
            Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/forwardMessage", [
                'chat_id' => $this->adminChatId,
                'from_chat_id' => $studentId,
                'message_id' => $message['message_id']
            ]);
        }
    }

    private function processAdminReply($text)
    {
        $parts = explode(':', $text, 3);
        if (count($parts) === 3) {
            $this->sendRequest(trim($parts[1]), "👨‍🏫 **رد من المهندس:**\n\n" . trim($parts[2]));
            $this->sendRequest($this->adminChatId, "✅ تم إرسال ردك للطالب.");
        }
    }

    private function sendRequest($chatId, $text, $buttons = null)
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($buttons) $payload['reply_markup'] = $buttons;
        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }
}