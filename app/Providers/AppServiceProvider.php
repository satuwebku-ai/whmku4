<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use App\Support\CspNonce;
use App\Support\MailConfig;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
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

        // SMTP dari Pengaturan → Email (database) menimpa MAIL_* di .env.
        MailConfig::apply();

        // Worker antrean berjalan lama; muat ulang tiap job supaya perubahan
        // pengaturan langsung berlaku tanpa restart worker.
        Event::listen(JobProcessing::class, fn () => MailConfig::apply());
    }
}
