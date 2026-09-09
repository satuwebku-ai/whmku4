<?php

namespace App\Services\Chat\Providers;

use App\Models\Setting;
use App\Services\Chat\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiProvider implements AiProviderInterface
{
    public function defaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    public function chat(array $messages, string $systemPrompt, string $model): array
    {
        $apiKey = Setting::get('ai_chat_openai_api_key');

        if (blank($apiKey)) {
            return ['success' => false, 'text' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'message' => 'API Key OpenAI belum diisi.'];
        }

        // OpenAI menaruh system prompt sebagai pesan pertama berperan
        // "system", beda dari Anthropic yang punya parameter terpisah
        // -- ini satu-satunya perbedaan struktur permintaan yang berarti
        // untuk percakapan teks biasa seperti ini.
        $payload = [
            'model' => $model,
            'max_tokens' => 500,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            ),
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(25)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (! $response->successful()) {
                $err = $response->json('error.message') ?? "HTTP {$response->status()}";
                Log::warning('AI chat bot (OpenAI): API menolak', ['status' => $response->status(), 'body' => $response->json()]);

                return ['success' => false, 'text' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'message' => $err];
            }

            $text = $response->json('choices.0.message.content');
            $usage = $response->json('usage', []);

            return [
                'success' => true,
                'text' => $text ? trim($text) : null,
                // OpenAI menamainya prompt_tokens/completion_tokens --
                // dipetakan ke nama generik input/output supaya
                // AiChatService & tabel pemakaian tidak perlu tahu
                // bedanya per provider.
                'input_tokens' => $usage['prompt_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? 0,
                'message' => 'OK',
            ];
        } catch (Throwable $e) {
            Log::warning('AI chat bot (OpenAI): gagal menghubungi API — ' . $e->getMessage());

            return ['success' => false, 'text' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'message' => $e->getMessage()];
        }
    }
}
