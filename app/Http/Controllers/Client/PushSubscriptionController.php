<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Notification\PushSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Kunci publik VAPID -- dipakai JS di browser untuk subscribe lewat
     * PushManager. Publik memang boleh terlihat siapa saja (namanya juga
     * "public key"), yang harus dijaga rahasia cuma kunci privatnya.
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json(['key' => Setting::get('vapid_public_key')]);
    }

    public function store(Request $request, PushSubscriptionService $service): JsonResponse
    {
        $data = $request->validate([
            'subscription' => ['required', 'array'],
            'subscription.endpoint' => ['required', 'string'],
            'subscription.keys.p256dh' => ['required', 'string'],
            'subscription.keys.auth' => ['required', 'string'],
        ]);

        $service->subscribe($request->user('client'), $data['subscription'], $request->userAgent());

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, PushSubscriptionService $service): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        $service->unsubscribe($request->user('client'), $data['endpoint']);

        return response()->json(['success' => true]);
    }
}
