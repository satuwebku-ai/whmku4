<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Notification\PushSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
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

        $service->subscribe($request->user('admin'), $data['subscription'], $request->userAgent());

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, PushSubscriptionService $service): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);

        $service->unsubscribe($request->user('admin'), $data['endpoint']);

        return response()->json(['success' => true]);
    }
}
