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
            // Detect input source
            $fromTelex = $request->has('event.text');

            // Get text from both possible sources
            $errorText = $request->input('event.text', '') ?: $request->input('error', '') ?: $request->input('message', '');

            if (trim($errorText) === '') {
                return $fromTelex
                    ? response("⚠️ Send an error message for me to analyze.", 200)
                    : response()->json(['error' => 'No error text provided.'], 400);
            }

            $cleanText = trim(strip_tags($errorText));
            $lower = strtolower($cleanText);

            // Greeting detection
            $greetings = [
                'hi', 'hello', 'hey', 'help', 'start', 'good morning', 'good afternoon',
                'good evening', 'what can you do', 'what are you', 'who are you',
                'what are you configured to do'
            ];

            foreach ($greetings as $greet) {
                if (str_contains($lower, $greet)) {

                    $textReply =
                        "👋 Hello! I am **ErrorFixer**.\n\n" .
                        "Send me any code error and I will:\n" .
                        "• Detect the language\n" .
                        "• Identify the error\n" .
                        "• Explain the cause\n" .
                        "• Provide the correct fix\n\n" .
                        "Try it now!";

                    return $fromTelex
                        ? response($textReply, 200)
                        : response()->json(['message' => strip_tags($textReply)], 200);
                }
            }

            // Reject unsafe input
            if ($this->containsMaliciousCode($errorText)) {
                return $fromTelex
                    ? response("❌ Unsafe or malicious code detected. Try again.", 200)
                    : response()->json(['error' => 'Malicious content detected.'], 400);
            }

            // Reject meaningless input
            if ($this->isNonMeaningful($errorText)) {
                return $fromTelex
                    ? response("⚠️ Please send a real code error or stack trace.", 200)
                    : response()->json(['error' => 'Input too short or unclear.'], 422);
            }

            // Limit extremely long messages
            $errorText = $this->truncateInput($errorText);

            // Process with AI agent
            $agent = new ErrorFixAgent();
            $result = $agent->classifyAndFix($errorText);

            $decoded = json_decode($result, true);

            if (!$decoded) {
                return $fromTelex
                    ? response("❗ I couldn't understand the error. Try again.", 200)
                    : response()->json(['error' => 'Invalid AI response.'], 502);
            }

            // ✅ If responding to Telex → Convert to human-readable text
            if ($fromTelex) {
                $plain =
                    "🧠 *Code Analysis*\n\n" .
                    "🔹 Language: " . ($decoded['language'] ?? 'Unknown') . "\n" .
                    "🔹 Error Type: " . ($decoded['error_type'] ?? 'Unknown') . "\n\n" .
                    "💡 Cause:\n" . ($decoded['cause'] ?? 'Not available') . "\n\n" .
                    "🔧 Fix:\n" . ($decoded['fix'] ?? 'Not provided') . "\n\n" .
                    "📌 Notes: " . ($decoded['notes'] ?? 'None');

                return response($plain, 200);
            }

            // ✅ If responding to Postman → Return clean JSON
            return response()->json($decoded, 200);

        } catch (\Exception $e) {
            Log::error('ErrorFixController failed', ['error' => $e->getMessage()]);

            return $request->has('event.text')
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
