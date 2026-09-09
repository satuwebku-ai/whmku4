<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Akses cepat ke cPanel server tempat APLIKASI LUMORA INI SENDIRI
 * berjalan -- beda dari SSO client (login otomatis ke cPanel LAYANAN
 * klien, butuh akses WHM/reseller).
 *
 * Server aplikasi ini adalah akun cPanel BIASA (bukan WHM/reseller),
 * jadi login OTOMATIS lewat API tidak bisa dipakai di sini -- cPanel
 * sendiri tidak punya cara resmi untuk "SSO diri sendiri" tanpa akses
 * WHM di atasnya. Solusinya: simpan kredensial (password terenkripsi
 * sama seperti api_token server), tampilkan untuk disalin manual, dan
 * sediakan Akses Cepat berupa TAUTAN LANGSUNG ke halaman-halaman
 * cPanel yang sering dipakai -- begitu login manual sekali di browser,
 * tautan-tautan ini bisa dipakai berulang tanpa cPanel meminta login
 * lagi (selama sesi browser masih aktif).
 */
class SelfCpanelController extends Controller
{
    /**
     * Sama persis daftar shortcut yang dipakai halaman Layanan Saya
     * klien -- supaya konsisten, dan admin sudah familiar polanya.
     */
    public const SHORTCUTS = [
        ['label' => 'Email Accounts',  'icon' => 'fa-envelope',    'path' => 'frontend/jupiter/email/email_accounts.html'],
        ['label' => 'Forwarders',      'icon' => 'fa-share',       'path' => 'frontend/jupiter/email/email_forwarders.html'],
        ['label' => 'File Manager',    'icon' => 'fa-folder-open', 'path' => 'frontend/jupiter/filemanager/index.html'],
        ['label' => 'Backup',          'icon' => 'fa-database',    'path' => 'frontend/jupiter/backup/index.html'],
        ['label' => 'Domains',         'icon' => 'fa-globe',       'path' => 'frontend/jupiter/domains/index.html'],
        ['label' => 'MySQL Databases', 'icon' => 'fa-server',      'path' => 'frontend/jupiter/sql/index.html'],
        ['label' => 'phpMyAdmin',      'icon' => 'fa-table-cells', 'path' => '3rdparty/phpMyAdmin/index.php'],
        ['label' => 'Cron Jobs',       'icon' => 'fa-clock',       'path' => 'frontend/jupiter/cron/index.html'],
        ['label' => 'SSH Access',      'icon' => 'fa-terminal',    'path' => 'frontend/jupiter/ssh/index.html'],
        ['label' => 'Error Log',       'icon' => 'fa-triangle-exclamation', 'path' => 'frontend/jupiter/logs/errors.html'],
        ['label' => 'Awstats',         'icon' => 'fa-chart-line',  'path' => 'frontend/jupiter/stats/awstats_landing.html'],
        ['label' => 'SSL/TLS',         'icon' => 'fa-lock',        'path' => 'frontend/jupiter/security/manage_ssl.html'],
    ];

    public function edit(): View
    {
        return view('admin.settings.self-cpanel', ['shortcuts' => self::SHORTCUTS]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'self_cpanel_url' => ['required', 'url', 'max:255'],
            'self_cpanel_username' => ['required', 'string', 'max:100'],
            'self_cpanel_password' => ['nullable', 'string', 'max:255'],
        ]);

        $password = $data['self_cpanel_password'] ?? null;
        unset($data['self_cpanel_password']);

        Setting::putMany($data, 'general');

        // Ditangani TERPISAH dari putMany() -- itu tidak mendukung
        // flag enkripsi sama sekali (cuma put() biasa yang bisa).
        // Password TIDAK ikut kosong menimpa yang sudah tersimpan
        // kalau admin membiarkan kolomnya kosong saat cuma mengedit
        // URL/username -- sama pola dengan token server/registrar.
        if (filled($password)) {
            Setting::put('self_cpanel_password', $password, 'general', encrypted: true);
        }

        return back()->with('success', 'Pengaturan cPanel aplikasi berhasil disimpan.');
    }
}
