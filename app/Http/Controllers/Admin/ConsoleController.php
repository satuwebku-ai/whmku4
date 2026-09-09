<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ConsoleController extends Controller
{
    /**
     * Daftar putih perintah yang boleh dijalankan lewat browser — SENGAJA
     * cuma yang aman (tidak menghapus data, tidak mengubah struktur mundur).
     * Perintah berbahaya (migrate:fresh, migrate:rollback, db:wipe,
     * db:seed) SENGAJA tidak dimasukkan — kalau memang perlu, itu wajib
     * lewat Terminal/SSH sungguhan, bukan cukup klik tombol di browser.
     */
    private const ALLOWED_COMMANDS = [
        'migrate' => 'Terapkan migrasi database yang belum jalan (aman — cuma menambah, tidak menghapus)',
        'optimize:clear' => 'Bersihkan semua cache (config, route, view, dll) — jalankan ini kalau ada perubahan terasa belum muncul',
        'config:clear' => 'Bersihkan cache konfigurasi saja',
        'view:clear' => 'Bersihkan cache tampilan (blade) saja',
        'route:clear' => 'Bersihkan cache rute saja',
        'lumora:check' => 'Cek kesehatan setup — tabel/kolom yang mungkin belum lengkap',
        'lumora:test-mail' => 'Kirim email uji coba (butuh alamat email sebagai argumen)',
        'lumora:generate-renewal-invoices' => 'Buat invoice perpanjangan yang sudah jatuh tempo H-7 (jalan otomatis tiap hari, ini cuma untuk uji manual)',
        'lumora:suspend-overdue' => 'Suspend layanan yang telat bayar (jalan otomatis tiap hari, ini cuma untuk uji manual)',
        'lumora:expire-privacy' => 'Matikan ID Protection yang habis masa berlakunya (jalan otomatis tiap hari, ini cuma untuk uji manual)',
        'lumora:send-reminders' => 'Kirim pengingat tagihan (jalan otomatis tiap hari, ini cuma untuk uji manual)',
        'lumora:backup' => 'Buat cadangan database + file sekarang juga (sama seperti tombol di halaman Backup)',
        'lumora:liquid-prices' => 'Sinkronkan ulang harga TLD dari Liqu.id',
        'lumora:inspect-hosting' => 'Lihat status provisioning hosting account (tanpa tinker)',
        'lumora:inspect-invoice' => 'Lihat nilai amount/tax/discount/total sebuah invoice (tanpa tinker)',
        'lumora:seed-legal-drafts' => 'Isi draf awal Syarat & Ketentuan + Kebijakan Privasi',
    ];

    /**
     * Perintah yang mendukung mode simulasi (--dry) — tidak benar-benar
     * mengubah apa pun, cuma menunjukkan APA yang AKAN terjadi. Dicentang
     * secara default di tampilan supaya klik pertama selalu aman dulu.
     */
    private const SUPPORTS_DRY_RUN = [
        'lumora:generate-renewal-invoices',
        'lumora:suspend-overdue',
        'lumora:send-reminders',
        'lumora:expire-privacy',
    ];

    public function index(): View
    {
        return view('admin.console.index', [
            'commands' => self::ALLOWED_COMMANDS,
            'dryRunCommands' => self::SUPPORTS_DRY_RUN,
        ]);
    }

    public function indexBootstrap(): View
    {
        return view('admin.console.index', [
            'commands' => self::ALLOWED_COMMANDS,
            'dryRunCommands' => self::SUPPORTS_DRY_RUN,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string'],
            'argument' => ['nullable', 'string', 'max:255'],
        ]);

        if (! array_key_exists($data['command'], self::ALLOWED_COMMANDS)) {
            return back()->with('error', 'Perintah tidak dikenali atau tidak diizinkan.');
        }

        $params = [];

        // Satu-satunya perintah di daftar ini yang butuh argumen adalah
        // test-mail (alamat email tujuan) — ditangani khusus, bukan
        // argumen bebas untuk semua perintah (supaya tidak disalahgunakan
        // untuk menyelipkan opsi yang tidak dimaksudkan).
        if ($data['command'] === 'lumora:test-mail') {
            if (blank($data['argument'] ?? null)) {
                return back()->with('error', 'Isi dulu alamat email tujuan untuk uji kirim email.');
            }

            $params = ['email' => $data['argument']];
        }

        if (in_array($data['command'], self::SUPPORTS_DRY_RUN, true) && $request->boolean('dry', false)) {
            $params['--dry'] = true;
        }

        try {
            Artisan::call($data['command'], $params);
            $output = Artisan::output();
        } catch (\Throwable $e) {
            return back()->with('error', 'Perintah gagal dijalankan: ' . $e->getMessage());
        }

        return back()->with('success', 'Perintah selesai dijalankan.')->with('output', $output);
    }
}
