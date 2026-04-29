<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected $token = "8793332771:AAHGpL83PANP6rUbdzsohV3z4vODwkpXLic"; 
    protected $adminChatId = "1464023815"; 

    public function handle(Request $request)
    {
        $data = $request->all();

        // تجنب انهيار السيرفر في حال كان الطلب فارغاً
        if (!$data) return response()->json(['status' => 'no data']);

        try {
            // 1. التعامل مع الأزرار
            if (isset($data['callback_query'])) {
                $chatId = $data['callback_query']['message']['chat']['id'];
                $callbackData = $data['callback_query']['data'];
                $this->handleButtons($chatId, $callbackData);
            } 
            
            // 2. التعامل مع الرسائل
            elseif (isset($data['message'])) {
                $chatId = $data['message']['chat']['id'];
                $text = $data['message']['text'] ?? $data['message']['caption'] ?? '';

                if ($text == '/start') {
                    $this->sendFireWelcome($chatId);
                } elseif ($chatId == $this->adminChatId && str_starts_with($text, 'reply:')) {
                    $this->processAdminReply($text);
                } else {
                    $this->forwardToAdmin($chatId, $data['message']);
                }
            }
        } catch (\Exception $e) {
            // تسجيل الخطأ داخلياً دون إظهار 500 لتيليجرام
            Log::error("Telegram Bot Error: " . $e->getMessage());
        }

        return response()->json(['status' => 'success']);
    }

    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **UniTask | رفيقك نحو الامتياز**\n\n"
             . "أهلاً بك يا بطل! نحن لا نحل الواجبات فقط، بل نؤمن لك طريق الوصول لـ **+A** بكل سهولة. 🔥\n\n"
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
        $msg = "";
        if ($data == 'p_hw') {
            $msg = "🔥 **قسم الواجبات:**\n💸 السعر: **10 - 20 شيكل**.\n📥 **أرسل التفاصيل الآن!**";
        } elseif ($data == 'p_ex') {
            $msg = "⚡ **قسم الاختبارات:**\n🔹 كويز: **10** | نصفي: **15** | نهائي: **20**\n📥 **أرسل المادة والموعد!**";
        } elseif ($data == 'p_pr') {
            $msg = "💻 **قسم المشاريع:**\n💰 السعر: **10 - 80 شيكل**.\n📥 **أرسل المتطلبات هان!**";
        }
        
        $this->sendRequest($chatId, $msg);
    }

    private function forwardToAdmin($studentId, $message)
    {
        if ($studentId == $this->adminChatId) return;

        $text = $message['text'] ?? $message['caption'] ?? '(مرفق ميديا)';

        // رسالة للطالب
        $this->sendRequest($studentId, "✅ **تم استلام طلبك!**\nالمهندس فادي يراجع طلبك الآن. ⏳");

        // رسالة للأدمن
        $adminNotice = "🚀 **طلب جديد من طالب**\n"
                     . "👤 **ID:** `{$studentId}`\n"
                     . "📝 **الوصف:** {$text}\n\n"
                     . "👇 **للرد:**\n"
                     . "`reply:{$studentId}:نص الرد`";

        $this->sendRequest($this->adminChatId, $adminNotice);

        // إعادة توجيه أي صورة أو ملف للأدمن فوراً
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
            $this->sendRequest($this->adminChatId, "✅ تم إرسال الرد.");
        }
    }

    private function sendRequest($chatId, $text, $buttons = null)
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($buttons) $payload['reply_markup'] = $buttons;
        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }
}