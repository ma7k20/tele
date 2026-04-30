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

        if (!$data) return response()->json(['status' => 'no data']);

        try {
            // 1. التعامل مع الأزرار (Callback Queries)
            if (isset($data['callback_query'])) {
                $chatId = $data['callback_query']['message']['chat']['id'];
                $callbackData = $data['callback_query']['data'];
                $this->handleButtons($chatId, $callbackData);
            } 
            
            // 2. التعامل مع الرسائل (Messages)
            elseif (isset($data['message'])) {
                $chatId = $data['message']['chat']['id'];
                $text = $data['message']['text'] ?? $data['message']['caption'] ?? '';

                if ($text == '/start') {
                    $this->sendFireWelcome($chatId);
                } 
                // معالجة رد الأدمن السريع
                elseif ($chatId == $this->adminChatId && str_starts_with($text, 'reply:')) {
                    $this->processAdminReply($text);
                } 
                // توجيه رسائل الطلاب للأدمن
                else {
                    $this->forwardToAdmin($chatId, $data['message']);
                }
            }
        } catch (\Exception $e) {
            Log::error("Telegram Bot Error: " . $e->getMessage());
        }

        return response()->json(['status' => 'success']);
    }

    private function sendFireWelcome($chatId)
    {
        $msg = "🎓 **أهلاً بك في UniTask | مساعدك الأكاديمي**\n\n"
             . "أنا هنا لأخفف عنك ضغط الدراسة وأضمن لك الدرجة الكاملة بإذن الله. ✨\n\n"
             . "👇 **ماذا نحجز لك اليوم؟**";

        $buttons = [
            'inline_keyboard' => [
                [['text' => '📚 حل واجبات', 'callback_data' => 'p_hw'], ['text' => '📝 اختبارات (كويزات)', 'callback_data' => 'p_ex']],
                [['text' => '💻 مشاريع برمجية', 'callback_data' => 'p_pr']],
                [['text' => '📞 تواصل مباشر مع المهندس', 'url' => 'https://t.me/fadi_alaa']] // رابط حسابك الشخصي مباشرة
            ]
        ];
        $this->sendRequest($chatId, $msg, $buttons);
    }

    private function handleButtons($chatId, $data)
    {
        $msg = "";
        if ($data == 'p_hw') {
            $msg = "📝 **قسم الواجبات:**\n\nيرجى إرسال ملف الواجب أو تصوير الأسئلة بوضوح.\n💰 السعر التقريبي: **10 - 25 شيكل** حسب الصعوبة.";
        } elseif ($data == 'p_ex') {
            $msg = "⚡ **قسم الاختبارات:**\n\nأرسل لنا (المادة، الوقت، ونماذج سابقة إن وجد).\n🔹 كويز: **10** | نصفي: **20** | نهائي: **30+**";
        } elseif ($data == 'p_pr') {
            $msg = "💻 **المشاريع الهندسية والبرمجية:**\n\nأرسل ملف المتطلبات (Requirements) وسنقوم بمراجعته فوراً.\n💰 السعر: يحدد بعد المعاينة.";
        }
        
        $this->sendRequest($chatId, "✅ تم اختيار القسم.\n\n" . $msg . "\n\n📥 **أنا بانتظار ملفاتك الآن..**");
    }

    private function forwardToAdmin($studentId, $message)
    {
        if ($studentId == $this->adminChatId) return;

        $text = $message['text'] ?? $message['caption'] ?? '(مرفق ميديا أو ملف)';

        // تأكيد الاستلام للطالب
        $this->sendRequest($studentId, "⏳ **جاري المعالجة..**\n\nتم إرسال طلبك للمهندس فادي بنجاح. سيصلك الرد هنا مع السعر خلال دقائق قليلة.");

        // إشعار للأدمن بتنسيق جديد يسهل الفصل بين المستخدمين
        $adminNotice = "🔔 **طلب جديد وصلك!**\n"
                     . "────────────────\n"
                     . "👤 **ID الطالب:** `{$studentId}`\n"
                     . "💬 **الوصف:** {$text}\n"
                     . "────────────────\n"
                     . "👇 **للرد السريع (انسخ القالب بالضغط عليه):**\n\n"
                     . "`reply:{$studentId}:أهلاً بك، تكلفة الحل هي .. شيكل. هل نعتمد؟`";

        $this->sendRequest($this->adminChatId, $adminNotice);

        // توجيه الميديا (صور/ملفات) للأدمن
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
        // تقسيم النص بناءً على ":" مع مراعاة وجود نص الرد الذي قد يحتوي على ":"
        $parts = explode(':', $text, 3);
        
        if (count($parts) === 3) {
            $targetStudentId = trim($parts[1]);
            $replyMessage = trim($parts[2]);

            $status = $this->sendRequest($targetStudentId, "👨‍🏫 **رد من المهندس فادي:**\n\n" . $replyMessage);
            
            if ($status) {
                $this->sendRequest($this->adminChatId, "✅ تم إرسال الرد للطالب (`$targetStudentId`) بنجاح.");
            } else {
                $this->sendRequest($this->adminChatId, "❌ فشل إرسال الرد. تأكد من أن الطالب لم يقم بحظر البوت.");
            }
        }
    }

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