<?php

namespace App\Services\Hosting;

use App\Models\Product;
use App\Models\Server;

/**
 * Menentukan server tujuan untuk order baru dari sebuah produk.
 *
 *  - Produk punya Grup Server  -> server cPanel dipilih otomatis dari grup
 *    itu (lihat ServerGroup::pickServer()).
 *  - Produk hanya punya Server -> server itu dipakai, TAPI hanya kalau masih
 *    aktif, tidak maintenance, dan belum penuh.
 *  - Tidak ada keduanya        -> tanpa server (aktivasi manual, seperti dulu).
 *
 * Kalau produk sudah dikonfigurasi tetapi tidak ada server yang bisa
 * menerima, hasilnya server = null disertai "reason" yang jelas, supaya order
 * jatuh ke aktivasi manual dengan catatan, bukan gagal diam-diam atau
 * menumpuk di server yang sedang maintenance/penuh.
 */
class ServerSelector
{
    /**
     * @return array{server: ?Server, reason: ?string}
     */
    public function resolve(?Product $product): array
    {
        if (! $product) {
            return ['server' => null, 'reason' => null];
        }

        if ($product->server_group_id) {
            $group = $product->serverGroup;

            if (! $group || ! $group->is_active) {
                return ['server' => null, 'reason' => 'Grup server produk ini nonaktif atau tidak ditemukan — aktivasi perlu dilakukan manual oleh admin.'];
            }

            $server = $group->pickServer();

            return $server
                ? ['server' => $server, 'reason' => null]
                : ['server' => null, 'reason' => "Tidak ada server cPanel yang siap di grup \"{$group->name}\" (server mungkin penuh, nonaktif, maintenance, atau belum mendukung provisioning otomatis) — aktivasi perlu dilakukan manual oleh admin."];
        }

        if ($product->server_id) {
            $server = $product->server;

            if (! $server) {
                return ['server' => null, 'reason' => 'Server produk ini tidak ditemukan — aktivasi perlu dilakukan manual oleh admin.'];
            }

            $reason = $server->unavailableReason();

            return $reason
                ? ['server' => null, 'reason' => $reason . ' Aktivasi perlu dilakukan manual oleh admin.']
                : ['server' => $server, 'reason' => null];
        }

        return ['server' => null, 'reason' => null];
    }
}
