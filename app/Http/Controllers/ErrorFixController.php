<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AI\ErrorFixAgent;
use Illuminate\Support\Facades\Log;

class ErrorFixController extends Controller
{
    protected int $maxWords = 9000;

    public function fix(Request $request)
    {
        try {

            // ✅ Detect if request came from Telex (very reliable)
            $fromTelex = $request->hasHeader('X-Telex-Event') 
                        || $request->has('event') 
                        || $request->input('event.message.text');

            // ✅ Correctly extract user text no matter the format
            $errorText =
                $request->input('event.message.text') ??
                $request->input('event.text') ??
                $request->input('text') ??
                $request->input('error') ??
                $request->input('message') ??
                $request->getContent() ??
                '';

            $errorText = trim($errorText);

            if ($errorText === '') {
                return $fromTelex
                    ? response("⚠️ Send me an error message to analyze.", 200)
                    : response()->json(['error' => 'No error text provided.'], 400);
            }

            // ✅ Normalize for logic checks
            $cleanText = trim(strip_tags($errorText));
            $lower = strtolower($cleanText);

            // ✅ Greetings / Assistant Intro
            $greetings = [
                'hi', 'hello', 'hey', 'help', 'start',
                'good morning', 'good afternoon', 'good evening',
                'what can you do', 'what are you', 'who are you', 'about you',
                'what are you configured to do'
            ];

            foreach ($greetings as $greet) {
                if (str_contains($lower, $greet)) {

                    $reply =
"👋 Hello! I am *ErrorFixer*.

Send me *any code error* and I will:
• Detect the language
• Identify the error
• Explain the cause
• Provide the correct fix

Example:
```
PHP Fatal error: Call to undefined method User::fullname()
```";

                    return $fromTelex
                        ? response($reply, 200)
                        : response()->json(['message' => strip_tags($reply)], 200);
                }
            }

            // ✅ Unsafe input protection
            if ($this->containsMaliciousCode($cleanText)) {
                return $fromTelex
                    ? response("❌ Unsafe code detected. Try again.", 200)
                    : response()->json(['error' => 'Malicious content detected.'], 400);
            }

            // ✅ Reject gibberish
            if ($this->isNonMeaningful($cleanText)) {
                return $fromTelex
                    ? response("⚠️ Please send a real error message.", 200)
                    : response()->json(['error' => 'Input too short or unclear.'], 422);
            }

            // ✅ Limit overly long messages
            $errorText = $this->truncateInput($errorText);

            // ✅ Send to AI Agent
            $agent = new ErrorFixAgent();
            $result = $agent->classifyAndFix($errorText);
            $decoded = json_decode($result, true);

            if (!$decoded) {
                return $fromTelex
                    ? response("❗ I couldn't understand the error.", 200)
                    : response()->json(['error' => 'Invalid AI response.'], 502);
            }

            // ✅ Return text for Telex
            if ($fromTelex) {
                $plain =
"🧠 *Code Analysis*

🔹 Language: {$decoded['language']}
🔹 Error Type: {$decoded['error_type']}

💡 Cause:
{$decoded['cause']}

🔧 Fix:
{$decoded['fix']}

📌 Notes:
{$decoded['notes']}";

                return response($plain, 200);
            }

            // ✅ Postman gets JSON
            return response()->json($decoded, 200);

        } catch (\Exception $e) {
            Log::error('ErrorFixController failed', ['error' => $e->getMessage()]);

            return $fromTelex
                ? response("❗ Server error. Try again later.", 200)
                : response()->json(['error' => 'Server error.'], 500);
        }
    }

    protected function containsMaliciousCode(string $text): bool
    {
        $patterns = [
            '/<script.*?>.*?<\/script>/is',
            '/<\?php.*?\?>/is',
            '/eval\s*\(.*\)/i',
            '/base64_decode\s*\(/i',
            '/system\s*\(/i',
            '/rm\s+-rf/i',
            '/DROP\s+TABLE/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) return true;
        }
        return false;
    }

    protected function isNonMeaningful(string $text): bool
    {
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $text);
        return strlen($clean) < 3;
    }

    protected function truncateInput(string $text): string
    {
        $words = str_word_count($text, 2);
        if (count($words) > $this->maxWords) {
            $words = array_slice($words, 0, $this->maxWords);
            $text = implode(' ', $words);
        }
        return $text;
    }
}
