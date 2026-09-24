<?php

namespace App\Http\Controllers\Api\Domain;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainSearchRequest;
use App\Services\Domain\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AvailabilityController extends Controller
{
    public function __invoke(DomainSearchRequest $request, AvailabilityService $availability): JsonResponse
    {
        $domain = strtolower(trim((string) $request->string('domain')));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = trim(explode('/', $domain)[0]);

        if ($domain === '' || ! Str::contains($domain, '.')) {
            return response()->json(['message' => 'Masukkan nama domain lengkap, misalnya contoh.com.'], 422);
        }

        $result = $availability->check([$domain]);

        return response()->json([
            'domain' => $domain,
            'available' => $result['results'][$domain] ?? null,
            'unknown' => in_array($domain, $result['unknown'], true),
        ]);
    }
}