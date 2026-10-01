<?php

namespace App\Services\Mail;

/**
 * Pengurai email mentah (RFC 822/MIME) tanpa dependensi tambahan.
 *
 * Menghasilkan pengirim, subjek, header threading, isi teks (tanpa kutipan
 * balasan) dan lampiran yang sudah didekode.
 */
class MimeMessage
{
    public array $headers = [];          // nama huruf kecil => nilai (header berulang digabung dengan "\n")
    public string $text = '';
    public string $html = '';
    /** @var array<int, array{name:string, mime:string, content:string}> */
    public array $attachments = [];

    private const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;
    private const MAX_ATTACHMENTS = 5;

    public static function parse(string $raw): self
    {
        $m = new self();
        $m->parsePart($raw, true);

        return $m;
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function subject(): string
    {
        return trim(static::decodeHeader($this->header('subject')));
    }

    /**
     * @return array{email:string, name:string}
     */
    public function from(): array
    {
        return static::parseAddress($this->header('from'));
    }

    public function messageId(): string
    {
        return trim($this->header('message-id'), " \t<>");
    }

    /**
     * True untuk balasan otomatis, bounce, dan milis -- email seperti ini
     * tidak boleh dijadikan pesan (mencegah perulangan tanpa akhir).
     */
    public function isAutomated(): bool
    {
        $auto = strtolower(trim($this->header('auto-submitted')));

        if ($auto !== '' && $auto !== 'no') {
            return true;
        }

        if (preg_match('/\b(bulk|junk|list|auto_reply)\b/i', $this->header('precedence'))) {
            return true;
        }

        if ($this->header('x-autoreply') !== '' || $this->header('x-autorespond') !== '') {
            return true;
        }

        $from = $this->from()['email'];

        return (bool) preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)@/i', $from);
    }

    /**
     * True kalau server penerima sendiri menandai pengirim palsu
     * (DMARC gagal, atau SPF dan DKIM sama-sama gagal). Kalau header
     * Authentication-Results tidak ada, email dianggap lolos.
     */
    public function failsSenderAuth(): bool
    {
        $auth = strtolower($this->header('authentication-results'));

        if ($auth === '') {
            return false;
        }

        return str_contains($auth, 'dmarc=fail')
            || (str_contains($auth, 'spf=fail') && str_contains($auth, 'dkim=fail'));
    }

    /**
     * Isi pesan sebagai teks biasa, kutipan balasan sebelumnya dibuang.
     */
    public function body(): string
    {
        $text = $this->text !== '' ? $this->text : $this->htmlToText($this->html);

        return static::stripQuotedReply($text);
    }

    // ── Penguraian ──────────────────────────────────────────────

    private function parsePart(string $raw, bool $isRoot): void
    {
        [$rawHeaders, $body] = $this->splitHeaderBody($raw);
        $headers = $this->parseHeaders($rawHeaders);

        if ($isRoot) {
            $this->headers = $headers;
        }

        $type = strtolower($this->paramValue($headers['content-type'] ?? 'text/plain', null) ?: 'text/plain');
        $encoding = strtolower(trim($headers['content-transfer-encoding'] ?? '7bit'));
        $disposition = strtolower($this->paramValue($headers['content-disposition'] ?? '', null));

        if (str_starts_with($type, 'multipart/')) {
            $boundary = $this->paramValue($headers['content-type'] ?? '', 'boundary');

            if ($boundary === '') {
                return;
            }

            foreach ($this->splitMultipart($body, $boundary) as $part) {
                $this->parsePart($part, false);
            }

            return;
        }

        $name = $this->paramValue($headers['content-disposition'] ?? '', 'filename')
            ?: $this->paramValue($headers['content-type'] ?? '', 'name');
        $name = static::decodeHeader($name);

        $decoded = $this->decodeBody($body, $encoding);

        $isAttachment = $disposition === 'attachment' || ($name !== '' && ! in_array($type, ['text/plain', 'text/html'], true))
            || ($disposition === 'attachment' && $name !== '');

        if (! $isAttachment && $type === 'text/plain') {
            $this->text .= ($this->text !== '' ? "\n" : '') . $this->toUtf8($decoded, $headers['content-type'] ?? '');

            return;
        }

        if (! $isAttachment && $type === 'text/html') {
            $this->html .= $this->toUtf8($decoded, $headers['content-type'] ?? '');

            return;
        }

        if ($type === 'message/rfc822' || $type === 'message/delivery-status') {
            return;
        }

        if (count($this->attachments) < self::MAX_ATTACHMENTS && strlen($decoded) <= self::MAX_ATTACHMENT_BYTES && $decoded !== '') {
            $this->attachments[] = [
                'name' => $name !== '' ? $name : 'lampiran',
                'mime' => $type,
                'content' => $decoded,
            ];
        }
    }

