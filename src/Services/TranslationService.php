<?php

namespace Nktlksvch\BulbaKit\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Translation Service
 *
 * Handles AI-powered text translation for both static UI strings
 * and dynamic database content (spatie/laravel-translatable).
 *
 * Uses OpenAI-compatible API (OpenRouter, OpenAI, etc.) configured
 * in config('bulba.translation').
 */
class TranslationService
{
    /**
     * Translate a single text string to multiple target locales.
     *
     * @param  string  $text  Source text
     * @param  string  $fromLocale  Source locale (e.g., 'en')
     * @param  array<int, string>  $toLocales  Target locales (e.g., ['ru', 'de'])
     * @return array<string, string> Translations keyed by locale
     */
    public function translate(string $text, string $fromLocale, array $toLocales): array
    {
        $config = config('bulba.translation');

        if (! ($config['ai_enabled'] ?? false)) {
            return [];
        }

        $apiKey = $config['ai_api_key'] ?? '';
        if (empty($apiKey)) {
            return [];
        }

        $provider = $config['ai_provider'] ?? 'openrouter';
        $model = $config['ai_model'] ?? 'gpt-4';

        $targetLocales = implode(', ', $toLocales);

        $prompt = <<<PROMPT
Translate the following text from "{$fromLocale}" to these locales: {$targetLocales}.

Return ONLY a valid JSON object with locale codes as keys and translations as values.
Example: {"ru": "Перевод", "de": "Übersetzung"}

Text to translate:
"{$text}"
PROMPT;

        try {
            $response = $this->callAiApi($provider, $apiKey, $model, $prompt);

            $translations = json_decode($response, true);

            if (! is_array($translations)) {
                return [];
            }

            // Filter to only requested locales
            return array_intersect_key($translations, array_flip($toLocales));
        } catch (\Throwable $e) {
            Log::warning('TranslationService: AI translation failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Translate multiple texts to multiple target locales in a single call.
     *
     * More efficient than calling translate() in a loop.
     *
     * @param  array<int, string>  $texts  Source texts
     * @param  string  $fromLocale  Source locale
     * @param  array<int, string>  $toLocales  Target locales
     * @return array<string, array<string, string>> Translations keyed by text then locale
     */
    public function translateBatch(array $texts, string $fromLocale, array $toLocales): array
    {
        $config = config('bulba.translation');

        if (! ($config['ai_enabled'] ?? false)) {
            return [];
        }

        $apiKey = $config['ai_api_key'] ?? '';
        if (empty($apiKey)) {
            return [];
        }

        $provider = $config['ai_provider'] ?? 'openrouter';
        $model = $config['ai_model'] ?? 'gpt-4';
        $targetLocales = implode(', ', $toLocales);

        $textsJson = json_encode($texts, JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Translate the following texts from "{$fromLocale}" to these locales: {$targetLocales}.

Return ONLY a valid JSON object where each key is the original text and the value is an object of locale translations.
Example: {"Hello": {"ru": "Привет", "de": "Hallo"}, "World": {"ru": "Мир", "de": "Welt"}}

Texts to translate:
{$textsJson}
PROMPT;

        try {
            $response = $this->callAiApi($provider, $apiKey, $model, $prompt);

            $result = json_decode($response, true);

            if (! is_array($result)) {
                return [];
            }

            // Filter to only requested locales
            $filtered = [];
            foreach ($result as $text => $translations) {
                if (is_array($translations)) {
                    $filtered[$text] = array_intersect_key($translations, array_flip($toLocales));
                }
            }

            return $filtered;
        } catch (\Throwable $e) {
            Log::warning('TranslationService: AI batch translation failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Call the AI API (OpenAI-compatible).
     *
     * @param  string  $provider  Provider name (openrouter, openai)
     * @param  string  $apiKey  API key
     * @param  string  $model  Model name
     * @param  string  $prompt  User prompt
     * @return string AI response content
     */
    protected function callAiApi(string $provider, string $apiKey, string $model, string $prompt): string
    {
        $url = $this->resolveApiUrl($provider);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a professional translator. Return only valid JSON, no explanations.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
        ]);

        $response->throw();

        $body = $response->json();

        return $body['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Resolve the API URL for the given provider.
     */
    protected function resolveApiUrl(string $provider): string
    {
        return match ($provider) {
            'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
            'openai' => 'https://api.openai.com/v1/chat/completions',
            default => 'https://openrouter.ai/api/v1/chat/completions',
        };
    }
}
