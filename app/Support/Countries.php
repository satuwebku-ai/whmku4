<?php

namespace App\Support;

/**
 * Daftar negara (kode ISO 3166-1 alpha-2 => nama). Disimpan sebagai kode
 * ISO supaya data konsisten dan langsung bisa dipakai API registrar domain.
 */
class Countries
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
        'ID' => 'Indonesia',
        'MY' => 'Malaysia',
        'SG' => 'Singapura',
        'BN' => 'Brunei Darussalam',
        'TH' => 'Thailand',
        'VN' => 'Vietnam',
        'PH' => 'Filipina',
        'KH' => 'Kamboja',
        'LA' => 'Laos',
        'MM' => 'Myanmar',
        'TL' => 'Timor Leste',
        'AU' => 'Australia',
        'NZ' => 'Selandia Baru',
        'JP' => 'Jepang',
        'KR' => 'Korea Selatan',
        'KP' => 'Korea Utara',
        'CN' => 'Tiongkok',
        'HK' => 'Hong Kong',
        'TW' => 'Taiwan',
        'MO' => 'Makau',
        'MN' => 'Mongolia',
        'IN' => 'India',
        'PK' => 'Pakistan',
        'BD' => 'Bangladesh',
        'LK' => 'Sri Lanka',
        'NP' => 'Nepal',
        'BT' => 'Bhutan',
        'MV' => 'Maladewa',
        'AF' => 'Afganistan',
        'SA' => 'Arab Saudi',
        'AE' => 'Uni Emirat Arab',
        'QA' => 'Qatar',
        'KW' => 'Kuwait',
        'BH' => 'Bahrain',
        'OM' => 'Oman',
        'YE' => 'Yaman',
        'JO' => 'Yordania',
        'LB' => 'Lebanon',
        'IQ' => 'Irak',
        'IR' => 'Iran',
        'IL' => 'Israel',
        'PS' => 'Palestina',
        'SY' => 'Suriah',
        'TR' => 'Turki',
        'EG' => 'Mesir',
        'MA' => 'Maroko',
        'DZ' => 'Aljazair',
        'TN' => 'Tunisia',
        'LY' => 'Libya',
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'KE' => 'Kenya',
        'ET' => 'Ethiopia',
        'TZ' => 'Tanzania',
        'UG' => 'Uganda',
        'ZA' => 'Afrika Selatan',
        'SN' => 'Senegal',
        'CI' => 'Pantai Gading',
        'CM' => 'Kamerun',
        'SD' => 'Sudan',
        'GB' => 'Inggris Raya',
        'IE' => 'Irlandia',
        'FR' => 'Prancis',
        'DE' => 'Jerman',
        'NL' => 'Belanda',
        'BE' => 'Belgia',
        'LU' => 'Luksemburg',
        'CH' => 'Swiss',
        'AT' => 'Austria',
        'IT' => 'Italia',
        'ES' => 'Spanyol',
        'PT' => 'Portugal',
        'GR' => 'Yunani',
        'SE' => 'Swedia',
        'NO' => 'Norwegia',
        'DK' => 'Denmark',
        'FI' => 'Finlandia',
        'IS' => 'Islandia',
        'PL' => 'Polandia',
        'CZ' => 'Ceko',
        'SK' => 'Slovakia',
        'HU' => 'Hungaria',
        'RO' => 'Rumania',
        'BG' => 'Bulgaria',
        'HR' => 'Kroasia',
        'RS' => 'Serbia',
        'SI' => 'Slovenia',
        'BA' => 'Bosnia dan Herzegovina',
        'AL' => 'Albania',
        'MK' => 'Makedonia Utara',
        'UA' => 'Ukraina',
        'BY' => 'Belarus',
        'RU' => 'Rusia',
        'LT' => 'Lituania',
        'LV' => 'Latvia',
        'EE' => 'Estonia',
        'MT' => 'Malta',
        'CY' => 'Siprus',
        'KZ' => 'Kazakhstan',
        'UZ' => 'Uzbekistan',
        'AZ' => 'Azerbaijan',
        'GE' => 'Georgia',
        'AM' => 'Armenia',
        'US' => 'Amerika Serikat',
        'CA' => 'Kanada',
        'MX' => 'Meksiko',
        'BR' => 'Brasil',
        'AR' => 'Argentina',
        'CL' => 'Chili',
        'CO' => 'Kolombia',
        'PE' => 'Peru',
        'VE' => 'Venezuela',
        'EC' => 'Ekuador',
        'UY' => 'Uruguay',
        'PA' => 'Panama',
        'CR' => 'Kosta Rika',
        'DO' => 'Republik Dominika',
        'CU' => 'Kuba',
        'JM' => 'Jamaika',
        'FJ' => 'Fiji',
        'PG' => 'Papua Nugini',
        ];
    }

    public static function name(?string $codeOrName): ?string
    {
        $code = static::codeFor($codeOrName);

        return $code ? (static::all()[$code] ?? $codeOrName) : $codeOrName;
    }

    /**
     * Terima kode ISO ("ID") ATAU nama lama yang diketik bebas
     * ("indonesia", "Singapore") dan kembalikan kode ISO, atau null kalau
     * tidak dikenali.
     */
    public static function codeFor(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $all = static::all();
        $upper = strtoupper($value);

        if (strlen($value) === 2 && isset($all[$upper])) {
            return $upper;
        }

        $needle = mb_strtolower($value);

        foreach ($all as $code => $name) {
            if (mb_strtolower($name) === $needle) {
                return $code;
            }
        }

        $aliases = [
            'singapore' => 'SG', 'philippines' => 'PH', 'united states' => 'US', 'usa' => 'US', 'amerika' => 'US',
            'united kingdom' => 'GB', 'uk' => 'GB', 'inggris' => 'GB', 'japan' => 'JP', 'china' => 'CN', 'cina' => 'CN',
            'germany' => 'DE', 'france' => 'FR', 'netherlands' => 'NL', 'brunei' => 'BN', 'timor-leste' => 'TL',
            'korea' => 'KR', 'saudi arabia' => 'SA', 'uae' => 'AE', 'turkey' => 'TR',
        ];

        return $aliases[$needle] ?? null;
    }
}
