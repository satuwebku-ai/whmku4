<?php

namespace App\Http\Controllers\Client;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Dipakai semua controller di namespace Client untuk memeriksa "data ini
 * benar-benar milik klien yang sedang login atau bukan", lewat Policy di
 * app/Policies/ — bukan menulis ulang `abort_unless($model->client_id
 * === Auth::guard('client')->id(), 403)` di tiap method satu per satu.
 *
 * Dipakai lewat Gate::forUser() secara eksplisit (bukan $this->authorize()
 * bawaan Laravel) karena aplikasi ini punya DUA guard terpisah (admin &
 * client) — authorize() bawaan cuma tahu cara membaca guard default,
 * jadi kalau dipakai apa adanya di controller klien, dia akan salah
 * memeriksa user dari guard yang salah.
 */
trait AuthorizesClientOwnership
{
    /**
     * @throws AuthorizationException  (otomatis jadi respons 403 —
     *   sama persis seperti abort_unless() yang digantikannya)
     */
    protected function authorizeOwner(mixed $model): void
    {
        Gate::forUser(auth('client')->user())->authorize('view', $model);
    }
}
