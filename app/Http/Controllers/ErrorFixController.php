<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AI\ErrorFixAgent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ErrorFixController extends Controller
{
    // Max words allowed per request to avoid hitting Gemini MAX_TOKENS
    protected int $maxWords = 9000; // adjust based on your Gemini model limit

    public function fix(Request $request)
    {
        try {
            $errorText = $request->input('error', '');

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
            if ($this->isNonMeaningful($errorText)) {
                return response()->json([
                    'error' => 'Text is not meaningful. Provide a clear error message or code snippet.'
                ], 422);
            }

            // 4️⃣ Truncate input if too large
            $errorText = $this->truncateInput($errorText);

            // 5️⃣ Call the AI agent
            $agent = new ErrorFixAgent();
            $result = $agent->classifyAndFix($errorText);

            // 6️⃣ Check if AI returned an error message
            $decoded = json_decode($result, true);
            if ($decoded && isset($decoded['error'])) {
                return response()->json([
                    'error' => $decoded['error']
                ], 502);
            }

            // 7️⃣ Check if AI returned empty text
            if (empty(trim($result))) {
                return response()->json([
                    'error' => 'AI stopped early or returned no text. Try shorter input.'
                ], 502);
            }

            // 8️⃣ Success
           return response()->json(json_decode($result, true));


        } catch (\Exception $e) {
            Log::error('ErrorFixController failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Unexpected error occurred. Try again later.'
            ], 500);
        }
    }

    /**
     * Detect potentially dangerous code (basic sanitization)
     */
    protected function containsMaliciousCode(string $text): bool
    {
        $patterns = [
            '/<script.*?>.*?<\/script>/is',   // JS
            '/<\?php.*?\?>/is',               // PHP
            '/eval\s*\(.*\)/i',               // eval()
            '/base64_decode\s*\(/i',          // base64 hacks
            '/system\s*\(/i',                 // system commands
            '/rm\s+-rf/i',                    // destructive shell commands
            '/DROP\s+TABLE/i',                // SQL injection
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect non-meaningful input
     */
    protected function isNonMeaningful(string $text): bool
    {
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $text);
        return strlen($clean) < 3;
    }

    /**
     * Truncate input to avoid hitting MAX_TOKENS
     */
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
