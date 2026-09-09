<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;

/**
 * Menentukan lewat channel mana sebuah notifikasi dikirim.
 *
 * Aturannya:
 *  - Email transaksional (invoice, tagihan, tiket, layanan aktif) SELALU
 *    dikirim. Ini bagian dari layanan, bukan promosi — mematikannya akan
 *    membuat pelanggan tidak tahu tagihannya.
 *  - Email promosi hanya kalau klien tidak menolaknya.
 *  - WhatsApp hanya kalau (a) gateway diaktifkan admin, DAN (b) klien
 *    sendiri mengaktifkannya di profil. Dua-duanya harus setuju.
 *  - SMS sama syaratnya dengan WhatsApp (gateway aktif + klien opt-in),
 *    DITAMBAH notifikasinya sendiri harus eksplisit menyediakan
 *    toSms() — SMS berbayar per pesan, jadi tidak semua notifikasi ikut
 *    otomatis seperti WhatsApp.
 */
trait ResolvesChannels
{
    /**
     * Apakah notifikasi ini bersifat promosi? Ditimpa di kelas promo.
     */
    protected bool $isPromotional = false;

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->wantsEmail($notifiable)) {
            $channels[] = 'mail';
        }

        if ($this->wantsWhatsApp($notifiable)) {
            $channels[] = WhatsAppChannel::class;
        }

        if ($this->wantsSms($notifiable)) {
            $channels[] = SmsChannel::class;
        }

        if ($this->wantsPush($notifiable)) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    private function wantsEmail(object $notifiable): bool
    {
        if (blank($notifiable->email ?? null)) {
            return false;
        }

        // Klien bisa menolak email promosi, tapi tidak email transaksional.
        if ($this->isPromotional && isset($notifiable->notify_promo)) {
            return (bool) $notifiable->notify_promo;
        }

        return true;
    }

    private function wantsWhatsApp(object $notifiable): bool
    {
        // Gateway belum diatur admin — tidak ada yang bisa dikirim.
        if (Setting::get('wa_provider', 'none') === 'none' || blank(Setting::get('wa_token'))) {
            return false;
        }

        if (! method_exists($this, 'toWhatsApp')) {
            return false;
        }

        $number = $notifiable->whatsapp_number ?? $notifiable->phone ?? null;

        if (blank($number)) {
            return false;
        }

        // Admin (model Admin) tidak punya kolom opt-in; keputusannya ada
        // di pengaturan global.
        if (! isset($notifiable->notify_whatsapp)) {
            return true;
        }

        return (bool) $notifiable->notify_whatsapp;
    }

    private function wantsSms(object $notifiable): bool
    {
        // Gateway belum diatur admin — tidak ada yang bisa dikirim.
        if (Setting::get('sms_provider', 'none') === 'none') {
            return false;
        }

        if (! method_exists($this, 'toSms')) {
            return false;
        }

        $number = $notifiable->phone ?? null;

        if (blank($number)) {
            return false;
        }

        // Admin (model Admin) tidak punya kolom opt-in; keputusannya ada
        // di pengaturan global, sama seperti WhatsApp.
        if (! isset($notifiable->notify_sms)) {
            return true;
        }

        return (bool) $notifiable->notify_sms;
    }

    /**
     * Beda dari WhatsApp/SMS (butuh toggle eksplisit), Push tidak punya
     * kolom "notify_push" tersendiri -- keberadaan baris di
     * push_subscriptions ITU SENDIRI sudah membuktikan orangnya pernah
     * menekan "Izinkan" di browser, jadi sudah cukup sebagai bukti
     * persetujuan. Kalau dia belum pernah subscribe sama sekali, method
     * ini otomatis false karena query di bawah kosong.
     *
     * Untuk promosi tetap dicek notify_promo terpisah (SENGAJA lebih
     * ketat dari WhatsApp yang belum membedakan ini) -- izin browser
     * push mudah dicabut sepihak begitu situs dianggap spam oleh
     * pengguna, jadi lebih aman tidak dipakai sembarangan untuk promo.
     */
    private function wantsPush(object $notifiable): bool
    {
        if (blank(Setting::get('vapid_public_key'))) {
            return false;
        }

        if (! method_exists($this, 'toPush')) {
            return false;
        }

        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return false;
        }

        if ($this->isPromotional && isset($notifiable->notify_promo) && ! $notifiable->notify_promo) {
            return false;
        }

        return $notifiable->pushSubscriptions()->exists();
    }
}
