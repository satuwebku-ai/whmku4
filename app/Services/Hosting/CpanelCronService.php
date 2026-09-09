<?php

namespace App\Services\Hosting;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengelola cron job milik akun cPanel tempat aplikasi ini di-hosting.
 *
 * PERBEDAAN PENTING dengan CpanelWhmService:
 *  - CpanelWhmService memakai WHM API (port 2087) dengan kredensial
 *    reseller/root untuk membuat akun hosting PELANGGAN.
 *  - Kelas ini memakai cPanel UAPI (port 2083) dengan kredensial akun
 *    cPanel tempat aplikasi ini sendiri berjalan, hanya untuk memasang
 *    satu baris cron.
 *
 * Keduanya butuh kredensial berbeda dan tidak bisa saling menggantikan.
 *
 * Dokumentasi: https://api.docs.cpanel.net/openapi/cpanel/operation/add_line/
 */
class CpanelCronService
{
    /**
     * Apakah kredensial cPanel sudah diisi?
     */
    public function isConfigured(): bool
    {
        return filled(Setting::get('cpanel_host'))
            && filled(Setting::get('cpanel_user'))
            && filled(Setting::get('cpanel_token'));
    }

    /**
     * Baris cron yang perlu dipasang. Dibuat dari path aplikasi yang
     * sedang berjalan, jadi selalu benar tanpa perlu diketik manual.
     */
    public function cronLine(): string
    {
        $php = Setting::get('cpanel_php_path') ?: 'php';

        return sprintf('cd %s && %s artisan lumora:cron >> /dev/null 2>&1', base_path(), $php);
    }

    /**
     * Ambil daftar cron yang sudah terpasang di akun cPanel.
     *
     * @return array{success: bool, message: string, lines: array}
     */
    public function listLines(): array
    {
        $response = $this->call('Cron', 'list_lines');

        if (! $response['success']) {
            return ['success' => false, 'message' => $response['message'], 'lines' => []];
        }

        $lines = $response['data']['data'] ?? [];

        return ['success' => true, 'message' => 'OK', 'lines' => is_array($lines) ? $lines : []];
    }

    /**
     * Pasang baris cron aplikasi ke cPanel, dijalankan tiap menit.
     *
     * Dicek dulu apakah sudah ada supaya tidak terpasang dobel — cron
     * ganda akan membuat email pengingat terkirim dua kali.
     *
     * @return array{success: bool, message: string}
     */
    public function install(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Kredensial cPanel belum diisi.'];
        }

        $command = $this->cronLine();

        $existing = $this->listLines();

        if ($existing['success']) {
            foreach ($existing['lines'] as $line) {
                if (str_contains((string) ($line['command'] ?? ''), 'lumora:cron')) {
                    return ['success' => true, 'message' => 'Cron sudah terpasang sebelumnya — tidak ada yang diubah.'];
                }
            }
        }

        $response = $this->call('Cron', 'add_line', [
            'command' => $command,
            'minute'  => '*',
            'hour'    => '*',
            'day'     => '*',
            'month'   => '*',
            'weekday' => '*',
        ]);

        if (! $response['success']) {
            return ['success' => false, 'message' => $response['message']];
        }

        return ['success' => true, 'message' => 'Cron berhasil dipasang di cPanel dan akan berjalan tiap menit.'];
    }

    /**
     * Uji koneksi ke cPanel tanpa mengubah apa pun.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Kredensial cPanel belum diisi.'];
        }

        $response = $this->call('Cron', 'list_lines');

        return [
            'success' => $response['success'],
            'message' => $response['success']
                ? 'Koneksi ke cPanel berhasil.'
                : $response['message'],
        ];
    }

    /**
     * Panggil cPanel UAPI dengan autentikasi API token.
     *
     * Format header: Authorization: cpanel {user}:{token}
     *
     * @return array{success: bool, message: string, data: mixed}
     */
    private function call(string $module, string $function, array $params = []): array
    {
        try {
            $response = $this->client()->get("/execute/{$module}/{$function}", $params);

            $body = $response->json();

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => "cPanel mengembalikan HTTP {$response->status()}.",
                    'data' => $body,
                ];
            }

            // UAPI menandai kegagalan lewat errors[], bukan status HTTP.
            $errors = $body['errors'] ?? null;

            if (! empty($errors)) {
                return [
                    'success' => false,
                    'message' => is_array($errors) ? implode(' ', $errors) : (string) $errors,
                    'data' => $body,
                ];
            }

            return ['success' => true, 'message' => 'OK', 'data' => $body];
        } catch (Throwable $e) {
            Log::warning("cPanel UAPI [{$module}::{$function}] gagal: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Tidak bisa terhubung ke cPanel: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    private function client(): PendingRequest
    {
        $host = rtrim((string) Setting::get('cpanel_host'), '/');
        $port = Setting::get('cpanel_port', '2083');

        // Tambahkan skema kalau admin hanya mengetik hostname.
        if (! str_starts_with($host, 'http')) {
            $host = "https://{$host}";
        }

        return Http::baseUrl("{$host}:{$port}")
            ->withHeaders([
                'Authorization' => 'cpanel ' . Setting::get('cpanel_user') . ':' . Setting::get('cpanel_token'),
            ])
            ->withOptions(['verify' => Setting::get('cpanel_verify_ssl', '1') === '1'])
            ->timeout(20);
    }
}
