<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

/**
 * Membawa pesan galat (dan isian form) dari domain CLIENT kembali ke halaman
 * PUBLIK tempat pengunjung memilih.
 *
 * Gagal menambah ke keranjang dari halaman publik tidak bisa memakai flash
 * session: flash tersimpan di sesi client, sedangkan halaman publik membaca
 * sesi publik. Pesan dibawa sebagai parameter terenkripsi (APP_KEY) dan
 * kedaluwarsa cepat; RestoreCartNotice di domain publik membukanya, memasukkan
 * ke sesi publik (flash + old input), lalu membersihkan alamatnya.
 *
 * Keamanan:
 *  - Payload terenkripsi dan terautentikasi (tidak bisa diubah pihak lain).
 *  - Payload DIIKAT ke IP pembuatnya. Tanpa ini, penyerang yang memalsukan
 *    header Origin (klien non-browser seperti curl bisa melakukannya) dapat
 *    meminta server membuat token berisi teks pilihannya, lalu mengirim
 *    tautannya ke korban -> teks palsu (phishing) tampil di toast korban.
 *  - Hanya isian form yang dikenal (ALLOWED_INPUT) yang dibawa, dengan panjang
 *    dibatasi; kunci lain dibuang. Pesan juga dibatasi panjangnya.
 *  - Alamat tujuan SELALU dibuat server dari route publik, dan isian sensitif
 *    (kode transfer) tidak pernah ikut.
 */
class CartNotice
{
    public const PARAM = 'cart_notice';

    /** Detik. Cukup untuk satu putaran redirect; lebih lama dianggap basi. */
    private const TTL = 120;

    private const MAX_BYTES = 1800;

    private const MAX_MESSAGE = 400;

    private const MAX_VALUE = 255;

    private const MAX_LIST = 20;

    /** Satu-satunya isian yang dibawa kembali (nama field form pesan di halaman publik). */
    private const ALLOWED_INPUT = [
        'product_id', 'billing_cycle', 'domain_mode', 'domain_name', 'options',
        'addon_id', 'license_ip', 'domain_label', 'tld_premium_id', 'custom_premium_id',
        'tld_id', 'years',
    ];

    /** Alamat halaman publik $backUrl dengan pesan terlampir. */
    public static function url(string $backUrl, string $message, Request $request): string
    {
        $payload = [
            'm' => self::message($message),
            'i' => self::input($request->all()),
            't' => time(),
            'b' => self::binding($request),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($json === false || strlen($json) > self::MAX_BYTES) {
            $payload['i'] = [];
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        return $backUrl.(str_contains($backUrl, '?') ? '&' : '?')
            .self::PARAM.'='.rawurlencode(Crypt::encryptString((string) $json));
    }

    /**
     * Buka payload. Null bila rusak, dipalsukan, kedaluwarsa, atau dibuat untuk
     * pengunjung lain (IP berbeda).
     *
     * @return array{message: string, input: array<string, mixed>}|null
     */
    public static function open(string $token, ?Request $request = null): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($data) || ! is_string($data['m'] ?? null) || ! is_int($data['t'] ?? null) || ! is_string($data['b'] ?? null)) {
            return null;
        }

        if (abs(time() - $data['t']) > self::TTL) {
            return null;
        }

        if (! hash_equals($data['b'], self::binding($request ?? request()))) {
            return null;
        }

        return [
            'message' => self::message($data['m']),
            'input' => self::input(is_array($data['i'] ?? null) ? $data['i'] : []),
        ];
    }

    private static function binding(Request $request): string
    {
        return hash('sha256', (string) $request->ip());
    }

    private static function message(string $message): string
    {
        return mb_substr($message, 0, self::MAX_MESSAGE);
    }

    /** @return array<string, mixed> */
    private static function input(array $input): array
    {
        $clean = [];

        foreach (self::ALLOWED_INPUT as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (is_scalar($value) || $value === null) {
                $clean[$key] = self::value($value);
            } elseif (is_array($value)) {
                $list = [];

                foreach (array_slice($value, 0, self::MAX_LIST, true) as $k => $v) {
                    if (is_scalar($v) || $v === null) {
                        $list[mb_substr((string) $k, 0, 64)] = self::value($v);
                    }
                }

                $clean[$key] = $list;
            }
        }

        return $clean;
    }

    private static function value(mixed $value): mixed
    {
        return is_string($value) ? mb_substr($value, 0, self::MAX_VALUE) : $value;
    }
}
