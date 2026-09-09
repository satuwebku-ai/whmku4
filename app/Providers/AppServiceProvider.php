<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Daftarkan channel WhatsApp supaya bisa dipakai lewat
        // Notification::route() maupun method via() di kelas notifikasi.
        Notification::extend(WhatsAppChannel::class, fn ($app) => $app->make(WhatsAppChannel::class));
    }
}
