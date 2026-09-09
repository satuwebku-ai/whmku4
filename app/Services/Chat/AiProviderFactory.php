<?php

namespace App\Services\Chat;

use App\Models\Setting;
use App\Services\Chat\Contracts\AiProviderInterface;
use App\Services\Chat\Providers\AnthropicProvider;
use App\Services\Chat\Providers\OpenAiProvider;
use InvalidArgumentException;

/**
 * SATU-SATUNYA tempat yang perlu diedit saat menambah provider AI
 * baru -- tambahkan class Provider baru (implementasikan
 * AiProviderInterface, lihat AnthropicProvider sebagai contoh), lalu
 * daftarkan satu baris di match() ini. AiChatService, ChatController,
 * dan halaman pengaturan semuanya sudah generik lewat interface ini.
 */
class AiProviderFactory
{
    public const PROVIDERS = [
        'anthropic' => 'Claude (Anthropic)',
        'openai' => 'ChatGPT (OpenAI)',
    ];

    public static function make(): AiProviderInterface
    {
        $provider = Setting::get('ai_chat_provider', 'anthropic');

        return match ($provider) {
            'anthropic' => new AnthropicProvider(),
            'openai' => new OpenAiProvider(),
            default => throw new InvalidArgumentException("Provider AI [{$provider}] tidak dikenali."),
        };
    }
}
