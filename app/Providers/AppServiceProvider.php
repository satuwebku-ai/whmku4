<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use App\Support\CspNonce;
use App\Support\MailConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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

        // Serah-terima publik -> client (routes/cart.php): route ini menerima POST
        // lintas domain tanpa CSRF (hanya dijaga RequirePortalOrigin), jadi dibatasi
        // per IP. Dibuat sebagai pembatas bernama agar pengunjung yang melewati
        // batas diarahkan ke keranjang dengan pesan yang jelas, bukan halaman 429 mentah.
        RateLimiter::for('cart-handoff', fn (Request $request) => Limit::perMinute(30)
            ->by('cart-handoff|'.$request->ip())
            ->response(fn () => redirect()->route('cart.index')
                ->with('error', 'Terlalu banyak permintaan. Coba lagi sebentar lagi.')));

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
