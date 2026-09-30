<?php

namespace App\Services;

use App\Models\Addon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupplierPricingSyncService
{
    /**
     * Supplier API sengaja dibuat generik: endpoint dapat mengembalikan
     * JSON apa pun selama path harga per siklus diatur pada form addon.
     *
     * @return array{updated: int, message: string}
     */
    public function sync(Addon $addon): array
    {
        if (! $addon->supplier_api_url) {
            throw new RuntimeException('URL API supplier belum diisi.');
        }

        // Cegah SSRF: URL diisi admin tapi dipanggil dari server, jadi tolak
        // alamat internal/metadata dan jangan ikuti redirect ke sana.
        if (! \App\Support\UrlGuard::isPublicHttpUrl($addon->supplier_api_url)) {
            $message = 'URL API supplier harus http(s) ke alamat publik (bukan localhost/jaringan internal).';
            $addon->update(['supplier_last_error' => $message]);
            throw new RuntimeException($message);
        }

        $request = Http::acceptJson()->timeout(20)->withoutRedirecting();

        if ($addon->supplier_api_token) {
            $request = $request->withToken($addon->supplier_api_token);
        }

        try {
            $response = $addon->supplier_http_method === 'POST'
                ? $request->post($addon->supplier_api_url, [
                    'addon' => $addon->slug,
                    'name' => $addon->name,
                ])
                : $request->get($addon->supplier_api_url, [
                    'addon' => $addon->slug,
                    'name' => $addon->name,
                ]);
        } catch (\Throwable $e) {
            $message = 'Koneksi ke API supplier gagal: ' . $e->getMessage();
            $addon->update(['supplier_last_error' => $message]);
            throw new RuntimeException($message, previous: $e);
        }

        if ($response->failed()) {
            $message = "Supplier mengembalikan HTTP {$response->status()}.";
            $addon->update(['supplier_last_error' => $message]);
            throw new RuntimeException($message);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $message = 'Respons supplier bukan JSON object yang valid.';
            $addon->update(['supplier_last_error' => $message]);
            throw new RuntimeException($message);
        }

        $updates = [];
        foreach (['monthly', 'quarterly', 'semi_annually', 'annually'] as $cycle) {
            $path = $addon->{"supplier_price_path_{$cycle}"};
            if (! $path) {
                continue;
            }

            $value = data_get($payload, $path);
            if ($value === null || ! is_numeric($value) || (float) $value < 0) {
                $message = "Harga {$cycle} tidak ditemukan atau tidak valid pada path {$path}.";
                $addon->update(['supplier_last_error' => $message]);
                throw new RuntimeException($message);
            }

            $updates["cost_price_{$cycle}"] = (float) $value;
        }

        if ($updates === []) {
            $message = 'Belum ada mapping path harga supplier yang diisi.';
            $addon->update(['supplier_last_error' => $message]);
            throw new RuntimeException($message);
        }

        $updates['supplier_last_synced_at'] = now();
        $updates['supplier_last_error'] = null;
        $addon->update($updates);

        return [
            'updated' => count($updates) - 2,
            'message' => 'Harga modal berhasil disinkronkan dari API supplier.',
        ];
    }
}