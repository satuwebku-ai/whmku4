<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Affiliate\AffiliateTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Titik masuk link referral affiliate: `/ref/{code}` atau
 * `/ref/{code}/{campaign}`. Mencatat klik, menitipkan cookie, lalu
 * meneruskan pengunjung ke halaman utama -- pengunjung TIDAK melihat
 * halaman apa pun di sini, cuma redirect sekilas.
 */
class ReferralController extends Controller
{
    public function visit(Request $request, AffiliateTrackingService $tracking, string $code, ?string $campaign = null): RedirectResponse
    {
        ['cookie' => $cookie] = $tracking->handleVisit($request, $code, $campaign);

        $response = redirect()->route('home');

        if ($cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
