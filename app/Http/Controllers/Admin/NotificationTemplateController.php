<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function index(): View
    {
        $defaults = NotificationTemplate::defaults();
        $customized = NotificationTemplate::pluck('key')->all();

        $templates = collect($defaults)->map(function ($def, $key) use ($customized) {
            $def['key'] = $key;
            $def['is_customized'] = in_array($key, $customized, true);

            return $def;
        })->values();

        return view('admin.notification-templates.index', compact('templates'));
    }

    public function edit(string $key): View
    {
        $defaults = NotificationTemplate::defaults();

        abort_unless(isset($defaults[$key]), 404);

        $effective = NotificationTemplate::effective($key);
        $isCustomized = NotificationTemplate::where('key', $key)->exists();

        return view('admin.notification-templates.form', [
            'key' => $key,
            'meta' => $defaults[$key],
            'effective' => $effective,
            'isCustomized' => $isCustomized,
        ]);
    }

    /**
     * Contoh data untuk tiap variabel — supaya pratinjau menampilkan
     * angka/nama yang masuk akal, bukan cuma placeholder kosong.
     */
    private function sampleData(): array
    {
        return [
            'client_name' => 'Budi Santoso',
            'admin_name' => 'Admin',
            'site_name' => \App\Models\Setting::get('site_name', config('app.name')),
            'dashboard_url' => url('/client'),
            'services_url' => url('/client/services'),
            'balance_url' => url('/client/saldo'),
            'invoice_url' => url('/client/invoice/1'),
            'invoice_number' => 'INV-2026-0042',
            'total' => 'Rp 150.000',
            'due_date' => now()->addDays(7)->format('d M Y'),
            'days_left' => 3,
            'days_late' => 2,
            'service_name' => 'contohsaya.com',
            'amount' => 'Rp 100.000',
            'new_balance' => 'Rp 250.000',
            'code' => '482913',
            'judul' => 'Order Baru Masuk',
        ];
    }

    public function preview(string $key): View
    {
        $defaults = NotificationTemplate::defaults();

        abort_unless(isset($defaults[$key]), 404);

        $tpl = NotificationTemplate::effective($key);

        return $this->renderPreview($key, $defaults[$key], $tpl['subject'], $tpl['body_mail'], $tpl['body_whatsapp'], $tpl['body_sms']);
    }

    /**
     * Sama seperti preview(), tapi memakai teks yang sedang diketik admin
     * di form (belum disimpan) — supaya bisa lihat hasilnya sambil masih
     * menyusun, tanpa harus simpan-lihat-edit-simpan berulang.
     */
    public function previewDraft(Request $request, string $key): View
    {
        $defaults = NotificationTemplate::defaults();

        abort_unless(isset($defaults[$key]), 404);

        return $this->renderPreview(
            $key,
            $defaults[$key],
            $request->input('subject'),
            $request->input('body_mail'),
            $request->input('body_whatsapp'),
            $request->input('body_sms'),
        );
    }

    private function renderPreview(string $key, array $meta, ?string $subjectTpl, ?string $bodyMailTpl, ?string $bodyWhatsappTpl, ?string $bodySmsTpl = null): View
    {
        $data = $this->sampleData();

        $subject = NotificationTemplate::substitute($subjectTpl, $data);
        $bodyMail = NotificationTemplate::substitute($bodyMailTpl, $data);
        $bodyWhatsapp = NotificationTemplate::substitute($bodyWhatsappTpl, $data);
        $bodySms = NotificationTemplate::substitute($bodySmsTpl, $data);

        // Marker pembungkus diganti contoh isi dinamis, supaya pratinjau
        // tidak menampilkan tanda [RINCIAN] dkk. mentah-mentah.
        $sampleFillers = [
            '[RINCIAN]' => "**Klien:** Budi Santoso\n**Total:** Rp 150.000",
            '[DAFTAR_LAYANAN]' => "**Hosting — contohsaya.com**\nUsername cPanel: `contohus`\nPassword: `••••••••`",
            '[ISI_PROMO]' => "*Promo Spesial!*\n\nDapatkan diskon 20% untuk semua paket hosting bulan ini.",
        ];
        $bodyMail = strtr($bodyMail, $sampleFillers);
        $bodyWhatsapp = strtr($bodyWhatsapp, $sampleFillers);
        $bodySms = strtr($bodySms, $sampleFillers);

        // Baris [ACTION:Label:Url] diuraikan terpisah supaya bisa
        // ditampilkan sebagai tombol sungguhan di pratinjau, bukan teks.
        $lines = [];
        $action = null;

        foreach (explode("\n", $bodyMail) as $line) {
            $line = rtrim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^\[ACTION:(.+?):(.+)\]$/', $line, $m)) {
                $action = ['label' => trim($m[1]), 'url' => trim($m[2])];
                continue;
            }

            $lines[] = $line;
        }

        return view('admin.notification-templates.preview', [
            'key' => $key,
            'meta' => $meta,
            'subject' => $subject,
            'lines' => $lines,
            'action' => $action,
            'bodyWhatsapp' => $bodyWhatsapp,
            'bodySms' => $bodySms,
            'siteName' => \App\Models\Setting::get('site_name', config('app.name')),
            'siteLogo' => \App\Models\Setting::get('site_logo'),
            'promoBanner' => \App\Models\PromoBanner::live()->forPage('email')->orderBy('sort_order')->first(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $defaults = NotificationTemplate::defaults();

        abort_unless(isset($defaults[$key]), 404);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body_mail' => ['nullable', 'string', 'max:10000'],
            'body_whatsapp' => ['nullable', 'string', 'max:5000'],
            'body_sms' => ['nullable', 'string', 'max:1000'],
        ]);

        // Marker wajib ([RINCIAN], [DAFTAR_LAYANAN], [ISI_PROMO]) untuk
        // template pembungkus — tanpa ini, bagian dinamis (kredensial,
        // isi promo, dll) akan hilang tak tersisipkan sama sekali.
        $requiredMarker = match ($key) {
            'admin_alert' => '[RINCIAN]',
            'order_provisioned' => '[DAFTAR_LAYANAN]',
            'promo_broadcast' => '[ISI_PROMO]',
            default => null,
        };

        if ($requiredMarker && filled($data['body_mail'] ?? null) && ! str_contains($data['body_mail'], $requiredMarker)) {
            return back()->withInput()->with('error', "Isi email wajib menyertakan tanda {$requiredMarker} — itu tempat sistem menyisipkan bagian yang berbeda tiap kejadian.");
        }

        NotificationTemplate::updateOrCreate(['key' => $key], $data);

        return redirect()->route('admin.notification-templates.edit', $key)->with('success', 'Template berhasil disimpan.');
    }

    public function reset(string $key): RedirectResponse
    {
        NotificationTemplate::where('key', $key)->delete();

        return back()->with('success', 'Template dikembalikan ke kata-kata bawaan.');
    }
}
