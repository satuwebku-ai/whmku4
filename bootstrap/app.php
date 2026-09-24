<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Honor X-Forwarded-Proto only from explicitly configured proxies.
        // Never trust the header from arbitrary direct clients.
        $trustedProxies = env('TRUSTED_PROXIES');
        if (filled($trustedProxies)) {
            $middleware->trustProxies(
                at: array_values(array_filter(array_map('trim', explode(',', $trustedProxies))))
            );
        }

        $middleware->append(\App\Http\Middleware\ForceHttps::class);

        // Tamu diarahkan ke halaman login yang sesuai areanya, supaya
        // klien tidak terlempar ke form login admin dan sebaliknya.
        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('admin', 'admin/*')
                ? route('admin.login')
                : route('client.login');
        });

        // Webhook gateway dipanggil server-ke-server tanpa session/CSRF token.
        // Keasliannya diverifikasi lewat signature (Midtrans) atau callback
        // token (Xendit) di dalam service masing-masing.
        $middleware->validateCsrfTokens(except: [
            'payment/webhook/*',
            'webhook/whatsapp',
        ]);

        // Pembatasan akses berdasarkan peran & modul admin.
        //
        // 'role' & 'module' membaca sistem lama (kolom admins.role /
        // admins.permissions) dan dipakai di SELURUH routes/admin.php
        // yang sudah ada -- jangan diganti tanpa migrasi data penuh.
        // 'permission' & 'admin'/'client'/'check.status'/'2fa' adalah
        // middleware final tambahan sesuai Master Blueprint, tersedia
        // untuk dipakai di route baru (lihat komentar masing-masing
        // kelasnya di app/Http/Middleware/).
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'module' => \App\Http\Middleware\EnsureAdminModule::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'client' => \App\Http\Middleware\ClientMiddleware::class,
            'check.status' => \App\Http\Middleware\CheckAccountStatus::class,
            '2fa' => \App\Http\Middleware\TwoFactorMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
