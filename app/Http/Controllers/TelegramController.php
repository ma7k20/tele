<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    // بيانات البوت الخاصة بك
    protected $token = "8793332771:AAHGpL83PANP6rUbdzsohV3z4vODwkpXLic"; 
    protected $adminChatId = "1464023815"; 

    /**
     * الدالة الرئيسية لاستقبال التحديثات من تليجرام
     */
    public function handle(Request $request)
    {
        $data = $request->all();

        // تجنب المعالجة إذا كان الطلب فارغاً
        if (!$data) return response()->json(['status' => 'no data']);

        try {
            // 1. التعامل مع الضغط على الأزرار (Inline Buttons)
            if (isset($data['callback_query'])) {
                $chatId = $data['callback_query']['message']['chat']['id'];
                $callbackData = $data['callback_query']['data'];
                $this->handleButtons($chatId, $callbackData);
            } 
            
            // 2. التعامل مع الرسائل (نصوص، ملفات، صور)
            elseif (isset($data['message'])) {
                $chatId = $data['message']['chat']['id'];
                // جلب النص سواء كان رسالة عادية أو وصف تحت ملف (Caption)
                $text = $data['message']['text'] ?? $data['message']['caption'] ?? '';

                // إذا أرسل المستخدم أمر البداية
                if ($text == '/start') {
                    $this->sendFireWelcome($chatId);
                } 
                // إذا كان المرسل هو الأدمن (أنت) ويريد الرد على طالب
                elseif ($chatId == $this->adminChatId && str_starts_with($text, 'reply:')) {
                    $this->processAdminReply($data['message']);
                } 
                // إذا كان المرسل طالب عادي، نقوم بتحويل رسالته للأدمن
                else {
                    $this->forwardToAdmin($chatId, $data['message']);
                }
            }
        } catch (\Exception $e) {
            // تسجيل أي خطأ في ملفات الـ Log الخاصة بـ Laravel في ريندر
            Log::error("Telegram Bot Error: " . $e->getMessage());
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * إرسال رسالة الترحيب الأنيقة
     */
    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **أهلاً بك في UniTask | رفيقك نحو الامتياز**\n\n"
             . "نحن هنا لنجعل طريقك نحو الـ **+A** أسهل وأسرع. 🔥\n\n"
             . "👇 **اختر وجهتك الآن وباشر بالإنجاز:**";

        $buttons = [
            'inline_keyboard' => [
                [['text' => '📚 حل واجبات', 'callback_data' => 'p_hw'], ['text' => '📝 اختبارات', 'callback_data' => 'p_ex']],
                [['text' => '💻 مشاريع برمجية', 'callback_data' => 'p_pr']],
                [['text' => '👤 تواصل مباشر مع المهندس', 'url' => 'https://t.me/fadi_alaa']]
            ]
        ];
        $this->sendRequest($chatId, $msg, $buttons);
    }

    /**
     * معالجة ردود الأزرار للطالب
     */
    private function handleButtons($chatId, $data)
    {
        $responses = [
            'p_hw' => "🔥 **قسم الواجبات:**\n💸 السعر: **10 - 25 شيكل**.\n📥 **أرسل ملف الواجب أو صورة الأسئلة الآن!**",
            'p_ex' => "⚡ **قسم الاختبارات:**\n🔹 كويز: **10** | نصفي: **20** | نهائي: **30**\n📥 **أرسل المادة والموعد فوراً!**",
            'p_pr' => "💻 **المشاريع البرمجية:**\n💰 السعر: يحدد حسب المتطلبات.\n📥 **أرسل ملف المتطلبات (Doc/PDF) للمعاينة.**"
        ];

        if (isset($responses[$data])) {
            $this->sendRequest($chatId, "✅ تم اختيار القسم.\n\n" . $responses[$data]);
        }
    }

    /**
     * تحويل طلبات الطلاب للأدمن مع خيارات الرد السريع
     */
    private function forwardToAdmin($studentId, $message)
    {
        if ($studentId == $this->adminChatId) return;

        $text = $message['text'] ?? $message['caption'] ?? '(مرفق ملف/ميديا)';

        // إشعار للطالب بالاستلام
        $this->sendRequest($studentId, "✅ **تم استلام طلبك!**\nالمهندس فادي يراجع التفاصيل وسيرد عليك بالسعر فوراً. ⏳");

        // إشعار للأدمن (أنت) مع ID الطالب لتسهيل الرد
        $adminNotice = "🚀 **طلب جديد من طالب**\n"
                     . "👤 **ID:** `{$studentId}`\n"
                     . "📝 **الوصف:** {$text}\n\n"
                     . "👇 **للرد (نص أو أرسل ملف مع هذا الوصف):**\n"
                     . "`reply:{$studentId}:نص الرد هنا`";

        $this->sendRequest($this->adminChatId, $adminNotice);

        // إعادة توجيه الملفات أو الصور للأدمن
        if (!isset($message['text'])) {
            Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/forwardMessage", [
                'chat_id' => $this->adminChatId,
                'from_chat_id' => $studentId,
                'message_id' => $message['message_id']
            ]);
        }
    }

    /**
     * معالجة رد الأدمن وإرساله للطالب (نصوص أو ملفات)
     */
    private function processAdminReply($message)
    {
        $text = $message['text'] ?? $message['caption'] ?? '';
        $parts = explode(':', $text, 3);
        
        if (count($parts) < 3) return;

        $targetStudentId = trim($parts[1]);
        $replyText = trim($parts[2]);

        // إذا قام الأدمن بإرسال (ملف أو صورة) مع نص الرد في الوصف
        if (isset($message['document']) || isset($message['photo'])) {
            $this->sendMediaToStudent($targetStudentId, $message, $replyText);
        } 
        // إذا كان الرد نصياً فقط
        else {
            $this->sendRequest($targetStudentId, "👨‍🏫 **رد من المهندس فادي:**\n\n" . $replyText);
        }

        $this->sendRequest($this->adminChatId, "✅ تم إرسال الرد للطالب بنجاح.");
    }

    /**
     * دالة تقنية لإرسال الميديا (صور/ملفات) من الأدمن للطالب
     */
    private function sendMediaToStudent($studentId, $message, $caption)
    {
        $method = isset($message['document']) ? 'sendDocument' : 'sendPhoto';
        $fileId = isset($message['document']) ? $message['document']['file_id'] : end($message['photo'])['file_id'];
        $fileKey = isset($message['document']) ? 'document' : 'photo';

        $payload = [
            'chat_id' => $studentId,
            $fileKey => $fileId,
            'caption' => "👨‍🏫 **رد المهندس فادي:**\n\n" . $caption,
            'parse_mode' => 'Markdown'
        ];

        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/{$method}", $payload);
    }

    /**
     * دالة عامة لإرسال طلبات POST لتليجرام
     */
    private function sendRequest($chatId, $text, $buttons = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];
        
        if ($buttons) $payload['reply_markup'] = $buttons;

        $response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
        return $response->successful();
    }
}