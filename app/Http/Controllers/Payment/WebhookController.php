<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Endpoint publik yang dipanggil server gateway (bukan browser klien).
 *
 * Route ini dikecualikan dari CSRF karena request datang dari server luar.
 * Keamanan dijamin oleh verifikasi signature (Midtrans) atau callback token
 * (Xendit) yang dilakukan di masing-masing service — bukan oleh session.
 */
class WebhookController extends Controller
{
    public function handle(Request $request, string $driver): JsonResponse
    {
        $gateway = PaymentGateway::where('driver', $driver)
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            Log::warning("Webhook masuk untuk driver [{$driver}] tapi tidak ada gateway aktif.");

            return response()->json(['message' => 'Gateway tidak ditemukan atau nonaktif.'], 404);
        }

        $result = PaymentGatewayFactory::make($gateway)->handleCallback($request);

        if (! $result['success']) {
            // 400 supaya gateway tahu ada masalah dan mencoba kirim ulang.
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json([
            'message' => $result['message'],
            'status'  => $result['status'],
        ]);
    }

    /**
     * Halaman yang dituju klien setelah selesai membayar (redirect dari gateway).
     */
    public function finish(Request $request): View
    {
        // 'lumora_ref' SENGAJA nama yang khas — bukan 'reference' polos,
        // karena beberapa gateway (dikonfirmasi: Duitku) menambahkan
        // parameter bawaan MEREKA SENDIRI ke returnUrl (reference,
        // merchantOrderId, resultCode) yang bisa menimpa nilai kita kalau
        // namanya sama persis. 'reference'/'merchantOrderId' tetap dicoba
        // sebagai cadangan untuk transaksi yang sudah terlanjur jalan
        // sebelum perbaikan ini dipasang.
        $reference = $request->query('lumora_ref')
            ?: $request->query('merchantOrderId')
            ?: $request->query('reference');

        $payment = Payment::where('reference', $reference)->first();

        return view('payment.finish', compact('payment'));
    }
}