    private function splitHeaderBody(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");

        if ($pos === false) {
            return [$raw, ''];
        }

        return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    private function parseHeaders(string $raw): array
    {
        // Lipat baris lanjutan (diawali spasi/tab).
        $raw = preg_replace("/\n[ \t]+/", ' ', $raw);
        $headers = [];

        foreach (explode("\n", $raw) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            $value = trim($value);

            $headers[$name] = isset($headers[$name]) ? $headers[$name] . "\n" . $value : $value;
        }

        return $headers;
    }

    /**
     * Ambil nilai dari "type; param=value". $param null = ambil bagian utama.
     */
    private function paramValue(string $header, ?string $param): string
    {
        if ($header === '') {
            return '';
        }

        if ($param === null) {
            return trim(explode(';', $header, 2)[0]);
        }

        // RFC 2231: filename*=UTF-8''nama%20file.pdf
        if (preg_match('/\b' . preg_quote($param, '/') . '\*=([^\']*)\'[^\']*\'([^;]+)/i', $header, $m)) {
            $value = rawurldecode(trim($m[2], " \"\t"));

            return strtolower($m[1]) === 'utf-8' || $m[1] === '' ? $value : $this->toUtf8($value, "charset={$m[1]}");
        }

        if (preg_match('/\b' . preg_quote($param, '/') . '="((?:[^"\\\\]|\\\\.)*)"/i', $header, $m)) {
            return stripslashes($m[1]);
        }

        if (preg_match('/\b' . preg_quote($param, '/') . '=([^;\s]+)/i', $header, $m)) {
            return trim($m[1], '"');
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function splitMultipart(string $body, string $boundary): array
    {
        $delimiter = '--' . $boundary;
        $segments = explode($delimiter, $body);
        array_shift($segments);   // pembuka sebelum boundary pertama

        $parts = [];

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '--')) {
                break;        // boundary penutup
            }

            $parts[] = ltrim($segment, "\n");
        }

        return $parts;
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body), false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function toUtf8(string $content, string $contentTypeHeader): string
    {
        $charset = strtolower($this->paramValue($contentTypeHeader, 'charset') ?: 'utf-8');

        if ($charset === 'utf-8' || $charset === 'us-ascii') {
            return mb_check_encoding($content, 'UTF-8') ? $content : mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        try {
            return mb_convert_encoding($content, 'UTF-8', $charset);
        } catch (\Throwable) {
            return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }
    }

    private function htmlToText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6])>#i', "\n", $html);
        $html = preg_replace('#<blockquote\b[^>]*>.*?</blockquote>#is', '', $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // ── Pembantu statis ─────────────────────────────────────────

    public static function decodeHeader(string $value): string
    {
        if ($value === '' || ! str_contains($value, '=?')) {
            return $value;
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return mb_decode_mimeheader($value);
    }

    /**
     * @return array{email:string, name:string}
     */
    public static function parseAddress(string $header): array
    {
        $header = static::decodeHeader($header);

        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $header, $m)) {
            return ['email' => strtolower(trim($m[2])), 'name' => trim($m[1])];
        }

        return ['email' => strtolower(trim($header, " <>\t")), 'name' => ''];
    }

    /**
     * Buang kutipan balasan: baris diawali ">", penanda "On ... wrote:" /
     * "Pada ... menulis:", blok "-----Original Message-----", dan tanda tangan "-- ".
     */
    public static function stripQuotedReply(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $kept = [];

        foreach ($lines as $i => $line) {
            $trim = trim($line);

            if (preg_match('/^-{2,}\s*(original message|pesan asli|forwarded message)\s*-{2,}$/i', $trim)
                || preg_match('/^_{10,}$/', $trim)
                || preg_match('/^(on|pada)\s.+\s(wrote|menulis|menulis kepada):?$/i', $trim)
                || preg_match('/^(on|pada)\s.+,$/i', $trim) && isset($lines[$i + 1]) && preg_match('/(wrote|menulis):?\s*$/i', trim($lines[$i + 1]))
                || $trim === '-- ') {
                break;
            }

            // Blok header balasan Outlook: "From:/Dari:" diikuti "Sent:/Dikirim:".
            if (preg_match('/^(from|dari):\s/i', $trim) && isset($lines[$i + 1]) && preg_match('/^(sent|dikirim|date|tanggal):\s/i', trim($lines[$i + 1]))) {
                break;
            }

            if (str_starts_with($trim, '>')) {
                continue;
            }

            $kept[] = rtrim($line);
        }

        $clean = trim(implode("\n", $kept));
        $clean = preg_replace("/\n{3,}/", "\n\n", $clean);

        // Kalau semua isi ternyata kutipan, jangan kembalikan kosong.
        return $clean !== '' ? $clean : trim($text);
    }
}
