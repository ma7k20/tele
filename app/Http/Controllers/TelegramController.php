<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    protected $token = "8793332771:AAHGpL83PANP6rUbdzsohV3z4vODwkpXLic"; // توكين البوت
    protected $adminChatId = "1464023815"; // الـ ID تبعك (الأدمن)

    public function handle(Request $request)
    {
        $data = $request->all();

        // استخراج الـ ID والبيانات بدقة
        if (isset($data['callback_query'])) {
            $chatId = $data['callback_query']['message']['chat']['id'];
            $callbackData = $data['callback_query']['data'];
            $this->handleButtons($chatId, $callbackData);
        } elseif (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            $text = $data['message']['text'] ?? '';

            if ($text == '/start') {
                $this->sendFireWelcome($chatId);
            } elseif ($chatId == $this->adminChatId && str_contains($text, 'reply:')) {
                $this->processAdminReply($text);
            } else {
                $this->forwardToAdmin($chatId, $text);
            }
        }
        return response()->json(['status' => 'success']);
    }

    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **UniTask | رفيقك نحو الامتياز**\n\n"
             . "أهلاً بك يا بطل! نحن لا نحل الواجبات فقط، بل نؤمن لك طريق الوصول لـ **+A** بكل سهولة. 🔥\n\n"
             . "⚡ **لماذا نحن؟**\n"
             . "🔹 فريق من المهندسين الخبراء.\n"
             . "🔹 ضمان علامة **80% فما فوق**.\n"
             . "🔹 سرعة في التنفيذ.. دقة في التسليم.\n\n"
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
            $response = "🔥 **انتهى وقت القلق!**\n\n"
                      . "📌 **حل الواجبات:**\n"
                      . "💸 السعر: **10 - 20 شيكل** فقط.\n"
                      . "⏳ الوقت: تسليم قياسي قبل الموعد.\n\n"
                      . "📥 **أرسل تفاصيل واجبك الآن (صورة أو نص)** وسيرد المهندس فادي عليك فوراً! ⚡";
        } elseif ($data == 'p_ex') {
            $response = "⚡ **جاهزون للاكتساح!**\n\n"
                      . "📌 **الاختبارات:**\n"
                      . "🔹 كويز: **10 شيكل**\n"
                      . "🔹 نصفي: **15 شيكل**\n"
                      . "🔹 نهائي: **20 شيكل**\n\n"
                      . "📥 **أرسل اسم المادة والموعد هلقيت**.. ودع الباقي للمهندسين! 🎯";
        } elseif ($data == 'p_pr') {
            $response = "💻 **Code Like a Pro | قسم المشاريع**\n\n"
                      . "بنحول تعقيد الأكواد لدرجات كاملة. بنغطي (Laravel, Java, Figma) وغيرهم.\n\n"
                      . "💰 **التكلفة:** تبدأ من **10 شيكل** وتصل لـ **80 شيكل**.\n"
                      . "🛡️ **الضمان:** كود نظيف وعلامة كاملة.\n\n"
                      . "📥 **أرسل ملف المتطلبات (Requirements) هان.. ونحن لها!** 🛠️";
        }
        $this->sendRequest($chatId, $response);
    }

    private function forwardToAdmin($studentId, $text)
    {
        if ($studentId == $this->adminChatId) return;

        $this->sendRequest($studentId, "✅ **تم استلام طلبك بنجاح!**\nالمهندس فادي قيد المراجعة الآن، انتظر الرد هنا خلال دقائق قليلة. ⏳");

        $adminNotice = "🚀 **طلب جديد " . date('H:i') . "**\n\n"
                     . "👤 **ID الطالب:** `{$studentId}`\n"
                     . "📝 **التفاصيل:** {$text}\n\n"
                     . "👇 **للرد المباشر:**\n"
                     . "`reply:{$studentId}:نص الرد`";

        $this->sendRequest($this->adminChatId, $adminNotice);
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
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown', // تفعيل التنسيق الناري (غامق ومائل)
        ];
        if ($buttons) $payload['reply_markup'] = $buttons;

        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }
}