<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = ['key', 'subject', 'body_mail', 'body_whatsapp', 'body_sms'];

    /**
     * Daftar template yang bisa diedit, kata-katanya persis diambil
     * dari kelas Notification masing-masing (bukan ditulis ulang) —
     * supaya begitu fitur ini dipasang, tidak ada satu pun kalimat yang
     * berubah sampai admin sendiri yang mengeditnya.
     *
     * {variabel} diganti otomatis saat dikirim — daftar variabel yang
     * tersedia beda-beda tiap template, lihat 'variables' masing-masing.
     *
     * `body_sms` SENGAJA cuma diisi untuk sebagian kecil template (OTP
     * login klien, tagihan baru, layanan disuspend, alert admin) — SMS
     * berbayar per pesan, jadi dibatasi ke yang benar-benar mendesak,
     * beda dari email/WhatsApp yang mencakup semua kejadian. Ditulis
     * pendek & polos (tanpa markdown) karena SMS dikenakan biaya per
     * segmen 160 karakter.
     *
     * Beberapa notifikasi (AdminAlert, OrderProvisioned, PromoBroadcast)
     * punya bagian yang isinya BERBEDA-BEDA tiap kejadian (daftar rincian,
     * daftar kredensial hosting, isi promo bebas dari admin) — bagian itu
     * TETAP diatur lewat kode seperti biasa, cuma bagian "bungkusnya"
     * (sapaan, kalimat pembuka/penutup) yang bisa diedit di sini.
     */
    public static function defaults(): array
    {
        return [
            'client_welcome' => [
                'label' => 'Selamat Datang Klien Baru',
                'subject' => 'Selamat datang di {site_name}',
                'body_mail' => "Terima kasih sudah mendaftar di {site_name}. Akun Anda sudah aktif dan siap digunakan.\n\nLewat halaman klien, Anda bisa memesan layanan, melihat tagihan, mengelola domain, dan menghubungi tim support kapan saja.\n\n[ACTION:Buka Halaman Klien:{dashboard_url}]\n\nKalau ada yang perlu dibantu, balas email ini atau buat tiket support.",
                'body_whatsapp' => "Halo {client_name}, selamat datang di {site_name}!\n\nAkun Anda sudah aktif. Buka halaman klien untuk memesan layanan dan melihat tagihan:\n{dashboard_url}",
                'variables' => ['client_name', 'site_name', 'dashboard_url'],
            ],
            'invoice_created' => [
                'label' => 'Invoice Terbit',
                'subject' => 'Invoice {invoice_number} — {site_name}',
                'body_mail' => "Berikut tagihan untuk pesanan Anda.\n\n**Nomor Invoice:** {invoice_number}\n**Total:** {total}\n**Jatuh tempo:** {due_date}\n\n[ACTION:Lihat & Bayar Invoice:{invoice_url}]\n\nLayanan akan otomatis aktif setelah pembayaran kami terima.",
                'body_whatsapp' => "Halo {client_name},\n\nInvoice *{invoice_number}* sebesar *{total}* sudah terbit.\nJatuh tempo: {due_date}\n\nBayar di sini:\n{invoice_url}",
                'body_sms' => "Tagihan {invoice_number} sebesar {total} jatuh tempo {due_date}. Bayar: {invoice_url}",
                'variables' => ['client_name', 'site_name', 'invoice_number', 'total', 'due_date', 'invoice_url'],
            ],
            'invoice_paid' => [
                'label' => 'Pembayaran Diterima',
                'subject' => 'Pembayaran Diterima — {invoice_number}',
                'body_mail' => "Terima kasih, pembayaran sebesar **{total}** untuk invoice **{invoice_number}** sudah kami terima.\n\nLayanan Anda sedang diproses dan akan segera aktif.\n\n[ACTION:Lihat Layanan Saya:{services_url}]",
                'body_whatsapp' => "Halo {client_name},\n\nPembayaran *{total}* untuk invoice *{invoice_number}* sudah kami terima. Terima kasih!\n\nLayanan Anda sedang diproses.",
                'variables' => ['client_name', 'invoice_number', 'total', 'services_url'],
            ],
            'invoice_reminder_upcoming' => [
                'label' => 'Pengingat Tagihan (Sebelum Jatuh Tempo)',
                'subject' => 'Pengingat Tagihan — {invoice_number}',
                'body_mail' => "Tagihan **{invoice_number}** sebesar **{total}** akan jatuh tempo dalam **{days_left} hari** ({due_date}).\n\nKalau sudah dibayar, abaikan email ini.\n\n[ACTION:Bayar Sekarang:{invoice_url}]",
                'body_whatsapp' => "Halo {client_name},\n\nPengingat: tagihan *{invoice_number}* ({total}) jatuh tempo dalam {days_left} hari.\n\n{invoice_url}",
                'variables' => ['client_name', 'invoice_number', 'total', 'due_date', 'days_left', 'invoice_url'],
            ],
            'invoice_reminder_overdue' => [
                'label' => 'Pengingat Tagihan (Terlambat)',
                'subject' => 'Tagihan Terlambat — {invoice_number}',
                'body_mail' => "Tagihan **{invoice_number}** sebesar **{total}** sudah melewati jatuh tempo {days_late} hari.\n\nMohon segera diselesaikan agar layanan Anda tetap berjalan.\n\n[ACTION:Bayar Sekarang:{invoice_url}]",
                'body_whatsapp' => "Halo {client_name},\n\nTagihan *{invoice_number}* ({total}) sudah lewat jatuh tempo {days_late} hari.\n\nBayar di sini:\n{invoice_url}",
                'variables' => ['client_name', 'invoice_number', 'total', 'days_late', 'invoice_url'],
            ],
            'domain_needs_documents' => [
                'label' => 'Domain Butuh Dokumen (TLD Indonesia)',
                'subject' => 'Lengkapi Dokumen untuk Domain {domain_name}',
                'body_mail' => "Terima kasih sudah memesan domain **{domain_name}**.\n\nEkstensi **{tld}** mewajibkan dokumen identitas/legalitas sesuai ketentuan PANDI sebelum domain bisa diaktifkan.\n\n[ACTION:Upload Dokumen:{upload_url}]\n\nFormat file: ZIP, RAR, JPG, JPEG, atau PNG — maksimal 1 MB per file.\nProses verifikasi biasanya memakan waktu 2x24 jam kerja setelah dokumen lengkap kami terima.",
                'body_whatsapp' => "Halo {client_name},\n\nDomain *{domain_name}* ({tld}) butuh dokumen tambahan sebelum bisa diaktifkan.\n\nUpload di sini:\n{upload_url}",
                'variables' => ['client_name', 'domain_name', 'tld', 'upload_url'],
            ],
            'service_suspended' => [
                'label' => 'Layanan Disuspend',
                'subject' => 'Layanan Disuspend — {service_name}',
                'body_mail' => "Layanan **{service_name}** telah disuspend sementara karena tagihan **{invoice_number}** ({total}) belum dibayar hingga melewati batas waktu.\n\nLayanan akan aktif kembali secara otomatis begitu pembayaran kami terima — tidak perlu menghubungi kami untuk mengaktifkan ulang.\n\n[ACTION:Bayar Sekarang:{invoice_url}]\n\nKalau ada kendala, balas email ini atau buat tiket support.",
                'body_whatsapp' => "Halo {client_name},\n\nLayanan *{service_name}* disuspend sementara karena tagihan *{invoice_number}* ({total}) belum dibayar.\n\nBayar di sini untuk aktif kembali otomatis:\n{invoice_url}",
                'body_sms' => "Layanan {service_name} disuspend krn tagihan {invoice_number} ({total}) belum dibayar. Bayar utk aktif lagi: {invoice_url}",
                'variables' => ['client_name', 'service_name', 'invoice_number', 'total', 'invoice_url'],
            ],
            'ticket_replied' => [
                'label' => 'Balasan Tiket (ke Klien)',
                'subject' => 'Balasan Tiket {ticket_number} — {site_name}',
                'body_mail' => "**{staff_name}** membalas tiket Anda \"**{subject}**\":\n\n> {message}\n\n[ACTION:Lihat & Balas Tiket:{ticket_url}]",
                'body_whatsapp' => "Halo {client_name},\n\n*{staff_name}* membalas tiket *{ticket_number}*:\n\"{message}\"\n\nBalas di sini:\n{ticket_url}",
                'variables' => ['client_name', 'site_name', 'ticket_number', 'subject', 'staff_name', 'message', 'ticket_url'],
            ],
            'balance_topup_paid' => [
                'label' => 'Isi Ulang Saldo Berhasil',
                'subject' => 'Isi Ulang Saldo Berhasil',
                'body_mail' => "Isi ulang saldo sebesar **{amount}** sudah berhasil.\n\nSaldo Anda sekarang: **{new_balance}**.\n\nSaldo ini bisa langsung dipakai untuk membayar invoice berikutnya.\n\n[ACTION:Lihat Saldo Saya:{balance_url}]",
                'body_whatsapp' => "Halo {client_name},\n\nIsi ulang saldo sebesar *{amount}* berhasil.\nSaldo Anda sekarang: *{new_balance}*.",
                'variables' => ['client_name', 'amount', 'new_balance', 'balance_url'],
            ],
            'order_provisioned' => [
                'label' => 'Pesanan Sudah Diproses (bungkus pesan)',
                'subject' => 'Pesanan Anda Sudah Diproses',
                'body_mail' => "Pembayaran Anda telah kami terima dan pesanan sedang/sudah diproses. Berikut detailnya:\n\n[DAFTAR_LAYANAN]\n\n[ACTION:Lihat Layanan Saya:{services_url}]\n\nTerima kasih telah menggunakan layanan kami.",
                'body_whatsapp' => null,
                'variables' => ['client_name', 'services_url'],
                'note' => 'Baris "[DAFTAR_LAYANAN]" WAJIB ada — di situ sistem menyisipkan daftar akun hosting/domain yang baru dibuat (kredensial, dll), yang isinya berbeda tiap pesanan sehingga tidak bisa ditulis tetap di sini. Hanya kirim lewat email (tidak ada versi WhatsApp, karena memuat password).',
            ],
            'verify_email_code' => [
                'label' => 'Kode Verifikasi Email (Pendaftaran)',
                'subject' => 'Kode Verifikasi Email — {site_name}',
                'body_mail' => "Masukkan kode berikut untuk mengaktifkan akun Anda:\n\n**{code}**\n\nKode berlaku 30 menit.\n\nKalau Anda tidak mendaftar di {site_name}, abaikan saja email ini.",
                'body_whatsapp' => null,
                'variables' => ['client_name', 'site_name', 'code'],
                'note' => 'Sengaja hanya email — tujuannya membuktikan kepemilikan alamat email itu sendiri.',
            ],
            'send_otp_code' => [
                'label' => 'Kode OTP Login Admin',
                'subject' => 'Kode Verifikasi Login — {site_name}',
                'body_mail' => "Berikut kode verifikasi untuk melanjutkan login ke admin panel:\n\n**{code}**\n\nKode ini berlaku 10 menit dan hanya bisa dipakai sekali.\n\nKalau Anda tidak sedang mencoba login, segera ganti password akun Anda — ada kemungkinan orang lain mengetahui kredensial Anda.",
                'body_whatsapp' => null,
                'variables' => ['admin_name', 'site_name', 'code'],
            ],
            'send_client_otp_code' => [
                'label' => 'Kode OTP Login Klien',
                'subject' => 'Kode Verifikasi Login — {site_name}',
                'body_mail' => "Berikut kode verifikasi untuk melanjutkan login ke akun Anda:\n\n**{code}**\n\nKode ini berlaku 10 menit dan hanya bisa dipakai sekali.\n\nKalau Anda tidak sedang mencoba login, segera ganti password akun Anda — ada kemungkinan orang lain mengetahui kredensial Anda.",
                'body_whatsapp' => null,
                'body_sms' => "Kode verifikasi login {site_name}: {code}. Berlaku 10 menit, jangan bagikan ke siapa pun.",
                'variables' => ['client_name', 'site_name', 'code'],
            ],
            'send_password_reset_code' => [
                'label' => 'Kode Reset Password Admin',
                'subject' => 'Kode Reset Password — {site_name}',
                'body_mail' => "Kami menerima permintaan untuk mengatur ulang password akun Anda. Gunakan kode berikut:\n\n**{code}**\n\nKode berlaku 15 menit dan hanya bisa dipakai sekali.\n\nKalau Anda tidak meminta reset password, abaikan email ini — password Anda tidak berubah.",
                'body_whatsapp' => null,
                'variables' => ['admin_name', 'site_name', 'code'],
            ],
            'admin_alert' => [
                'label' => 'Notifikasi ke Admin (bungkus pesan)',
                'subject' => '[{site_name}] {judul}',
                'body_mail' => "{judul}\n\n[RINCIAN]",
                'body_whatsapp' => "*{judul}*\n[RINCIAN]",
                'body_sms' => "{site_name}: {judul}",
                'variables' => ['admin_name', 'site_name', 'judul'],
                'note' => 'Baris "[RINCIAN]" WAJIB ada di mail & WhatsApp — di situ sistem menyisipkan detail yang berbeda tiap jenis kejadian (pesanan masuk, pembayaran diterima, tiket baru, dll), termasuk tautan ke admin panel kalau tersedia. Versi SMS SENGAJA tidak memuat [RINCIAN] (supaya tetap 1 segmen) — sistem otomatis menambahkan link admin panel di akhir pesan kalau tersedia.',
            ],
            'promo_broadcast' => [
                'label' => 'Promo/Broadcast (bungkus pesan)',
                'subject' => null,
                'body_mail' => "[ISI_PROMO]\n\n---\nTidak ingin menerima email promosi? Matikan lewat menu Profil Saya di halaman klien.",
                'body_whatsapp' => "[ISI_PROMO]",
                'variables' => ['client_name'],
                'note' => 'Judul & isi promo diketik admin sendiri setiap kali kirim broadcast (lewat menu Broadcast Promo) — bagian ini cuma mengatur "bungkus" tetapnya (sapaan, footer opt-out). Baris "[ISI_PROMO]" WAJIB ada.',
            ],
        ];
    }

    /**
     * Ambil template efektif untuk satu key — hasil gabungan bawaan +
     * perubahan admin (kalau ada). Dipanggil dari tiap kelas Notification.
     *
     * @return array{subject: ?string, body_mail: ?string, body_whatsapp: ?string, body_sms: ?string}
     */
    public static function effective(string $key): array
    {
        $default = static::defaults()[$key] ?? ['subject' => null, 'body_mail' => null, 'body_whatsapp' => null, 'body_sms' => null];
        $override = static::where('key', $key)->first();

        return [
            'subject' => filled($override?->subject) ? $override->subject : $default['subject'],
            'body_mail' => filled($override?->body_mail) ? $override->body_mail : $default['body_mail'],
            'body_whatsapp' => filled($override?->body_whatsapp) ? $override->body_whatsapp : $default['body_whatsapp'],
            'body_sms' => filled($override?->body_sms) ? $override->body_sms : ($default['body_sms'] ?? null),
        ];
    }

    /**
     * Ganti {variabel} dengan nilai sungguhan. Placeholder yang tidak
     * dikenali dibiarkan apa adanya (bukan dihapus) — supaya kalau admin
     * salah ketik nama variabel, jelas terlihat di email yang terkirim,
     * bukan diam-diam hilang.
     */
    public static function substitute(?string $text, array $data): string
    {
        if (blank($text)) {
            return '';
        }

        foreach ($data as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }

        return $text;
    }
}
