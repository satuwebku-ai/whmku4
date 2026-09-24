<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function general(): View
    {
        return view('admin.settings.general');
    }

    public function generalBootstrap(): View
    {
        return view('admin.settings.general');
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name'        => ['required', 'string', 'max:120'],
            'site_tagline'     => ['nullable', 'string', 'max:255'],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'support_email'    => ['nullable', 'email', 'max:255'],
            'support_phone'    => ['nullable', 'string', 'max:50'],
            'company_address'  => ['nullable', 'string', 'max:500'],
            'footer_text'      => ['nullable', 'string', 'max:500'],
            'theme_color'      => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_display' => ['nullable', 'in:logo_and_text,logo_only,text_only'],

            'site_logo'        => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:1024'],
            'site_favicon'     => ['nullable', 'image', 'mimes:png,ico,svg', 'max:256'],
        ], [
            'theme_color.regex' => 'Warna harus dalam format heksadesimal, contoh #6366F1.',
            'site_logo.max'     => 'Ukuran logo maksimal 1 MB.',
            'site_favicon.max'  => 'Ukuran favicon maksimal 256 KB.',
        ]);

        // Logo & favicon disimpan lewat Storage disk 'local'
        // (storage/app/branding) dan dilayani lewat rute Laravel — BUKAN
        // ditulis langsung ke folder public/, karena di beberapa server
        // (termasuk yang pakai cPanel Git Version Control) folder kode
        // yang dieksekusi PHP itu TERPISAH dari folder yang benar-benar
        // dilayani ke publik. Lewat rute Laravel, ini kebal terhadap
        // perbedaan struktur folder apa pun.
        foreach (['site_logo', 'site_favicon'] as $field) {
            unset($data[$field]);
            $old = Setting::get($field);
            $hasNewFile = $request->hasFile($field);

            if ($hasNewFile) {
                $filename = $field . '_' . time() . '.' . $request->file($field)->getClientOriginalExtension();
                $stored = $request->file($field)->storeAs('branding', $filename, 'local');

                if (! $stored) {
                    throw new \RuntimeException("Gagal menyimpan {$field}.");
                }

                // Hapus file lama hanya setelah upload baru berhasil.
                if ($old && Storage::disk('local')->exists('branding/' . $old)) {
                    Storage::disk('local')->delete('branding/' . $old);
                }

                $data[$field] = $filename;
            }

            // Jika admin sekaligus mengunggah file baru, upload baru menang.
            if (! $hasNewFile && $request->boolean('remove_' . $field)) {
                if ($old && Storage::disk('local')->exists('branding/' . $old)) {
                    Storage::disk('local')->delete('branding/' . $old);
                }

                $data[$field] = null;
            }
        }

        Setting::putMany($data, 'general');

        return back()->with('success', 'Pengaturan umum berhasil disimpan.');
    }

    /**
     * Grup & warna preset logo yang tersedia -- filenya sendiri ada di
     * storage/app/branding-presets/{group}/{color}.png (file fisik,
     * BUKAN base64 ditanam di halaman -- versi sebelumnya begitu dan
     * bikin halaman >1.5MB sekali render, ketahuan bikin PHP kehabisan
     * memori di shared hosting sampai halamannya blank).
     */
    private const BRANDING_PRESET_GROUPS = [
        'logo'     => ['label' => 'Logo Lengkap (ikon + nama)', 'target' => 'site_logo'],
        'icon'     => ['label' => 'Ikon Saja (buat sidebar diciutkan)', 'target' => 'site_icon'],
        'wordmark' => ['label' => 'Teks Saja (tanpa ikon)', 'target' => 'site_logo'],
        'favicon'  => ['label' => 'Favicon', 'target' => 'site_favicon'],
    ];

    private const BRANDING_PRESET_COLORS = [
        'indigo' => 'Indigo', 'blue' => 'Biru', 'emerald' => 'Emerald', 'teal' => 'Teal',
        'amber' => 'Amber', 'rose' => 'Rose', 'slate' => 'Slate', 'graywhite' => 'Abu-Putih', 'white' => 'Putih',
    ];

    /**
     * Melayani gambar preset (dipakai <img src="..."> di galeri) --
     * dibaca langsung dari disk 'local', tidak ikut serta di HTML
     * halamannya sama sekali.
     */
    public function presetImage(string $group, string $color)
    {
        if (! isset(self::BRANDING_PRESET_GROUPS[$group]) || ! isset(self::BRANDING_PRESET_COLORS[$color])) {
            abort(404);
        }

        $path = "branding-presets/{$group}/{$color}.png";

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File preset belum diupload ke server.');
        }

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Pakai logo/favicon dari galeri preset. Cukup kirim nama grup +
     * warna (string pendek) -- filenya dibaca & disalin di server,
     * tidak perlu kirim data gambar bolak-balik lewat request sama
     * sekali. Disimpan lewat jalur yang SAMA dengan upload manual
     * (Storage disk 'local', folder branding), supaya konsisten dengan
     * cara logo dilayani ke publik.
     */
    public function usePresetBranding(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', 'in:' . implode(',', array_keys(self::BRANDING_PRESET_GROUPS))],
            'color' => ['required', 'in:' . implode(',', array_keys(self::BRANDING_PRESET_COLORS))],
            // Target BOLEH dipilih bebas oleh admin (mis. gambar dari
            // grup "Ikon Saja" tetap bisa dipakai untuk Logo Utama, bukan
            // cuma untuk posisi sidebar diciutkan) -- kalau tidak
            // dikirim, jatuh balik ke target bawaan grupnya.
            'target' => ['nullable', 'in:site_logo,site_icon,site_favicon'],
        ]);

        $group = self::BRANDING_PRESET_GROUPS[$data['group']];
        $field = $data['target'] ?? $group['target'];
        $sourcePath = "branding-presets/{$data['group']}/{$data['color']}.png";

        if (! Storage::disk('local')->exists($sourcePath)) {
            $error = 'File preset ini belum ada di server. Cek lagi folder storage/app/branding-presets/ sudah terupload lengkap.';

            return $request->ajax()
                ? response()->json(['message' => $error], 422)
                : back()->with('error', $error);
        }

        $old = Setting::get($field);
        if ($old && Storage::disk('local')->exists('branding/' . $old)) {
            Storage::disk('local')->delete('branding/' . $old);
        }

        $filename = $field . '_preset_' . time() . '.png';
        Storage::disk('local')->makeDirectory('branding');
        Storage::disk('local')->put('branding/' . $filename, Storage::disk('local')->get($sourcePath));

        Setting::put($field, $filename, 'general');

        $label = match ($field) {
            'site_logo' => 'Logo Utama',
            'site_icon' => 'Ikon Sidebar Kecil',
            default => 'Favicon',
        };
        $colorLabel = self::BRANDING_PRESET_COLORS[$data['color']];
        $message = "{$label} berhasil diganti ke preset {$group['label']} - {$colorLabel}.";

        return $request->ajax()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    public function pdfInvoice(): View
    {
        return view('admin.settings.pdf-invoice');
    }

    public function updatePdfInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pdf_show_logo'    => ['nullable', 'boolean'],
            'pdf_tax_id'       => ['nullable', 'string', 'max:50'],
            'pdf_payment_info' => ['nullable', 'string', 'max:1000'],
            'pdf_notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $data['pdf_show_logo'] = $request->boolean('pdf_show_logo') ? '1' : '0';

        Setting::putMany($data, 'general');

        return back()->with('success', 'Pengaturan PDF invoice berhasil disimpan.');
    }

    public function pdfInvoicePreview()
    {
        // Invoice contoh -- TIDAK disimpan ke database (new Invoice(),
        // bukan create()), cuma dipakai sekali untuk merender PDF-nya.
        $invoice = new \App\Models\Invoice([
            'invoice_number' => 'INV-CONTOH-0001',
            'amount' => 150000,
            'tax' => 0,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
        ]);

        $invoice->setRelation('client', new \App\Models\Client([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.com',
            'company' => null,
        ]));

        $invoice->setRelation('items', collect([
            new \App\Models\InvoiceItem([
                'description' => 'Perpanjangan Hosting — contohsitus.my.id (Starter Host 1000, bulanan)',
                'amount' => 150000,
            ]),
        ]));

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('client.invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4')
            ->stream('Contoh-Invoice.pdf');
    }

    /**
     * Halaman muka Pengaturan -- grid kartu berisi semua sub-menu
     * pengaturan. Sebelumnya /admin/settings tidak punya halaman
     * sendiri, jadi admin harus tahu URL sub-menunya atau lewat tab.
     */
    public function index(): View
    {
        $cards = [
            ['label' => 'Pengaturan Umum', 'desc' => 'Identitas bisnis, logo, favicon, warna tema.', 'icon' => 'fa-gear', 'route' => 'admin.settings.general'],
            ['label' => 'Halaman Depan', 'desc' => 'Susunan & isi section di beranda situs publik.', 'icon' => 'fa-house', 'route' => 'admin.settings.homepage'],
            ['label' => 'Persyaratan Berkas', 'desc' => 'Jenis berkas yang diwajibkan saat pesan domain.', 'icon' => 'fa-file-shield', 'route' => 'admin.settings.requirements.index'],
            ['label' => 'PDF Invoice', 'desc' => 'Kop, NPWP, info pembayaran & catatan kaki PDF.', 'icon' => 'fa-file-invoice', 'route' => 'admin.settings.pdf-invoice'],
            ['label' => 'SEO', 'desc' => 'Judul, deskripsi, dan meta tag halaman publik.', 'icon' => 'fa-magnifying-glass', 'route' => 'admin.settings.seo'],
            ['label' => 'Analytics', 'desc' => 'Google Analytics dan skrip pelacakan lain.', 'icon' => 'fa-chart-line', 'route' => 'admin.settings.analytics'],
            ['label' => 'Affiliate', 'desc' => 'Komisi default, jenis komisi, dan minimal payout program affiliate.', 'icon' => 'fa-user-group', 'route' => 'admin.settings.affiliate'],
            ['label' => 'Notifikasi', 'desc' => 'Pengaturan pengiriman email & WhatsApp.', 'icon' => 'fa-bell', 'route' => 'admin.settings.notifications'],
            ['label' => 'Keamanan', 'desc' => 'Autentikasi dua faktor & pembatasan akses.', 'icon' => 'fa-lock', 'route' => 'admin.settings.security'],
            ['label' => 'Live Chat', 'desc' => 'Widget chat, pesan sambutan, bot AI.', 'icon' => 'fa-comments', 'route' => 'admin.settings.livechat'],
            ['label' => 'Trafik AI', 'desc' => 'Pemakaian token & perkiraan biaya AI.', 'icon' => 'fa-robot', 'route' => 'admin.ai-usage.index'],
            ['label' => 'cPanel Aplikasi', 'desc' => 'Pintasan cepat ke panel hosting sendiri.', 'icon' => 'fa-server', 'route' => 'admin.self-cpanel.edit'],
            ['label' => 'Cron Jobs', 'desc' => 'Tugas terjadwal & status terakhir dijalankan.', 'icon' => 'fa-clock', 'route' => 'admin.cron.index'],
        ];

        return view('admin.settings.index', compact('cards'));
    }

    public function homepage(): View
    {
        // Status tiap banner beserta ALASAN kenapa tidak tampil.
        // Banner beranda sering "hilang" bukan karena bug, tapi karena
        // nonaktif / tanggal mulai belum tiba / tanggal berakhir sudah
        // lewat / ditujukan ke halaman lain -- semuanya tidak kelihatan
        // dari halaman ini sebelumnya, jadi susah dilacak.
        $banners = \App\Models\PromoBanner::orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($b) {
                $reasons = [];

                if (! $b->is_active) {
                    $reasons[] = 'Nonaktif';
                }

                if ($b->starts_at && $b->starts_at->isAfter(now())) {
                    $reasons[] = 'Belum mulai (' . $b->starts_at->format('d M Y') . ')';
                }

                if ($b->ends_at && $b->ends_at->isBefore(now()->startOfDay())) {
                    $reasons[] = 'Sudah berakhir (' . $b->ends_at->format('d M Y') . ')';
                }

                if (! in_array($b->display_page, ['home', 'all'], true)) {
                    $reasons[] = 'Ditujukan ke: ' . (\App\Models\PromoBanner::PAGES[$b->display_page] ?? $b->display_page);
                }

                return [
                    'id' => $b->id,
                    'title' => trim((string) $b->title) === '-' ? '(tanpa judul)' : $b->title,
                    'page' => \App\Models\PromoBanner::PAGES[$b->display_page] ?? $b->display_page,
                    'shows_on_home' => empty($reasons),
                    'reasons' => $reasons,
                ];
            });

        // Daftar section beranda + urutan tersimpan. 'empty_hint'
        // menjelaskan kapan section otomatis tersembunyi walau
        // toggle-nya menyala.
        $sectionMeta = [
            'domain'        => ['label' => 'Pencarian Domain', 'desc' => 'Hero + kotak cek domain & harga TLD populer.', 'empty' => null],
            'banner'        => ['label' => 'Banner Promo', 'desc' => 'Carousel banner beranda.', 'empty' => 'tidak ada banner aktif untuk Beranda'],
            'benefits'      => ['label' => 'Keunggulan', 'desc' => '4 kartu "Aktif Otomatis", "Aman & Terjaga", dst.', 'empty' => null],
            'hosting'       => ['label' => 'Paket Hosting', 'desc' => 'Paket hosting unggulan (non-VPS).', 'empty' => 'belum ada produk hosting'],
            'vps'           => ['label' => 'VPS & Cloud Server', 'desc' => 'Paket VPS (produk yang memakai server cloud).', 'empty' => 'belum ada produk VPS'],
            'categories'    => ['label' => 'Layanan Kami', 'desc' => 'Grid kategori produk.', 'empty' => 'belum ada kategori berisi produk'],
            'announcements' => ['label' => 'Kabar Terbaru', 'desc' => 'Pengumuman yang dipublikasikan.', 'empty' => 'belum ada pengumuman terbit'],
            'cta'           => ['label' => 'Ajakan Daftar', 'desc' => 'Kartu "Siap memulai website-mu?".', 'empty' => null],
        ];

        $savedOrder = json_decode((string) \App\Models\Setting::get('home_section_order'), true);
        $order = is_array($savedOrder) && $savedOrder ? $savedOrder : array_keys($sectionMeta);
        $order = array_values(array_unique(array_merge(
            array_values(array_intersect($order, array_keys($sectionMeta))),
            array_keys($sectionMeta)
        )));

        return view('admin.settings.homepage', compact('banners', 'sectionMeta', 'order'));
    }

    public function updateHomepage(Request $request): RedirectResponse
    {
        $sectionKeys = ['domain', 'banner', 'benefits', 'hosting', 'vps', 'categories', 'announcements', 'cta'];

        $data = $request->validate([
            'home_categories_limit'    => ['required', 'integer', 'min:0', 'max:24'],
            'home_featured_limit'      => ['required', 'integer', 'min:1', 'max:12'],
            'home_vps_limit'           => ['required', 'integer', 'min:1', 'max:12'],
            'home_announcements_limit' => ['required', 'integer', 'min:1', 'max:12'],
            'section_order'            => ['nullable', 'string'],
        ]);

        foreach ($sectionKeys as $key) {
            $data['home_show_' . $key] = $request->boolean('home_show_' . $key) ? '1' : '0';
        }

        // Urutan dikirim sebagai daftar dipisah koma dari input tersembunyi
        // yang diperbarui saat baris di-drag. Disaring ke section yang
        // dikenal saja, lalu section yang hilang ditambahkan di belakang --
        // supaya urutan tersimpan tidak pernah "kehilangan" section.
        $order = array_values(array_intersect(
            array_filter(array_map('trim', explode(',', (string) ($data['section_order'] ?? '')))),
            $sectionKeys
        ));

        $order = array_values(array_unique(array_merge($order, $sectionKeys)));

        $data['home_section_order'] = json_encode($order);
        unset($data['section_order']);

        Setting::putMany($data, 'general');

        return back()->with('success', 'Pengaturan halaman depan berhasil disimpan.');
    }

    public function seo(): View
    {
        return view('admin.settings.seo');
    }

    public function seoBootstrap(): View
    {
        return view('admin.settings.seo');
    }

    /**
     * Alat diagnosa upload logo/favicon.
     */
    public function brandingDiagnostics(): \Illuminate\Http\JsonResponse
    {
        $logo = Setting::get('site_logo');

        return response()->json([
            'metode' => 'Logo dan favicon dilayani lewat rute Laravel dari disk local, bukan file statis.',
            'folder_branding_ada' => Storage::disk('local')->exists('branding'),
            'logo_tersimpan' => $logo,
            'logo_file_ada' => $logo ? Storage::disk('local')->exists('branding/' . $logo) : null,
            'logo_url' => $logo ? route('branding.file', $logo) : null,
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function updateSeo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seo_title'        => ['nullable', 'string', 'max:70'],
            'seo_description'  => ['nullable', 'string', 'max:170'],
            'seo_keywords'     => ['nullable', 'string', 'max:255'],
            'seo_og_image'     => ['nullable', 'string', 'max:255'],
            'seo_canonical'    => ['nullable', 'url', 'max:255'],
            'seo_robots'       => ['nullable', 'string', 'max:2000'],
            'seo_noindex_site' => ['nullable', 'boolean'],
        ]);

        $data['seo_noindex_site'] = $request->boolean('seo_noindex_site') ? '1' : '0';

        Setting::putMany($data, 'seo');

        return back()->with('success', 'Pengaturan SEO berhasil disimpan.');
    }

    public function analytics(): View
    {
        return view('admin.settings.analytics');
    }

    public function analyticsBootstrap(): View
    {
        return view('admin.settings.analytics');
    }

    public function updateAnalytics(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Hanya ID, bukan potongan script — supaya tidak jadi celah XSS.
            'ga_measurement_id' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]*$/'],
            'gtm_container_id'  => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]*$/'],
            'fb_pixel_id'       => ['nullable', 'string', 'max:50', 'regex:/^[0-9]*$/'],
        ], [
            'ga_measurement_id.regex' => 'Isi ID-nya saja (contoh: G-XXXXXXX), bukan seluruh kode script.',
            'gtm_container_id.regex'  => 'Isi ID-nya saja (contoh: GTM-XXXXXX), bukan seluruh kode script.',
            'fb_pixel_id.regex'       => 'Facebook Pixel ID hanya berisi angka.',
        ]);

        Setting::putMany($data, 'analytics');

        return back()->with('success', 'Pengaturan analytics berhasil disimpan.');
    }


    public function affiliate(): View
    {
        return view('admin.settings.affiliate');
    }

    public function affiliateBootstrap(): View
    {
        return view('admin.settings.affiliate');
    }

    /**
     * Ini hanya tarif DEFAULT (dipakai kalau affiliate/campaign tidak
     * punya override sendiri -- lihat prioritas di
     * AffiliateCommissionService::resolveRate()). affiliate_min_payout
     * dibaca AffiliatePayoutService::MIN_AMOUNT -- perhatikan itu masih
     * konstanta di kode, TIDAK otomatis ikut nilai dari sini; sengaja
     * disimpan di sini juga supaya admin bisa melihat & merencanakan
     * angkanya di satu tempat sebelum diminta ubah ke Setting::get()
     * kalau nanti dibutuhkan.
     */
    public function updateAffiliate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'affiliate_commission_type'  => ['required', 'in:percentage,fixed'],
            'affiliate_commission_value' => ['required', 'numeric', 'min:0'],
             'affiliate_commission_repeat' => ['nullable', 'boolean'],
            'affiliate_min_payout' => ['required', 'numeric', 'min:0'],
             'affiliate_cookie_days' => ['required', 'integer', 'min:1', 'max:730'],
             'affiliate_attribution_model' => ['required', 'in:first_click,last_click'],
             'affiliate_commission_mode' => ['required', 'in:first_order,first_payment,every_payment'],
             'affiliate_tax_enabled' => ['nullable', 'boolean'],
             'affiliate_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($data['affiliate_commission_type'] === 'percentage' && $data['affiliate_commission_value'] > 100) {
            return back()->withErrors(['affiliate_commission_value' => 'Persentase komisi tidak boleh lebih dari 100.'])->withInput();
        }

        $data['affiliate_commission_repeat'] = $request->boolean('affiliate_commission_repeat') ? '1' : '0';
        $data['affiliate_tax_enabled'] = $request->boolean('affiliate_tax_enabled') ? '1' : '0';

        Setting::putMany($data, 'affiliate');

        return back()->with('success', 'Pengaturan program affiliate berhasil disimpan.');
    }

    public function notifications(): View
    {
        return view('admin.settings.notifications');
    }

    public function notificationsBootstrap(): View
    {
        return view('admin.settings.notifications');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Notifikasi ke klien
            'notify_welcome'   => ['nullable', 'boolean'],
            'notify_invoice'   => ['nullable', 'boolean'],
            'notify_paid'      => ['nullable', 'boolean'],
            'notify_reminder'  => ['nullable', 'boolean'],
            'notify_ticket_reply' => ['nullable', 'boolean'],

            // Notifikasi ke admin
            'notify_admin_order'   => ['nullable', 'boolean'],
            'notify_admin_payment' => ['nullable', 'boolean'],
            'notify_admin_ticket'  => ['nullable', 'boolean'],
            'notify_admin_client'  => ['nullable', 'boolean'],

            // Jadwal pengingat
            'reminder_days_before' => ['nullable', 'string', 'regex:/^[0-9,\s]*$/'],
            'reminder_days_after'  => ['nullable', 'string', 'regex:/^[0-9,\s]*$/'],
            'renewal_invoice_days_before' => ['nullable', 'integer', 'min:1', 'max:60'],
            'auto_suspend_enabled' => ['nullable', 'boolean'],
            'suspend_grace_days'   => ['nullable', 'integer', 'min:0', 'max:30'],
            'notify_suspend'       => ['nullable', 'boolean'],

            // WhatsApp
            'wa_provider' => ['required', 'in:none,fonnte,wablas,custom'],
            'wa_token'    => ['nullable', 'string', 'max:500'],
            'wa_endpoint' => ['nullable', 'string', 'max:255'],
            'wa_admin_number' => ['nullable', 'string', 'max:30'],

            // SMS
            'sms_provider' => ['required', 'in:none,zenziva,twilio,custom'],
            'sms_userkey'  => ['nullable', 'string', 'max:255'],
            'sms_passkey'  => ['nullable', 'string', 'max:500'],
            'sms_sender'   => ['nullable', 'string', 'max:30'],
            'sms_endpoint' => ['nullable', 'string', 'max:255'],
            'sms_admin_number' => ['nullable', 'string', 'max:30'],
        ], [
            'reminder_days_before.regex' => 'Isi angka dipisah koma, contoh: 7,3,1',
            'reminder_days_after.regex'  => 'Isi angka dipisah koma, contoh: 1,7',
        ]);

        // Checkbox yang tidak dicentang tidak ikut terkirim, jadi diisi
        // eksplisit agar nilainya benar-benar tersimpan sebagai "mati".
        foreach ([
            'notify_welcome', 'notify_invoice', 'notify_paid', 'notify_reminder', 'notify_ticket_reply',
            'notify_admin_order', 'notify_admin_payment', 'notify_admin_ticket', 'notify_admin_client',
            'auto_suspend_enabled', 'notify_suspend',
        ] as $toggle) {
            $data[$toggle] = $request->boolean($toggle) ? '1' : '0';
        }

        // Token kosong saat sudah ada nilai = tidak diganti.
        if (blank($data['wa_token'] ?? null)) {
            unset($data['wa_token']);
        }

        if (blank($data['sms_passkey'] ?? null)) {
            unset($data['sms_passkey']);
        }

        // Status "Terhubung" hasil tes lama tidak lagi valid begitu
        // kredensial WhatsApp diubah — daripada tetap menampilkan tanda
        // sukses palsu untuk pengaturan yang belum pernah diuji ulang.
        if ($request->filled('wa_token') || $request->input('wa_provider') !== Setting::get('wa_provider')
            || $request->input('wa_endpoint') !== Setting::get('wa_endpoint')) {
            Setting::put('wa_last_test_status', null, 'general');
            Setting::put('wa_last_test_at', null, 'general');
        }

        // Sama seperti WhatsApp di atas, untuk kredensial SMS.
        if ($request->filled('sms_passkey') || $request->input('sms_provider') !== Setting::get('sms_provider')
            || $request->input('sms_userkey') !== Setting::get('sms_userkey')
            || $request->input('sms_endpoint') !== Setting::get('sms_endpoint')) {
            Setting::put('sms_last_test_status', null, 'general');
            Setting::put('sms_last_test_at', null, 'general');
        }

        Setting::putMany($data, 'notification');

        return back()->with('success', 'Pengaturan notifikasi berhasil disimpan.');
    }

    /**
     * Kirim WhatsApp percobaan untuk memastikan gateway sudah benar.
     */
    public function testWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_number' => ['required', 'string', 'max:30'],
        ]);

        $site = Setting::get('site_name', config('app.name'));

        $ok = app(\App\Notifications\Channels\WhatsAppChannel::class)->dispatch(
            $data['test_number'],
            "Tes notifikasi WhatsApp dari {$site}.\n\nKalau pesan ini sampai, berarti gateway sudah tersambung dengan benar."
        );

        // Disimpan supaya status "Terhubung" tetap tampil tiap kali halaman
        // ini dibuka lagi — bukan cuma pesan sekali lewat yang hilang
        // begitu halaman di-refresh.
        Setting::put('wa_last_test_status', $ok ? 'success' : 'failed', 'general');
        Setting::put('wa_last_test_at', now()->toDateTimeString(), 'general');

        return back()->with(
            $ok ? 'success' : 'error',
            $ok
                ? 'Pesan percobaan terkirim. Cek WhatsApp di nomor tersebut.'
                : 'Gagal mengirim. Periksa provider, token, dan endpoint — detailnya ada di storage/logs/laravel.log.'
        );
    }

    /**
     * Kirim SMS percobaan untuk memastikan gateway sudah benar.
     */
    public function testSms(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_number' => ['required', 'string', 'max:30'],
        ]);

        $site = Setting::get('site_name', config('app.name'));

        $ok = app(\App\Notifications\Channels\SmsChannel::class)->dispatch(
            $data['test_number'],
            "Tes SMS dari {$site}. Gateway sudah tersambung dengan benar."
        );

        Setting::put('sms_last_test_status', $ok ? 'success' : 'failed', 'general');
        Setting::put('sms_last_test_at', now()->toDateTimeString(), 'general');

        return back()->with(
            $ok ? 'success' : 'error',
            $ok
                ? 'SMS percobaan terkirim. Cek nomor tersebut.'
                : 'Gagal mengirim. Periksa provider dan kredensial — detailnya ada di storage/logs/laravel.log.'
        );
    }

    /**
     * Bikin sepasang kunci VAPID baru untuk Push Notification.
     *
     * SEKALI dibuat, kunci ini HARUS tetap sama selamanya -- kalau
     * diganti, semua langganan push yang sudah ada (browser klien/admin
     * yang sudah mengaktifkan notifikasi) langsung tidak valid dan harus
     * mengaktifkan ulang dari nol. Karena itu tombolnya di halaman
     * pengaturan sengaja pakai konfirmasi tegas, bukan langsung jalan.
     */
    public function generateVapidKeys(): RedirectResponse
    {
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

        Setting::put('vapid_public_key', $keys['publicKey'], 'notification');
        Setting::put('vapid_private_key', $keys['privateKey'], 'notification');

        return back()->with('success', 'Kunci VAPID berhasil dibuat. Push Notification sekarang aktif.');
    }

    /**
     * Kirim push percobaan ke langganan milik ADMIN YANG SEDANG LOGIN --
     * beda dari tes WhatsApp/SMS (yang bisa kirim ke nomor siapa saja),
     * push cuma bisa dikirim ke browser yang sudah subscribe, jadi
     * paling praktis diuji ke diri sendiri dulu.
     */
    public function testPush(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        $sent = app(\App\Notifications\Channels\PushChannel::class)->dispatch($admin, 'Tes Push Notification', 'Kalau ini muncul, Push Notification sudah aktif dengan benar.');

        return back()->with(
            $sent > 0 ? 'success' : 'error',
            $sent > 0
                ? "Push percobaan terkirim ke {$sent} perangkat."
                : 'Belum ada perangkat yang mengaktifkan Push Notification untuk akun Anda. Aktifkan dulu lewat tombol lonceng di pojok atas.'
        );
    }

    public function security(): View
    {
        return view('admin.settings.security');
    }

    public function securityBootstrap(): View
    {
        return view('admin.settings.security');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'captcha_mode' => ['required', 'in:off,adaptive,always'],
            'recaptcha_site_key' => ['nullable', 'string', 'max:255'],
            'recaptcha_secret_key' => ['nullable', 'string', 'max:255'],
            'require_email_verification' => ['nullable', 'boolean'],
        ]);

        $data['require_email_verification'] = $request->boolean('require_email_verification') ? '1' : '0';

        if (blank($data['recaptcha_secret_key'] ?? null)) {
            unset($data['recaptcha_secret_key']);
        }

        Setting::putMany($data, 'security');

        // Kunci reCAPTCHA yang baru diisi belum tentu benar — status
        // "Success" lama tidak boleh ikut terbawa untuk kunci yang belum
        // pernah diuji ulang.
        if ($request->filled('recaptcha_secret_key')) {
            Setting::put('recaptcha_last_test_status', null, 'security');
        }

        return back()->with('success', 'Pengaturan keamanan berhasil disimpan.');
    }

    /**
     * Uji Secret Key reCAPTCHA LANGSUNG ke API Google — bukan cuma
     * mengecek kolomnya terisi atau tidak. Dikirim tanpa token respons
     * asli (memang tidak mungkin ada, ini pengujian dari admin panel,
     * bukan dari form sungguhan) — tapi kode error yang dikembalikan
     * Google tetap membedakan dengan jelas: "invalid-input-secret"
     * berarti Secret Key-nya salah, sedangkan "missing-input-response"
     * berarti Secret Key-nya diterima Google (cuma tidak ada token
     * karena memang sengaja tidak dikirim).
     */
    public function testRecaptcha(): RedirectResponse
    {
        $secret = Setting::get('recaptcha_secret_key');

        if (blank($secret)) {
            return back()->with('error', 'Isi dulu Secret Key sebelum diuji.');
        }

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => '',
                ]);

            $errorCodes = $response->json('error-codes', []);
            $secretValid = ! in_array('invalid-input-secret', $errorCodes, true)
                && ! in_array('missing-input-secret', $errorCodes, true);

            Setting::put('recaptcha_last_test_status', $secretValid ? 'success' : 'failed', 'security');
            Setting::put('recaptcha_last_test_at', now()->toDateTimeString(), 'security');

            return back()->with(
                $secretValid ? 'success' : 'error',
                $secretValid
                    ? 'Secret Key valid — diterima Google reCAPTCHA.'
                    : 'Secret Key ditolak Google — periksa lagi, kemungkinan salah salin atau untuk versi reCAPTCHA yang berbeda (v2 vs v3).'
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Tidak bisa menghubungi Google reCAPTCHA: ' . $e->getMessage());
        }
    }

    public function livechat(): View
    {
        return view('admin.settings.livechat');
    }

    public function livechatBootstrap(): View
    {
        return view('admin.settings.livechat');
    }

    public function updateLivechat(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'livechat_provider'   => ['required', 'in:none,widget,tawkto,crisp,whatsapp'],
            'livechat_property_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\/_\-]*$/'],
            'livechat_whatsapp'   => ['nullable', 'string', 'max:30', 'regex:/^[0-9]*$/'],
            'livechat_greeting'   => ['nullable', 'string', 'max:255'],
            'support_hours'       => ['nullable', 'string', 'max:120'],
            'chat_greeting_1'     => ['nullable', 'string', 'max:300'],
            'chat_greeting_2'     => ['nullable', 'string', 'max:300'],
            // Bot AI -- balasan otomatis, provider bisa Claude atau
            // ChatGPT (lihat AiProviderFactory). Model disimpan per
            // provider (ai_chat_model_anthropic / _openai) supaya
            // pilihan tidak saling menimpa saat admin gonta-ganti
            // provider.
            'ai_chat_enabled'     => ['nullable', 'boolean'],
            'ai_chat_provider'    => ['nullable', 'in:anthropic,openai'],
            'ai_chat_api_key'     => ['nullable', 'string', 'max:255'],
            'ai_chat_openai_api_key' => ['nullable', 'string', 'max:255'],
            'ai_chat_model_anthropic' => ['nullable', 'string', 'max:100'],
            'ai_chat_model_openai'    => ['nullable', 'string', 'max:100'],
            'ai_chat_context'     => ['nullable', 'string', 'max:4000'],
        ], [
            'livechat_property_id.regex' => 'Isi ID widget saja, bukan seluruh kode script.',
            'livechat_whatsapp.regex'    => 'Nomor WhatsApp hanya berisi angka, diawali kode negara. Contoh: 6281234567890',
        ]);

        $data['ai_chat_enabled'] = $request->boolean('ai_chat_enabled') ? '1' : '0';

        // Kunci API tidak boleh ikut kosong menimpa yang sudah tersimpan
        // kalau admin membiarkan kolomnya kosong saat mengedit pengaturan
        // lain -- sama pola dengan token SMTP/registrar di tempat lain.
        // Berlaku untuk KEDUA provider secara independen.
        if (blank($data['ai_chat_api_key'] ?? null)) {
            unset($data['ai_chat_api_key']);
        }

        if (blank($data['ai_chat_openai_api_key'] ?? null)) {
            unset($data['ai_chat_openai_api_key']);
        }

        Setting::putMany($data, 'livechat');

        // Belum tentu konfigurasi baru itu benar — status "Success" lama
        // tidak boleh ikut terbawa untuk pengaturan yang belum diuji ulang.
        Setting::put('livechat_last_test_status', null, 'livechat');

        return back()->with('success', 'Pengaturan live chat berhasil disimpan.');
    }

    /**
     * Uji live chat sesuai penyedia yang aktif — Tawk.to benar-benar
     * dicek ke server mereka (widget ID yang salah akan 404), sisanya
     * diperiksa formatnya karena tidak ada cara sederhana memverifikasi
     * Crisp/WhatsApp lewat satu panggilan HTTP tanpa memuat JavaScript
     * sungguhan di browser.
     */
    public function testLiveChat(): RedirectResponse
    {
        $provider = Setting::get('livechat_provider', 'none');
        $propertyId = Setting::get('livechat_property_id');
        $whatsapp = Setting::get('livechat_whatsapp');

        [$ok, $message] = match ($provider) {
            'none' => [null, 'Live chat sedang nonaktif — tidak ada yang perlu diuji.'],

            'widget' => [
                filled($whatsapp) || filled(Setting::get('support_email')),
                filled($whatsapp) || filled(Setting::get('support_email'))
                    ? 'Widget Bawaan siap — minimal satu jalur kontak (WhatsApp/email) sudah terisi.'
                    : 'Isi dulu Nomor WhatsApp di sini atau Email Support di Pengaturan Umum.',
            ],

            'whatsapp' => [
                filled($whatsapp) && preg_match('/^[0-9]{9,15}$/', $whatsapp) === 1,
                filled($whatsapp) && preg_match('/^[0-9]{9,15}$/', $whatsapp) === 1
                    ? 'Format nomor WhatsApp valid.'
                    : 'Nomor WhatsApp kosong atau formatnya tidak valid (9–15 digit, diawali kode negara tanpa +).',
            ],

            'tawkto' => $this->testTawkTo($propertyId),

            'crisp' => [
                filled($propertyId) && preg_match('/^[a-f0-9\-]{20,40}$/i', $propertyId) === 1,
                filled($propertyId) && preg_match('/^[a-f0-9\-]{20,40}$/i', $propertyId) === 1
                    ? 'Format Website ID terlihat valid (bentuknya sesuai pola Crisp) — verifikasi penuh cuma bisa lewat tampilan widget sungguhan di halaman publik.'
                    : 'Website ID kosong atau formatnya tidak seperti ID Crisp pada umumnya.',
            ],

            default => [false, 'Penyedia tidak dikenali.'],
        };

        if ($ok !== null) {
            Setting::put('livechat_last_test_status', $ok ? 'success' : 'failed', 'livechat');
            Setting::put('livechat_last_test_at', now()->toDateTimeString(), 'livechat');
        }

        return back()->with($ok === false ? 'error' : 'success', $message);
    }

    private function testTawkTo(?string $propertyId): array
    {
        if (blank($propertyId) || ! str_contains($propertyId, '/')) {
            return [false, 'Property ID kosong atau formatnya salah — harusnya "propertyId/widgetId" (ada tanda garis miring).'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(8)->get("https://embed.tawk.to/{$propertyId}");

            return $response->successful()
                ? [true, 'Widget Tawk.to ditemukan dan aktif.']
                : [false, "Tawk.to mengembalikan status {$response->status()} — Property ID kemungkinan salah atau widget belum dipublikasikan."];
        } catch (\Throwable $e) {
            return [false, 'Tidak bisa menghubungi Tawk.to: ' . $e->getMessage()];
        }
    }
}