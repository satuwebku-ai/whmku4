<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Cek kondisi pengaturan bot chat AI -- dibuat karena `php artisan
 * tinker` butuh eval() yang sering diblokir kebijakan keamanan shared
 * hosting (CloudLinux dkk), jadi command Artisan biasa (bukan REPL)
 * ini jadi cara diagnosis yang bisa diandalkan di lingkungan seperti
 * itu.
 */
class AiChatStatus extends Command
{
    protected $signature = 'lumora:ai-chat-status';

    protected $description = 'Tampilkan kondisi pengaturan bot chat AI untuk diagnosis';

    public function handle(): int
    {
        $enabled = Setting::get('ai_chat_enabled');
        $liveChatProvider = Setting::get('livechat_provider');
        $aiProvider = Setting::get('ai_chat_provider', 'anthropic');
        $anthropicKey = Setting::get('ai_chat_api_key');
        $openaiKey = Setting::get('ai_chat_openai_api_key');

        $this->line('── Provider Live Chat ──');
        $this->line("  livechat_provider = " . ($liveChatProvider ?: '(kosong)') . ($liveChatProvider === 'widget' ? ' ✓ benar' : ' ✗ HARUS "widget" supaya widget chat tampil'));

        $this->newLine();
        $this->line('── Bot AI ──');
        $this->line("  ai_chat_enabled   = " . var_export($enabled, true) . ($enabled === '1' ? ' ✓' : ' ✗ HARUS string "1"'));
        $this->line("  ai_chat_provider  = {$aiProvider}");
        $this->line("  Anthropic API key = " . ($anthropicKey ? 'terisi (' . strlen($anthropicKey) . ' karakter)' : 'KOSONG'));
        $this->line("  OpenAI API key    = " . ($openaiKey ? 'terisi (' . strlen($openaiKey) . ' karakter)' : 'KOSONG'));

        $this->newLine();

        $keyDipakai = $aiProvider === 'openai' ? $openaiKey : $anthropicKey;
        $namaKeyDipakai = $aiProvider === 'openai' ? 'OpenAI' : 'Anthropic';

        if ($liveChatProvider !== 'widget') {
            $this->error('MASALAH: livechat_provider bukan "widget" — widget chat custom tidak dirender sama sekali di halaman.');
        } elseif ($enabled !== '1') {
            $this->error('MASALAH: ai_chat_enabled bukan "1" — toggle "Aktif" belum benar-benar tersimpan sebagai aktif.');
        } elseif (blank($keyDipakai)) {
            $this->error("MASALAH: Provider aktif adalah \"{$aiProvider}\", tapi API key {$namaKeyDipakai}-nya KOSONG. Ini yang membuat bot diam tanpa mencatat log sama sekali.");
        } else {
            $this->info('Konfigurasi terlihat benar — kalau bot tetap tidak membalas, kemungkinan API key-nya ditolak Anthropic/OpenAI (cek kembali dengan --dry di bawah).');
        }

        $this->newLine();
        $this->line('Untuk tes langsung kirim pesan uji ke API (tanpa lewat widget chat), jalankan:');
        $this->line('  php artisan lumora:ai-chat-test "Halo, apa saja layanan yang tersedia?"');

        return self::SUCCESS;
    }
}
