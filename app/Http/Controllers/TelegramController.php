<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TelegramController extends Controller
{
    protected $token = "8793332771:AAHGpL83PANP6rUbdzsohV3z4vODwkpXLic"; 
    protected $adminChatId = "1464023815"; 

    public function handle(Request $request)
    {
        $data = $request->all();

        // 1. التعامل مع الأزرار
        if (isset($data['callback_query'])) {
            $chatId = $data['callback_query']['message']['chat']['id'];
            $callbackData = $data['callback_query']['data'];
            $this->handleButtons($chatId, $callbackData);
        } 
        
        // 2. التعامل مع الرسائل (نصوص، صور، ملفات)
        elseif (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            $text = $data['message']['text'] ?? '';

            if ($text == '/start') {
                Cache::forget("user_state_{$chatId}");
                $this->sendFireWelcome($chatId);
            } 
            elseif ($chatId == $this->adminChatId && str_contains($text, 'reply:')) {
                $this->processAdminReply($text);
            } 
            else {
                // تمرير كل شيء للأدمن (نص، صورة، ملف..)
                $this->forwardEverythingToAdmin($chatId, $data['message']);
            }
        }
        
        return response()->json(['status' => 'success']);
    }

    private function forwardEverythingToAdmin($studentId, $message)
    {
        if ($studentId == $this->adminChatId) return;

        $selectedSection = Cache::get("user_state_{$studentId}", "غير محدد");
        
        // إخبار الطالب باستلام طلبه
        $this->sendRequest($studentId, "✅ **وصلت رسالتك!**\nالمهندس فادي يراجع طلبك الآن.. ثواني وبيرد عليك. ⏳");

        // تحديد نوع المحتوى المكتوب في رسالة الإشعار للأدمن
        $type = "نصية";
        if (isset($message['photo'])) $type = "صورة 🖼️";
        if (isset($message['document'])) $type = "ملف 📄";
        if (isset($message['voice'])) $type = "بصمة صوت 🎤";

        $adminNotice = "🚀 **طلب جديد (من $selectedSection)**\n"
                     . "👤 **الطالب:** `{$studentId}`\n"
                     . "📦 **النوع:** {$type}\n"
                     . "📝 **المحتوى:** " . ($message['text'] ?? ($message['caption'] ?? 'بدون نص')) . "\n\n"
                     . "👇 **للرد:**\n"
                     . "`reply:{$studentId}:نص الرد`";

        // 1. إرسال الإشعار النصي للأدمن أولاً
        $this->sendRequest($this->adminChatId, $adminNotice);

        // 2. إعادة توجيه المرفق للأدمن (Forward) لكي يراه بوضوح
        $this->forwardMedia($this->adminChatId, $studentId, $message['message_id']);
    }

    private function handleButtons($chatId, $data)
    {
        $response = "";
        $section = "";

        if ($data == 'p_hw') {
            $section = "الواجبات";
            $response = "📥 **أرسل تفاصيل واجبك الآن (صورة أو نص)** وسيرد المهندس فادي عليك فوراً! ⚡";
        } elseif ($data == 'p_ex') {
            $section = "الاختبارات";
            $response = "📥 **أرسل اسم المادة والموعد هلقيت**.. ودع الباقي للمهندسين! 🎯";
        } elseif ($data == 'p_pr') {
            $section = "المشاريع";
            $response = "📥 **أرسل ملف المتطلبات (Requirements) هان.. ونحن لها!** 🛠️";
        }

        if ($section) Cache::put("user_state_{$chatId}", $section, 3600);
        $this->sendRequest($chatId, $response);
    }

    // وظيفة خاصة لإرسال المرفقات للأدمن (صورة، ملف، إلخ)
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
        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => $buttons
        ]);
    }

    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **UniTask | رفيقك نحو الامتياز**\n\n👇 **اختر وجهتك الآن وباشر بالإنجاز:**";
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