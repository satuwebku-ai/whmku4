<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Mail\ImapClient;
use App\Services\Mail\InboundMailProcessor;
use App\Services\Mail\MimeMessage;
use App\Support\MailConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Membaca email baru dari mailbox support (IMAP) dan memasukkannya ke
 * Inbox Email / tiket / Live Chat. Dijadwalkan lewat Pengaturan → Cron Jobs.
 */
class FetchInboundMail extends Command
{
    protected $signature = 'lumora:fetch-mail {--limit=25 : Maksimal email diproses per jalan}';

    protected $description = 'Ambil email masuk dari mailbox IMAP dan masukkan ke Inbox Email, balasan tiket, atau pesan live chat';

    public function handle(InboundMailProcessor $processor): int
    {
        $c = MailConfig::imap();

        if (! $c['enabled']) {
            $this->line('Email masuk (IMAP) nonaktif — dilewati.');

            return self::SUCCESS;
        }

        if (blank($c['host']) || blank($c['username']) || blank($c['password'])) {
            return $this->finish(false, 'Pengaturan IMAP belum lengkap (host, username, password).');
        }

        $imap = ImapClient::fromConfig($c);
        $processed = 0;

        try {
            $imap->connect();
            $imap->login($c['username'], $c['password']);
            $imap->select($c['folder']);

            foreach ($imap->searchUnseen((int) $this->option('limit')) as $uid) {
                $raw = $imap->fetchRaw($uid);

                if ($raw === null) {
                    // Terlalu besar / tidak terbaca: tandai dibaca supaya tidak diulang terus.
                    $imap->markSeen($uid);
                    $this->warn("UID {$uid} dilewati (terlalu besar).");

                    continue;
                }

                $mail = MimeMessage::parse($raw);
                $key = InboundMailProcessor::dedupeKey($mail, $raw);

                if (DB::table('inbound_emails')->where('dedupe_key', $key)->exists()) {
                    $imap->markSeen($uid);

                    continue;
                }

                try {
                    $result = $processor->process($mail);
                } catch (Throwable $e) {
                    report($e);
                    $result = 'error:' . $e->getMessage();
                }

                InboundMailProcessor::log($key, $mail, $result);
                $imap->markSeen($uid);
                $processed++;

                $this->line("UID {$uid}: {$result}");
            }

            $imap->logout();
        } catch (Throwable $e) {
            $imap->logout();

            return $this->finish(false, $e->getMessage());
        }

        return $this->finish(true, "{$processed} email diproses.");
    }

    private function finish(bool $ok, string $message): int
    {
        Setting::put('imap_last_status', $ok ? 'success' : 'failed', 'email');
        Setting::put('imap_last_message', $message, 'email');
        Setting::put('imap_last_run_at', now()->toDateTimeString(), 'email');

        $ok ? $this->info($message) : $this->error($message);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
