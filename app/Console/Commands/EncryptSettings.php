<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Mengenkripsi rahasia yang sebelumnya tersimpan sebagai teks biasa di tabel
 * settings (Setting::SECRET_KEYS). Nilai baru otomatis terenkripsi saat
 * disimpan; command ini hanya untuk nilai lama. Aman diulang.
 */
class EncryptSettings extends Command
{
    protected $signature = 'lumora:encrypt-settings';

    protected $description = 'Enkripsi token/secret di tabel settings yang masih tersimpan sebagai teks biasa';

    public function handle(): int
    {
        $count = 0;

        foreach (Setting::whereIn('key', Setting::SECRET_KEYS)->where('is_encrypted', false)->get() as $row) {
            if (blank($row->value)) {
                continue;
            }

            $row->update(['value' => encrypt($row->value), 'is_encrypted' => true]);
            $this->line("  dienkripsi: {$row->key}");
            $count++;
        }

        $this->info($count === 0 ? 'Tidak ada rahasia yang perlu dienkripsi.' : "{$count} rahasia berhasil dienkripsi.");

        return self::SUCCESS;
    }
}
