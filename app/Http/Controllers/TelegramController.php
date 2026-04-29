<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TelegramController extends Controller
{
    // ملاحظة: يفضل وضع هذه البيانات في ملف .env للأمان
    protected $token = "8793332771:AAHGpL83PANP6rUbdzsohV3z4vODwkpXLic"; 
    protected $adminChatId = "1464023815"; 

    public function handle(Request $request)
    {
        $data = $request->all();

        // 1. التعامل مع ضغطات الأزرار (Callback Queries)
        if (isset($data['callback_query'])) {
            $chatId = $data['callback_query']['message']['chat']['id'];
            $callbackData = $data['callback_query']['data'];
            $this->handleButtons($chatId, $callbackData);
        } 
        
        // 2. التعامل مع الرسائل النصية
        elseif (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            $text = $data['message']['text'] ?? '';

            if ($text == '/start') {
                // تصفير حالة المستخدم عند البدء من جديد
                Cache::forget("user_state_{$chatId}");
                $this->sendFireWelcome($chatId);
            } 
            elseif ($chatId == $this->adminChatId && str_contains($text, 'reply:')) {
                $this->processAdminReply($text);
            } 
            else {
                // إرسال الرسالة للأدمن مع معرفة القسم المختار
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
        $sectionName = "";

        if ($data == 'p_hw') {
            $sectionName = "📚 قسم الواجبات";
            $response = "🔥 **انتهى وقت القلق!**\n\n"
                      . "📌 **حل الواجبات:**\n"
                      . "💸 السعر: **10 - 20 شيكل** فقط.\n"
                      . "⏳ الوقت: تسليم قياسي قبل الموعد.\n\n"
                      . "📥 **أرسل تفاصيل واجبك الآن (صورة أو نص)** وسيرد المهندس فادي عليك فوراً! ⚡";
        } elseif ($data == 'p_ex') {
            $sectionName = "📝 قسم الاختبارات";
            $response = "⚡ **جاهزون للاكتساح!**\n\n"
                      . "📌 **الاختبارات:**\n"
                      . "🔹 كويز: **10 شيكل**\n"
                      . "🔹 نصفي: **15 شيكل**\n"
                      . "🔹 نهائي: **20 شيكل**\n\n"
                      . "📥 **أرسل اسم المادة والموعد هلقيت**.. ودع الباقي للمهندسين! 🎯";
        } elseif ($data == 'p_pr') {
            $sectionName = "💻 المشاريع البرمجية";
            $response = "💻 **Code Like a Pro | قسم المشاريع**\n\n"
                      . "بنحول تعقيد الأكواد لدرجات كاملة.\n\n"
                      . "💰 **التكلفة:** تبدأ من **10 شيكل** وتصل لـ **80 شيكل**.\n"
                      . "🛡️ **الضمان:** كود نظيف وعلامة كاملة.\n\n"
                      . "📥 **أرسل ملف المتطلبات (Requirements) هان.. ونحن لها!** 🛠️";
        }

        // حفظ القسم الذي اختاره المستخدم في الكاش لمدة ساعة واحدة
        if ($sectionName != "") {
            Cache::put("user_state_{$chatId}", $sectionName, 3600);
        }

        $this->sendRequest($chatId, $response);
    }

    private function forwardToAdmin($studentId, $text)
    {
        if ($studentId == $this->adminChatId) return;

        // جلب اسم القسم الذي اختاره الطالب من الذاكرة (Cache)
        $selectedSection = Cache::get("user_state_{$studentId}", "غير محدد (رسالة مباشرة)");

        $this->sendRequest($studentId, "✅ **تم استلام طلبك بنجاح!**\nالمهندس فادي قيد المراجعة الآن، انتظر الرد هنا خلال دقائق قليلة. ⏳");

        $adminNotice = "🚀 **طلب جديد " . date('H:i') . "**\n"
                     . "📂 **القسم:** {$selectedSection}\n" // هنا سيظهر للأدمن من أين جاء المستخدم
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
            $targetId = trim($parts[1]);
            $replyMsg = trim($parts[2]);
            
            $this->sendRequest($targetId, "👨‍🏫 **رد من المهندس:**\n\n" . $replyMsg);
            $this->sendRequest($this->adminChatId, "✅ تم إرسال ردك للطالب.");
        }
    }

    private function sendRequest($chatId, $text, $buttons = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];
        if ($buttons) $payload['reply_markup'] = $buttons;

        Http::withoutVerifying()->post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
    }
}