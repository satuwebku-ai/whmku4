<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Tampilan notifikasi (toast) di panel admin.
 *
 * Semua nilainya disimpan di tabel settings (kunci berawalan "toast_")
 * dan diatur dari Pengaturan → Tampilan Notifikasi. Nilai yang belum
 * pernah disimpan memakai DEFAULT di bawah, jadi panel tetap tampil
 * benar sebelum admin mengubah apa pun.
 */
class ToastStyle
{
    public const TYPES = [
        'success' => 'Sukses',
        'error' => 'Error',
        'warning' => 'Peringatan',
        'info' => 'Info',
    ];

    public const POSITIONS = [
        'top-right' => 'Kanan atas',
        'top-center' => 'Tengah atas',
        'top-left' => 'Kiri atas',
        'bottom-right' => 'Kanan bawah',
        'bottom-center' => 'Tengah bawah',
        'bottom-left' => 'Kiri bawah',
    ];

    /** Warna per tipe: [latar, garis tepi, teks & ikon]. */
    public const PRESETS = [
        'soft' => [
            'label' => 'Lembut',
            'success' => ['#ecfdf5', '#a7f3d0', '#065f46'],
            'error' => ['#fef2f2', '#fecaca', '#991b1b'],
            'warning' => ['#fffbeb', '#fde68a', '#92400e'],
            'info' => ['#eff6ff', '#bfdbfe', '#1e40af'],
        ],
        'solid' => [
            'label' => 'Solid',
            'success' => ['#059669', '#047857', '#ffffff'],
            'error' => ['#dc2626', '#b91c1c', '#ffffff'],
            'warning' => ['#d97706', '#b45309', '#ffffff'],
            'info' => ['#2563eb', '#1d4ed8', '#ffffff'],
        ],
        'dark' => [
            'label' => 'Gelap',
            'success' => ['#1f2937', '#10b981', '#f9fafb'],
            'error' => ['#1f2937', '#f43f5e', '#f9fafb'],
            'warning' => ['#1f2937', '#f59e0b', '#f9fafb'],
            'info' => ['#1f2937', '#38bdf8', '#f9fafb'],
        ],
    ];

    public const COLOR_PARTS = ['bg' => 'Latar', 'border' => 'Garis tepi', 'text' => 'Teks & ikon'];

    /**
     * Nilai bawaan seluruh pengaturan (warna memakai preset "soft").
     */
    public static function defaults(): array
    {
        $defaults = [
            'toast_position' => 'top-right',
            'toast_duration' => '4',
            'toast_duration_error' => '8',
            'toast_width' => '360',
            'toast_radius' => '14',
            'toast_show_icon' => '1',
            'toast_show_progress' => '1',
            'toast_shadow' => '1',
        ];

        return $defaults + self::presetValues('soft');
    }

    /**
     * Preset dipecah jadi kunci setting datar: toast_success_bg, dst.
     */
    public static function presetValues(string $preset): array
    {
        $values = [];

        foreach (self::PRESETS[$preset] ?? self::PRESETS['soft'] as $type => $colors) {
            if (! is_array($colors)) {
                continue;
            }

            foreach (array_keys(self::COLOR_PARTS) as $i => $part) {
                $values["toast_{$type}_{$part}"] = $colors[$i];
            }
        }

        return $values;
    }

    /**
     * Pengaturan aktif = default + yang tersimpan (nilai rusak dibuang).
     */
    public static function current(): array
    {
        $current = self::defaults();

        foreach ($current as $key => $default) {
            $saved = Setting::get($key);

            if ($saved !== null && $saved !== '' && self::isValid($key, (string) $saved)) {
                $current[$key] = (string) $saved;
            }
        }

        return $current;
    }

    public static function isValid(string $key, string $value): bool
    {
        return match (true) {
            $key === 'toast_position' => isset(self::POSITIONS[$value]),
            $key === 'toast_duration', $key === 'toast_duration_error' => ctype_digit($value) && (int) $value >= 1 && (int) $value <= 60,
            $key === 'toast_width' => ctype_digit($value) && (int) $value >= 260 && (int) $value <= 640,
            $key === 'toast_radius' => ctype_digit($value) && (int) $value <= 32,
            in_array($key, ['toast_show_icon', 'toast_show_progress', 'toast_shadow'], true) => in_array($value, ['0', '1'], true),
            default => (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value),
        };
    }

    /**
     * Isi blok CSS custom property untuk :root.
     */
    public static function cssVars(array $s): string
    {
        $vars = [
            '--lt-width' => (int) $s['toast_width'] . 'px',
            '--lt-radius' => (int) $s['toast_radius'] . 'px',
            '--lt-shadow' => $s['toast_shadow'] === '1'
                ? '0 10px 30px rgba(15,23,42,.18), 0 2px 6px rgba(15,23,42,.08)'
                : 'none',
        ];

        foreach (array_keys(self::TYPES) as $type) {
            foreach (array_keys(self::COLOR_PARTS) as $part) {
                $vars["--lt-{$type}-{$part}"] = $s["toast_{$type}_{$part}"];
            }
        }

        $css = '';

        foreach ($vars as $name => $value) {
            $css .= "{$name}:{$value};";
        }

        return $css;
    }

    /**
     * Konfigurasi perilaku untuk JavaScript.
     */
    public static function jsConfig(array $s): array
    {
        return [
            'duration' => (int) $s['toast_duration'] * 1000,
            'durationError' => (int) $s['toast_duration_error'] * 1000,
            'showIcon' => $s['toast_show_icon'] === '1',
            'showProgress' => $s['toast_show_progress'] === '1',
        ];
    }
}
