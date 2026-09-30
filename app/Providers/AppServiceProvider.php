<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use App\Support\CspNonce;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CspNonce::class);
    }

    public function boot(): void
    {
        // <script @nonce> => <script nonce="..."> (nonce sama dengan header CSP).
        Blade::directive('nonce', fn () => '<?php echo \'nonce="\' . e(app(\App\Support\CspNonce::class)->value()) . \'"\'; ?>');

        // Daftarkan channel WhatsApp supaya bisa dipakai lewat
        // Notification::route() maupun method via() di kelas notifikasi.
        Notification::extend(WhatsAppChannel::class, fn ($app) => $app->make(WhatsAppChannel::class));
    }
}
