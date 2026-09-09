<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Chat\AiProviderFactory;
use Illuminate\Console\Command;

/**
 * Tes langsung ke API provider AI (Anthropic/OpenAI), MELEWATI
 * seluruh alur widget chat -- supaya bisa memastikan apakah masalahnya
 * di kunci API/koneksi, atau di logika pemicu bot (enabled check,
 * status percakapan, dst).
 */
class AiChatTest extends Command
{
    protected $signature = 'lumora:ai-chat-test {pesan=Halo apa saja layanan yang tersedia}';

    protected $description = 'Tes langsung ke API AI chat bot tanpa lewat widget, untuk diagnosis';

    public function handle(): int
    {
        $provider = AiProviderFactory::make();
        $providerKey = Setting::get('ai_chat_provider', 'anthropic');
        $model = Setting::get("ai_chat_model_{$providerKey}") ?: $provider->defaultModel();

        $this->line("Provider : " . get_class($provider));
        $this->line("Model    : {$model}");
        $this->line("Pesan    : " . $this->argument('pesan'));
        $this->newLine();
        $this->line('Menghubungi API...');

        $result = $provider->chat(
            [['role' => 'user', 'content' => $this->argument('pesan')]],
            'Anda asisten chat untuk sebuah layanan hosting. Jawab singkat dalam Bahasa Indonesia.',
            $model
        );

        $this->newLine();

        if (! $result['success']) {
            $this->error('GAGAL: ' . $result['message']);
            $this->newLine();
            $this->line('Penyebab yang sering terjadi:');
            $this->line('  - API key salah/kadaluarsa/typo saat disalin');
            $this->line('  - Saldo/kredit API di akun Anthropic atau OpenAI habis');
            $this->line('  - Model yang dipilih tidak tersedia untuk akun ini');

            return self::FAILURE;
        }

        $this->info('BERHASIL');
        $this->newLine();
        $this->line('Balasan:');
        $this->line('  ' . $result['text']);
        $this->newLine();
        $this->line("Token: {$result['input_tokens']} masuk, {$result['output_tokens']} keluar");

        return self::SUCCESS;
    }
}
