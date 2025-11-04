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
            // ✅ Accept from Telex + manual tests
            $errorText = $request->input('event.text', '') ?: $request->input('error', '');

            // ✅ Empty
            if (trim($errorText) === '') {
                return response()->json(['error' => 'No error text provided.'], 400);
            }

            // ✅ Normalize message to avoid HTML detection from Telex
            $cleanText = trim(strip_tags($errorText));
            $lower = strtolower($cleanText);


            // ✅ Friendly greeting detection
            $greetings = [
                'hi',
                'hello',
                'hey',
                'help',
                'start',
                'good morning',
                'good afternoon',
                'good evening',
                'what can you do',
                'what are you',
                'who are you',
                'what are you configured to do'
            ];

            foreach ($greetings as $greet) {
                if (str_contains($lower, $greet)) {
                    return response()->json([
                        'message' => "👋 Hello! I am *ErrorFixer*.\n\n" .
                            "Send me any code error and I'll:\n" .
                            "• Detect the language\n" .
                            "• Identify the error\n" .
                            "• Explain the cause\n" .
                            "• Provide the correct fix\n\n" .
                            "Example:\n```\nPHP Fatal error: Call to undefined method User::fullname()\n```"
                    ]);
                }
            }

            // ✅ Block malicious content
            if ($this->containsMaliciousCode($errorText)) {
                return response()->json(['error' => 'Input contains unsafe or malicious content.'], 400);
            }

            // ✅ Avoid junk input
            if ($this->isNonMeaningful($errorText)) {
                return response()->json(['error' => 'Text is too short or unclear. Send an actual error.'], 422);
            }

            // ✅ Limit long text
            $errorText = $this->truncateInput($errorText);

            // ✅ Send to AI
            $agent = new ErrorFixAgent();
            $result = $agent->classifyAndFix($errorText);

            $decoded = json_decode($result, true);

            if (!$decoded) {
                return response()->json(['error' => 'AI returned invalid response.'], 502);
            }

            if (isset($decoded['error'])) {
                return response()->json(['error' => $decoded['error']], 502);
            }

            return response()->json($decoded);
        } catch (\Exception $e) {
            Log::error('ErrorFixController failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Unexpected server error. Try again.'], 500);
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
