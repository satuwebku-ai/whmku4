<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Domain;

/**
 * Satu-satunya tempat aturan "domain ini milik klien yang login atau
 * bukan" ditulis. Sebelum Policy ini, aturan yang SAMA PERSIS ditulis
 * ulang manual di setiap method controller yang menerima Domain lewat
 * route — kalau ada method baru lupa menambahkannya, itu jadi celah
 * IDOR (klien A bisa buka domain klien B lewat URL).
 *
 * Nama ability sengaja disatukan jadi 'view' untuk semua aksi (lihat,
 * ubah, hapus) — di kodebase ini belum ada perbedaan hak per aksi
 * untuk klien atas datanya sendiri (kalau itu miliknya, dia boleh
 * kelola sepenuhnya). Kalau nanti butuh dibedakan (mis. klien boleh
 * lihat tapi tidak boleh hapus), tinggal tambah method baru di sini.
 */
class DomainPolicy
{
    public function view(Client $client, Domain $domain): bool
    {
        return $domain->client_id === $client->id;
    }
}
