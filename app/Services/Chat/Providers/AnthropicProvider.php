<?php

namespace App\Services\Chat\Providers;

use App\Models\Setting;
use App\Services\Chat\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnthropicProvider implements AiProviderInterface
{
    public function defaultModel(): string
    {
        return 'claude-sonnet-4-6';
    }

    public function chat(array $messages, string $systemPrompt, string $model): array
    {
        $apiKey = Setting::get('ai_chat_api_key');

        if (blank($apiKey)) {
            return ['success' => false, 'text' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'message' => 'API Key Anthropic belum diisi.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(25)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 500,
                    'system' => $systemPrompt,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                $err = $response->json('error.message') ?? "HTTP {$response->status()}";
                Log::warning('AI chat bot (Anthropic): API menolak', ['status' => $response->status(), 'body' => $response->json()]);

                return ['success' => false, 'text' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'message' => $err];
            }

            $text = collect($response->json('content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            $usage = $response->json('usage', []);

            return [
                'success' => true,
                'text' => trim($text) ?: null,
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'message' => 'OK',
            ];
        } catch (Throwable $e) {
            Log::warning('AI chat bot (Anthropic): gagal menghubungi API — ' . $e->getMessage());

            return ['success' => false, 'text' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'message' => $e->getMessage()];
        }
    }
}
