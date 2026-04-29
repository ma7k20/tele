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

        // إضافة Log هنا لتشوف شو اللي بيوصل بالضبط في حال فشل الكود
        Log::info("Telegram Data: ", $data);

        try {
            if (isset($data['callback_query'])) {
                $chatId = $data['callback_query']['message']['chat']['id'];
                $callbackData = $data['callback_query']['data'];
                $this->handleButtons($chatId, $callbackData);
            } elseif (isset($data['message'])) {
                $chatId = $data['message']['chat']['id'];
                
                // التأكد من وجود نص أو وصف صورة
                $text = $data['message']['text'] ?? $data['message']['caption'] ?? '';

                if ($text == '/start') {
                    $this->sendFireWelcome($chatId);
                } elseif ($chatId == $this->adminChatId && str_contains($text, 'reply:')) {
                    $this->processAdminReply($text);
                } else {
                    $this->forwardToAdmin($chatId, $data['message']);
                }
            }
        } catch (\Exception $e) {
            Log::error("Bot Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        return response()->json(['status' => 'success']);
    }

    // استبدلنا الكاش بـ Log مؤقت أو رسالة بسيطة للتأكد من أن السيرفر لا ينهار
    private function handleButtons($chatId, $data)
    {
        $response = "";
        $section = "غير محدد";

        if ($data == 'p_hw') {
            $section = "📚 الواجبات";
            $response = "📥 **أرسل تفاصيل واجبك الآن (صورة أو نص)** وسيرد المهندس فادي عليك فوراً! ⚡";
        } elseif ($data == 'p_ex') {
            $section = "📝 الاختبارات";
            $response = "📥 **أرسل اسم المادة والموعد هلقيت**.. ودع الباقي للمهندسين! 🎯";
        } elseif ($data == 'p_pr') {
            $section = "💻 المشاريع";
            $response = "📥 **أرسل ملف المتطلبات (Requirements) هان.. ونحن لها!** 🛠️";
        }

        // ملاحظة: إذا استمر الخطأ 500، قم بتعطيل السطر التالي مؤقتاً
        try {
            session(['user_section_' . $chatId => $section]);
        } catch (\Exception $e) {}

        $this->sendRequest($chatId, $response);
    }

    private function forwardToAdmin($studentId, $message)
    {
        if ($studentId == $this->adminChatId) return;

        // محاولة جلب القسم من الجلسة
        $section = session('user_section_' . $studentId, "طلب مباشر");
        $text = $message['text'] ?? $message['caption'] ?? '(مرفق بدون نص)';

        $this->sendRequest($studentId, "✅ **تم استلام طلبك بنجاح!**\nالمهندس فادي قيد المراجعة الآن. ⏳");

        $adminNotice = "🚀 **طلب جديد [$section]**\n\n"
                     . "👤 **ID:** `{$studentId}`\n"
                     . "📝 **الوصف:** {$text}\n\n"
                     . "👇 **للرد:**\n"
                     . "`reply:{$studentId}:نص الرد`";

        $this->sendRequest($this->adminChatId, $adminNotice);

        // إعادة توجيه أي ميديا (صور، ملفات)
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
            $this->sendRequest($this->adminChatId, "✅ تم إرسال ردك.");
        }
    }

    private function sendRequest($chatId, $text, $buttons = null)
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
        if ($buttons) $payload['reply_markup'] = $buttons;
        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }

    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **UniTask | رفيقك نحو الامتياز**\n\n👇 **اختر وجهتك:**";
        $buttons = ['inline_keyboard' => [
            [['text' => '📚 قسم الواجبات', 'callback_data' => 'p_hw']],
            [['text' => '📝 قسم الاختبارات', 'callback_data' => 'p_ex']],
            [['text' => '💻 المشاريع البرمجية', 'callback_data' => 'p_pr']],
        ]];
        $this->sendRequest($chatId, $msg, $buttons);
    }
}