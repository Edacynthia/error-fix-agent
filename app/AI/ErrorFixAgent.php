<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;

class ErrorFixAgent
{
    protected string $apiKey;
    protected string $model = 'gemini-2.5-flash';
    protected string $endpoint;

    // Gemini max output tokens
    protected int $maxOutputTokens = 2048;

    // Rough approximation: 1 word ≈ 1.5 tokens
    protected float $tokensPerWord = 1.5;

    // Safe max total tokens for prompt
    protected int $maxPromptTokens = 3000;

    public function __construct()
    {
        $gemini = config('laragent.providers.gemini');
        if (empty($gemini['api_key'] ?? null)) {
            throw new InvalidArgumentException('Gemini API key missing. Add GEMINI_API_KEY to .env');
        }

        $this->apiKey = $gemini['api_key'];
        $this->endpoint = "https://generativelanguage.googleapis.com/v1/models/{$this->model}:generateContent?key={$this->apiKey}";
    }

    public function classifyAndFix(string $errorText): string
    {
        $errorText = trim($errorText);
        if ($errorText === '') {
            return json_encode(['error' => 'No error text provided.']);
        }

        // Estimate tokens of input
        $words = str_word_count($errorText, 1);
        $estimatedTokens = count($words) * $this->tokensPerWord;

        // If input exceeds max prompt tokens, truncate dynamically
        if ($estimatedTokens > $this->maxPromptTokens) {
            $maxWords = (int) floor($this->maxPromptTokens / $this->tokensPerWord);
            $errorText = implode(' ', array_slice($words, 0, $maxWords)) . "\n... [truncated]";
        }

        $prompt = $this->buildPrompt($errorText);

        return $this->callGemini($prompt);
    }

    protected function buildPrompt(string $errorText): string
{
    return <<<PROMPT
You must respond with **ONLY** valid JSON. 
No explanations, no comments, no markdown, no surrounding text.

Return output in this exact JSON structure:

{
  "language": "Detected programming language",
  "error_type": "Type of error (syntax, runtime, logic, etc.)",
  "cause": "Short explanation of what caused the issue.",
  "fix": "Corrected version of the code or config.",
  "notes": "Advice to avoid the issue in the future."
}

If you cannot confidently detect language or fix, still return the JSON with best guess values.

Now analyze this error:

{$errorText}
PROMPT;
    }

    protected function callGemini(string $prompt): string
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint, [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $this->maxOutputTokens,
                        'temperature' => 0.2,
                    ],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                    ]
                ]);

            Log::info('Gemini Raw Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->failed()) {
                return json_encode(['error' => "Gemini error: " . $response->status()]);
            }

            $text = $this->extractText($response->json());

            if (!$text || strlen($text) < 20) {
                return json_encode(['error' => 'AI stopped early or returned no text. Try shorter input.']);
            }

            return $text;
        } catch (Exception $e) {
            Log::error('Gemini call failed', ['error' => $e->getMessage()]);
            return json_encode(['error' => 'Service unavailable.']);
        }
    }

   protected function extractText(?array $json): ?string
{
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) return null;

    // Remove surrounding ```json ... ``` or ``` ... ```
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));

    return trim($text);
}
}
