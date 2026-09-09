<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Menguji konfigurasi email dengan mengirim pesan sungguhan.
 *
 * Dipakai sebelum mengaktifkan 2FA admin atau mengumumkan fitur lupa
 * password ke pelanggan — kalau SMTP belum benar, admin bisa terkunci
 * dari panelnya sendiri dan klien tidak akan menerima kode reset.
 */
class TestMail extends Command
{
    protected $signature = 'lumora:test-mail {email : Alamat tujuan uji coba}';

    protected $description = 'Kirim email percobaan untuk memastikan SMTP sudah benar';

    public function handle(): int
    {
        $to = $this->argument('email');
        $mailer = config('mail.default');

        $this->info('Mailer   : ' . $mailer);
        $this->info('Host     : ' . config('mail.mailers.smtp.host', '—'));
        $this->info('Port     : ' . config('mail.mailers.smtp.port', '—'));
        $this->info('Username : ' . (config('mail.mailers.smtp.username') ?: '(kosong)'));
        $this->info('Dari     : ' . config('mail.from.address', '—'));
        $this->newLine();

        if ($mailer === 'log') {
            $this->error('MAIL_MAILER masih "log" — email TIDAK benar-benar dikirim.');
            $this->warn('Isinya hanya ditulis ke storage/logs/laravel.log.');
            $this->warn('Ubah MAIL_MAILER=smtp di .env, lalu jalankan: php artisan config:clear');

            return self::FAILURE;
        }

        if (blank(config('mail.from.address'))) {
            $this->error('MAIL_FROM_ADDRESS belum diisi. Banyak server SMTP menolak email tanpa alamat pengirim.');

            return self::FAILURE;
        }

        $this->line('Mengirim email percobaan ke ' . $to . ' …');

        try {
            Mail::raw(
                "Ini email percobaan dari " . config('app.name') . ".\n\n" .
                "Kalau kamu menerima pesan ini, berarti konfigurasi SMTP sudah benar dan " .
                "fitur kode OTP serta reset password akan berfungsi.\n\n" .
                "Dikirim: " . now()->format('d M Y H:i:s'),
                function ($message) use ($to) {
                    $message->to($to)->subject('Tes SMTP — ' . config('app.name'));
                }
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('GAGAL: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Penyebab yang paling sering:');
            $this->line('  • Password salah, atau perlu App Password (Gmail/Outlook)');
            $this->line('  • Port diblokir hosting — coba 587, atau layanan dengan port 2525');
            $this->line('  • MAIL_ENCRYPTION tidak cocok: 465 pakai ssl, 587 pakai tls');
            $this->line('  • Lupa menjalankan: php artisan config:clear');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Email terkirim tanpa error.');
        $this->warn('Cek inbox DAN folder spam di ' . $to . '.');
        $this->line('Kalau tidak sampai padahal tidak ada error, biasanya masalah SPF/DKIM domain pengirim.');

        return self::SUCCESS;
    }
}
