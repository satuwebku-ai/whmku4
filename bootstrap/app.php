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
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureAdminRole::class,
            'module' => \App\Http\Middleware\EnsureAdminModule::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
