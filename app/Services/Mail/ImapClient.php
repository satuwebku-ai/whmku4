<?php

namespace App\Services\Mail;

use RuntimeException;

/**
 * Klien IMAP minimal berbasis socket PHP (tanpa ekstensi php-imap, yang
 * sering tidak tersedia di hosting dan sudah dihapus dari PHP 8.4).
 *
 * Hanya mendukung yang dibutuhkan: login, pilih folder, cari email belum
 * dibaca, ambil isi email mentah, tandai sudah dibaca.
 */
class ImapClient
{
    /** @var resource|null */
    private $socket = null;

    private int $tagCounter = 0;

    /** Email lebih besar dari ini dilewati (byte). */
    private const MAX_MESSAGE_BYTES = 12 * 1024 * 1024;

    public function __construct(
        private string $host,
        private int $port = 993,
        private string $encryption = 'ssl',   // ssl | tls | none
        private bool $verifyCert = true,
        private int $timeout = 20,
    ) {}

    public static function fromConfig(array $c): self
    {
        return new self(
            $c['host'],
            (int) ($c['port'] ?: 993),
            $c['encryption'] ?: 'ssl',
            (bool) ($c['verify_cert'] ?? true),
        );
    }

    public function connect(): void
    {
        $context = stream_context_create(['ssl' => [
            'verify_peer' => $this->verifyCert,
            'verify_peer_name' => $this->verifyCert,
            'allow_self_signed' => ! $this->verifyCert,
            'SNI_enabled' => true,
            'peer_name' => $this->host,
        ]]);

        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';

        $socket = @stream_socket_client(
            "{$scheme}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! $socket) {
            throw new RuntimeException("Tidak bisa terhubung ke {$this->host}:{$this->port} ({$errstr})");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        $greeting = $this->readLine();

        if (! str_starts_with($greeting, '* OK') && ! str_starts_with($greeting, '* PREAUTH')) {
            throw new RuntimeException('Respons awal server IMAP tidak dikenali: ' . trim($greeting));
        }

        if ($this->encryption === 'tls') {
            $this->command('STARTTLS');

            if (! @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Gagal mengaktifkan STARTTLS.');
            }
        }
    }

    public function login(string $username, string $password): void
    {
        $res = $this->command('LOGIN ' . $this->quote($username) . ' ' . $this->quote($password), false);

        if (! $res['ok']) {
            throw new RuntimeException('Login IMAP ditolak — periksa username dan password mailbox.');
        }
    }

    /**
     * @return int jumlah email di folder
     */
    public function select(string $folder): int
    {
        $res = $this->command('SELECT ' . $this->quote($folder), false);

        if (! $res['ok']) {
            throw new RuntimeException("Folder \"{$folder}\" tidak ditemukan di mailbox.");
        }

        foreach ($res['lines'] as $line) {
            if (preg_match('/^\* (\d+) EXISTS/i', $line, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    /**
     * @return int[] UID email yang belum dibaca, terlama dulu
     */
    public function searchUnseen(int $limit = 25): array
    {
        $res = $this->command('UID SEARCH UNSEEN');

        $uids = [];

        foreach ($res['lines'] as $line) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', trim($line), $m) && trim($m[1]) !== '') {
                $uids = array_map('intval', preg_split('/\s+/', trim($m[1])));
            }
        }

        sort($uids);

        return array_slice($uids, 0, $limit);
    }

    /**
     * Ambil isi email mentah tanpa menandainya sebagai dibaca.
     * Mengembalikan null kalau email terlalu besar.
     */
    public function fetchRaw(int $uid): ?string
    {
        $res = $this->command("UID FETCH {$uid} (BODY.PEEK[])");

        foreach ($res['literals'] as $literal) {
            if ($literal !== null) {
                return $literal;
            }
        }

        return null;
    }

    public function markSeen(int $uid): void
    {
        $this->command("UID STORE {$uid} +FLAGS (\\Seen)");
    }

    public function logout(): void
    {
        if ($this->socket) {
            try {
                $this->command('LOGOUT', false);
            } catch (\Throwable) {
                // abaikan -- koneksi ditutup di bawah
            }

            @fclose($this->socket);
            $this->socket = null;
        }
    }

    // ── Internal ────────────────────────────────────────────────

    /**
     * Kirim satu perintah, baca sampai respons bertag selesai.
     *
     * @return array{ok:bool, status:string, lines:string[], literals:array<int,?string>}
     */
    private function command(string $command, bool $throwOnFail = true): array
    {
        $tag = 'A' . str_pad((string) ++$this->tagCounter, 4, '0', STR_PAD_LEFT);

        $this->write("{$tag} {$command}\r\n");

        $lines = [];
        $literals = [];

        while (true) {
            $line = $this->readLine();

            // Literal: baris diakhiri {N}, lalu N byte data mentah.
            while (preg_match('/\{(\d+)\}\r?\n?$/', $line, $m)) {
                $size = (int) $m[1];
                $literals[] = $this->readBytes($size);
                $line .= '[literal]' . $this->readLine();
            }

            if (str_starts_with($line, $tag . ' ')) {
                $status = trim(substr($line, strlen($tag) + 1));
                $ok = (bool) preg_match('/^OK\b/i', $status);

                if (! $ok && $throwOnFail) {
                    throw new RuntimeException('Perintah IMAP gagal: ' . $status);
                }

                return ['ok' => $ok, 'status' => $status, 'lines' => $lines, 'literals' => $literals];
            }

            $lines[] = $line;
        }
    }

    private function write(string $data): void
    {
        if (! $this->socket || @fwrite($this->socket, $data) === false) {
            throw new RuntimeException('Koneksi IMAP terputus saat mengirim perintah.');
        }
    }

    private function readLine(): string
    {
        $line = $this->socket ? fgets($this->socket, 65536) : false;

        if ($line === false) {
            $meta = $this->socket ? stream_get_meta_data($this->socket) : [];

            throw new RuntimeException(! empty($meta['timed_out'])
                ? 'Server IMAP tidak merespons (timeout).'
                : 'Koneksi IMAP terputus.');
        }

        return $line;
    }

    /**
     * Baca tepat $size byte. Data melebihi batas dibuang (tetap dikonsumsi
     * supaya aliran protokol tidak rusak) dan dikembalikan sebagai null.
     */
    private function readBytes(int $size): ?string
    {
        $keep = $size <= self::MAX_MESSAGE_BYTES;
        $buffer = '';
        $remaining = $size;

        while ($remaining > 0) {
            $chunk = fread($this->socket, min(8192, $remaining));

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);

                if (! empty($meta['timed_out']) || feof($this->socket)) {
                    throw new RuntimeException('Koneksi IMAP terputus saat membaca email.');
                }

                continue;
            }

            $remaining -= strlen($chunk);

            if ($keep) {
                $buffer .= $chunk;
            }
        }

        return $keep ? $buffer : null;
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
