<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AI\ErrorFixAgent;
use Illuminate\Support\Facades\Log;

class ErrorFixController extends Controller
{
    protected int $maxWords = 9000; // Safe limit

    public function fix(Request $request)
    {
        try {
            /**
             * ✅ Get message from both:
             * - Telex → event.text
             * - Postman → error
             */
            $errorText = $request->input('event.text', '');
            if (trim($errorText) === '') {
                $errorText = $request->input('error', '');
            }

            // 1️⃣ Empty input
            if (trim($errorText) === '') {
                return response()->json([
                    'error' => 'No error text provided.'
                ], 400);
            }

            // 2️⃣ Malicious input detection
            if ($this->containsMaliciousCode($errorText)) {
                return response()->json([
                    'error' => 'Input contains unsafe or malicious content.'
                ], 400);
            }

            // 3️⃣ Non-meaningful input
            // 3️⃣ Friendly greeting or help requests
            $lower = strtolower($errorText);
            $greetings = ['hi', 'hello', 'hey', 'help', 'what can you do', 'what are you', 'who are you', 'what are you configured to do', 'start'];

            foreach ($greetings as $greet) {
                if (str_contains($lower, $greet)) {
                    return response()->json([
                        'message' => "👋 Hello! I am *ErrorFixer*.\n\nPaste any code error and I will return:\n\n- Detected programming language\n- Error type\n- Clear explanation of the cause\n- Corrected code or step-by-step fix\n- Advice to avoid it again\n\nExample:\n```\nPHP Fatal error: Call to undefined method User::fullname()\n```\nJust send it to me — I will fix it 😄"
                    ]);
                }
            }


            // 4️⃣ Limit length
            $errorText = $this->truncateInput($errorText);

            // 5️⃣ Send to AI agent
            $agent = new ErrorFixAgent();
            $result = $agent->classifyAndFix($errorText);

            // 6️⃣ Validate result
            $decoded = json_decode($result, true);

            if (!$decoded) {
                return response()->json([
                    'error' => 'AI returned invalid JSON.'
                ], 502);
            }

            if (isset($decoded['error'])) {
                return response()->json([
                    'error' => $decoded['error']
                ], 502);
            }

            return response()->json($decoded);
        } catch (\Exception $e) {
            Log::error('ErrorFixController failed', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Unexpected error occurred. Try again later.'
            ], 500);
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
            if (preg_match($pattern, $text)) {
                return true;
            }
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
