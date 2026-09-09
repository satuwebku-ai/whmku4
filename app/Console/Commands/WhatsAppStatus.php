<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class WhatsAppStatus extends Command
{
    protected $signature = 'lumora:whatsapp-status';

    protected $description = 'Tampilkan kondisi pengaturan gateway WhatsApp untuk diagnosis';

    public function handle(): int
    {
        $provider = Setting::get('wa_provider', 'none');
        $token = Setting::get('wa_token');
        $endpoint = Setting::get('wa_endpoint');
        $aiEnabled = Setting::get('ai_chat_enabled');

        $this->line('── Gateway WhatsApp ──');
        $this->line("  wa_provider = " . ($provider ?: '(kosong)') . ($provider !== 'none' ? ' ✓' : ' ✗ HARUS diisi fonnte/wablas/custom'));
        $this->line("  wa_token    = " . ($token ? 'terisi (' . strlen($token) . ' karakter)' : 'KOSONG ✗'));

        if ($provider === 'wablas' || $provider === 'custom') {
            $this->line("  wa_endpoint = " . ($endpoint ?: '(kosong, memakai default)'));
        }

        $this->newLine();
        $this->line('── Bot AI ──');
        $this->line("  ai_chat_enabled = " . var_export($aiEnabled, true) . ($aiEnabled === '1' ? ' ✓' : ' ✗'));

        $this->newLine();
        $this->line('── Alamat Webhook (daftarkan ini di dashboard gateway) ──');
        $this->line('  ' . route('webhook.whatsapp'));

        $this->newLine();

        if ($provider === 'none') {
            $this->error('MASALAH: wa_provider masih "none" — atur dulu di Admin → Pengaturan → Live Chat → Notifikasi WhatsApp.');
        } elseif (blank($token)) {
            $this->error('MASALAH: wa_token kosong — pesan tidak akan bisa DIKIRIM (masuk mungkin tetap tercatat, tapi bot tidak bisa membalas).');
        } elseif ($aiEnabled !== '1') {
            $this->error('MASALAH: Bot AI belum aktif — pesan WhatsApp masuk akan tercatat tapi tidak dibalas otomatis.');
        } else {
            $this->info('Konfigurasi terlihat benar.');
        }

        $this->newLine();
        $this->line('Riwayat webhook yang PERNAH masuk (10 terakhir):');

        $log = storage_path('logs/laravel.log');
        if (! file_exists($log)) {
            $this->line('  (file log tidak ditemukan)');

            return self::SUCCESS;
        }

        $hits = [];
        $handle = fopen($log, 'r');
        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, 'WhatsApp webhook diterima') || str_contains($line, 'WhatsApp webhook:')) {
                $hits[] = trim($line);
            }
        }
        fclose($handle);

        if (empty($hits)) {
            $this->warn('  BELUM ADA sama sekali webhook yang masuk — gateway kemungkinan belum berhasil memanggil alamat webhook di atas.');
            $this->line('  Cek lagi apakah alamat webhook sudah didaftarkan dengan benar di dashboard Fonnte/Wablas.');
        } else {
            foreach (array_slice($hits, -10) as $hit) {
                $this->line('  ' . mb_strimwidth($hit, 0, 200, '…'));
            }
        }

        return self::SUCCESS;
    }
}
