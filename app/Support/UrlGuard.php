<?php

namespace App\Support;

/**
 * Penjaga SSRF untuk URL yang dipanggil SERVER atas input admin (mis. API
 * supplier addon): hanya http/https ke alamat PUBLIK. Menolak localhost,
 * metadata cloud (169.254.169.254), dan rentang jaringan privat/reserved.
 */
class UrlGuard
{
    public static function isPublicHttpUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $parts = parse_url($url);

        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }

        // Kredensial di dalam URL (user:pass@host) sering dipakai untuk mengelabui parser.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = trim(strtolower($parts['host']), '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $ips = @gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
