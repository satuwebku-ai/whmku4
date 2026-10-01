<?php

namespace App\Console\Commands;

use App\Models\CronJob;
use App\Services\Mail\MailAutomation;
use Illuminate\Console\Command;

/**
 * Tutup otomatis thread Inbox Email yang sudah dibalas admin tetapi tidak
 * dibalas pelanggan dalam batas jam yang diatur (Email → Otomatisasi).
 * Thread yang menunggu balasan ADMIN tidak pernah ditutup otomatis.
 */
class CloseInactiveMail extends Command
{
    protected $signature = 'lumora:close-inactive-mail';

    protected $description = 'Tutup otomatis email yang sudah dibalas admin tapi tidak ada balasan pelanggan.';

    public function handle(): int
    {
        if (! MailAutomation::on('mail_autoclose_enabled')) {
            $this->line('Tutup otomatis email nonaktif — dilewati.');
            CronJob::recordExecution('lumora:close-inactive-mail', true, 'Nonaktif.');

            return self::SUCCESS;
        }

        $closed = MailAutomation::closeStale();
        $msg = $closed . ' email ditutup otomatis (batas ' . MailAutomation::closeHours() . ' jam tanpa balasan).';

        $this->info($msg);
        CronJob::recordExecution('lumora:close-inactive-mail', true, $msg);

        return self::SUCCESS;
    }
}
