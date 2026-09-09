<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Ubah teks template tersimpan (baris per baris, dipisah "\n") jadi
 * pemanggilan ->line()/->action() sungguhan di MailMessage.
 *
 * Baris berbentuk "[ACTION:Label Tombol:https://...]" diubah jadi tombol
 * aksi, baris lain diperlakukan sebagai paragraf biasa — supaya admin
 * bisa menaruh tombol di tengah isi email lewat teks biasa di form,
 * tanpa perlu tahu soal kode.
 */
trait UsesNotificationTemplate
{
    protected function applyTemplateBody(MailMessage $mail, string $body): MailMessage
    {
        foreach (explode("\n", $body) as $line) {
            $line = rtrim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^\[ACTION:(.+?):(.+)\]$/', $line, $m)) {
                $mail->action(trim($m[1]), trim($m[2]));
                continue;
            }

            $mail->line($line);
        }

        return $mail;
    }

    /**
     * Sisipkan banner promo (kalau admin sudah mengaktifkan satu untuk
     * halaman "Email Transaksional" di menu Banner Promo) sebagai baris
     * gambar terakhir sebelum salam penutup. Dipakai sintaks Markdown
     * gambar biasa ![...](...) -- bukan tag <img> mentah -- supaya pasti
     * dirender jadi gambar oleh parser Markdown email, bukan malah
     * di-escape jadi teks.
     *
     * Sengaja TIDAK dipasang otomatis di semua notifikasi (mis. OTP,
     * reset password) -- cuma dipanggil eksplisit dari notifikasi yang
     * relevan secara komersial (invoice, selamat datang, dll).
     */
    protected function applyPromoBanner(MailMessage $mail): MailMessage
    {
        $banner = \App\Models\PromoBanner::live()->forPage('email')->orderBy('sort_order')->first();

        if (! $banner) {
            return $mail;
        }

        $imageUrl = route('banner.file', $banner->image);
        $markdown = "![" . ($banner->title ?: 'Promo') . "]({$imageUrl})";

        if ($banner->link_url) {
            $target = str_starts_with($banner->link_url, 'http') ? $banner->link_url : url($banner->link_url);
            $markdown = "[{$markdown}]({$target})";
        }

        return $mail->line($markdown);
    }
}
