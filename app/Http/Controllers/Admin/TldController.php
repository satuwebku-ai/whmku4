<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registrar;
use App\Models\Tld;
use App\Services\Domain\DomainRegistrarFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TldController extends Controller
{
    /**
     * Simpan harga add-on domain (ID Protection, dst). Bukan diambil dari
     * Liqu.id — lihat catatan di view TLD Pricing untuk alasannya.
     */
    public function updateAddonPricing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whois_privacy_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        \App\Models\Setting::put('whois_privacy_price', $data['whois_privacy_price'] ?? 0, 'domain');

        return back()->with('success', 'Harga add-on domain berhasil disimpan.');
    }

    /**
     * Halaman TLD Pricing (BARU, terpisah dari index()/Status TLD) --
     * sekarang mengharuskan pilih registrar dulu sebelum tabel harga
     * muncul. Ini jadi wajib sejak satu ekstensi (mis. ".com") bisa
     * dimiliki BEBERAPA registrar sekaligus (lihat migration
     * 2027_06_01_..._make_tld_extension_unique_per_registrar) -- tanpa
     * pemilihan registrar, tabel gabungan bakal menampilkan beberapa
     * baris ".com" berdampingan tanpa konteks jelas yang mana punya
     * siapa.
     */
    public function pricing(Request $request): View
    {
        $registrars = Registrar::withCount('tlds')->orderByDesc('is_default')->orderBy('name')->get();

        $registrarParam = $request->input('registrar');
        $selected = null;
        $tlds = null;

        if ($registrarParam === 'none') {
            $selected = 'none';
            $tlds = Tld::whereNull('registrar_id')
                ->when($request->search, fn ($q) => $q->where('extension', 'like', "%{$request->search}%"))
                ->orderBy('extension')
                ->paginate(min((int) $request->input('per_page', 25), 200))
                ->withQueryString();
        } elseif ($registrarParam) {
            $registrar = $registrars->firstWhere('id', (int) $registrarParam);

            if ($registrar) {
                $selected = $registrar;
                $tlds = Tld::where('registrar_id', $registrar->id)
                    ->when($request->search, fn ($q) => $q->where('extension', 'like', "%{$request->search}%"))
                    ->orderBy('extension')
                    ->paginate(min((int) $request->input('per_page', 25), 200))
                    ->withQueryString();
            }
        }

        return view('admin.tlds.pricing', compact('registrars', 'selected', 'tlds'));
    }

    public function pricingBootstrap(Request $request): View
    {
        return $this->pricing($request);
    }

    /**
     * Simpan harga (Modal/Register/Renew/Transfer + harga per tahun)
     * untuk TLD milik SATU registrar tertentu. Dipisah dari bulkUpdate()
     * (yang sekarang cuma menangani Aktif/Tampil di Web/Grup di halaman
     * Status TLD) supaya submit di satu halaman tidak bisa tidak sengaja
     * mengubah data di halaman lain.
     *
     * registrar_id yang dikirim form dicocokkan ulang ke tiap baris --
     * kalau ada yang tidak cocok (mis. form dimanipulasi manual), baris
     * itu dilewati, bukan dipaksa disimpan.
     */
    public function updatePricing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registrar_id'                  => ['nullable'],
            'rows'                           => ['required', 'array'],
            'rows.*.cost_register'           => ['nullable', 'numeric', 'min:0'],
            'rows.*.cost_renew'              => ['nullable', 'numeric', 'min:0'],
            'rows.*.cost_transfer'           => ['nullable', 'numeric', 'min:0'],
            'rows.*.register_price'          => ['nullable', 'numeric', 'min:0'],
            'rows.*.renew_price'             => ['nullable', 'numeric', 'min:0'],
            'rows.*.transfer_price'          => ['nullable', 'numeric', 'min:0'],
            'rows.*.year_prices'             => ['nullable', 'array'],
            'rows.*.year_renew_prices'       => ['nullable', 'array'],
        ]);

        $registrarId = $data['registrar_id'] === 'none' ? null : ($data['registrar_id'] ?: null);

        $tlds = Tld::whereIn('id', array_keys($data['rows']))
            ->where('registrar_id', $registrarId)
            ->get()
            ->keyBy('id');

        $changed = 0;
        $skipped = 0;

        foreach ($data['rows'] as $id => $row) {
            $tld = $tlds->get((int) $id);

            if (! $tld) {
                // Baris ini bukan milik registrar yang sedang dibuka --
                // dilewati diam-diam supaya submit yang dimanipulasi
                // tidak bisa mengubah TLD registrar lain.
                $skipped++;
                continue;
            }

            $values = [
                'cost_register'  => $this->toNumber($row['cost_register'] ?? null, $tld->cost_register),
                'cost_renew'     => $this->toNumber($row['cost_renew'] ?? null, $tld->cost_renew),
                'cost_transfer'  => $this->toNumber($row['cost_transfer'] ?? null, $tld->cost_transfer),
                'register_price' => $this->toNumber($row['register_price'] ?? null, $tld->register_price),
                'renew_price'    => $this->toNumber($row['renew_price'] ?? null, $tld->renew_price),
                'transfer_price' => $this->toNumber($row['transfer_price'] ?? null, $tld->transfer_price),
            ];

            // Harga per tahun (2-10 tahun) -- cuma ditimpa kalau memang
            // dikirim form (lewat modal "Atur Harga per Tahun"), supaya
            // submit biasa tanpa modal itu tidak menghapus data yang
            // sudah ada.
            if (isset($row['year_prices'])) {
                $values['year_prices'] = array_filter($row['year_prices'], fn ($v) => $v !== null && $v !== '');
            }

            if (isset($row['year_renew_prices'])) {
                $values['year_renew_prices'] = array_filter($row['year_renew_prices'], fn ($v) => $v !== null && $v !== '');
            }

            if ($values['cost_register'] > 0 && (float) $tld->cost_register !== $values['cost_register']) {
                $values['cost_synced_at'] = now();
            }

            $tld->fill($values);

            if ($tld->isDirty()) {
                $tld->save();
                $changed++;
            }
        }

        $msg = "{$changed} TLD berhasil diperbarui.";

        if ($skipped > 0) {
            $msg .= " ({$skipped} baris dilewati karena tidak cocok dengan registrar yang sedang dibuka.)";
        }

        return back()->with($changed > 0 ? 'success' : 'info', $msg);
    }

    public function index(Request $request): View
    {
        $tlds = Tld::with('registrar')
            ->when($request->search, fn ($q) => $q->where('extension', 'like', "%{$request->search}%"))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->web === 'shown', fn ($q) => $q->where('show_in_search', true))
            ->when($request->web === 'hidden', fn ($q) => $q->where('show_in_search', false))
            ->when($request->registrar === 'none', fn ($q) => $q->whereNull('registrar_id'))
            ->when($request->registrar && $request->registrar !== 'none', fn ($q) => $q->where('registrar_id', $request->registrar))
            ->orderByDesc('is_active')
            ->orderBy('extension')
            // Bisa diperbesar supaya lebih banyak baris diedit sekaligus.
            ->paginate(min((int) $request->input('per_page', 25), 200))
            ->withQueryString();

        $counts = [
            'all'      => Tld::count(),
            'active'   => Tld::where('is_active', true)->count(),
            'inactive' => Tld::where('is_active', false)->count(),
            // Dipakai untuk memperingatkan bahwa markup tidak akan berpengaruh
            // pada TLD yang harga modalnya belum terisi.
            'no_cost'  => Tld::where('cost_register', '<=', 0)->count(),
            'shown'    => Tld::where('show_in_search', true)->count(),
            'hidden'   => Tld::where('show_in_search', false)->count(),
            'privacy_eligible' => Tld::where('whois_privacy_eligible', true)->count(),
        ];

        $registrars = Registrar::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        $priceCompare = $this->priceComparison($tlds);

        return view('admin.tlds.index', compact('tlds', 'counts', 'registrars', 'priceCompare'));
    }

    public function indexBootstrap(Request $request): View
    {
        $tlds = Tld::with('registrar')
            ->when($request->search, fn ($q) => $q->where('extension', 'like', "%{$request->search}%"))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->web === 'shown', fn ($q) => $q->where('show_in_search', true))
            ->when($request->web === 'hidden', fn ($q) => $q->where('show_in_search', false))
            ->when($request->registrar === 'none', fn ($q) => $q->whereNull('registrar_id'))
            ->when($request->registrar && $request->registrar !== 'none', fn ($q) => $q->where('registrar_id', $request->registrar))
            ->orderByDesc('is_active')
            ->orderBy('extension')
            ->paginate(min((int) $request->input('per_page', 25), 200))
            ->withQueryString();

        $counts = [
            'all'      => Tld::count(),
            'active'   => Tld::where('is_active', true)->count(),
            'inactive' => Tld::where('is_active', false)->count(),
            'no_cost'  => Tld::where('cost_register', '<=', 0)->count(),
            'shown'    => Tld::where('show_in_search', true)->count(),
            'hidden'   => Tld::where('show_in_search', false)->count(),
            'privacy_eligible' => Tld::where('whois_privacy_eligible', true)->count(),
        ];

        $registrars = Registrar::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();

        $priceCompare = $this->priceComparison($tlds);

        return view('admin.tlds.index', compact('tlds', 'counts', 'registrars', 'priceCompare'));
    }


    /**
     * Perbandingan harga antar registrar untuk ekstensi yang sama.
     * Cuma dihitung untuk ekstensi yang benar-benar tampil di halaman
     * (bukan seluruh tabel), supaya query-nya tetap ringan.
     *
     * Ekstensi yang cuma dijual SATU registrar sengaja tidak masuk hasil
     * -- barisnya dibiarkan polos, bukan diberi warna "termurah" yang
     * menyesatkan (tidak ada yang dibandingkan).
     */
    private function priceComparison($tlds): array
    {
        $visible = $tlds->pluck('extension')->unique()->all();

        if (empty($visible)) {
            return [];
        }

        $rows = Tld::whereIn('extension', $visible)
            ->where('register_price', '>', 0)
            ->get(['id', 'extension', 'register_price']);

        $compare = [];

        foreach ($rows->groupBy('extension') as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $min = (float) $group->min('register_price');
            $max = (float) $group->max('register_price');

            foreach ($group as $row) {
                $price = (float) $row->register_price;

                $compare[$row->id] = [
                    'rank'  => $price <= $min ? 'cheapest' : ($price >= $max ? 'priciest' : 'middle'),
                    'count' => $group->count(),
                    'min'   => $min,
                ];
            }
        }

        return $compare;
    }

    /**
     * Aktif/nonaktifkan satu TLD tanpa membuka form edit.
     */
    /**
     * Aktifkan/nonaktifkan satu TLD.
     *
     * EKSKLUSIF PER EKSTENSI: kalau ".com" milik DNAMA diaktifkan,
     * ".com" milik registrar lain otomatis dinonaktifkan. Alasannya
     * bukan sekadar kerapian tampilan -- kalau dua registrar sama-sama
     * aktif untuk ekstensi yang sama, sistem tidak punya cara
     * menentukan lewat registrar MANA order domain itu harus diproses,
     * dan harga yang tampil ke klien pun jadi ambigu.
     */
    public function status(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $tld = Tld::with('registrar')->findOrFail($request->input('tld_id'));

        // Mengaktifkan TLD tanpa harga jual hampir pasti tidak disengaja.
        if (! $tld->is_active && (float) $tld->register_price <= 0) {
            $error = "TLD {$tld->extension} belum punya harga jual. Isi harganya dulu di TLD Pricing sebelum diaktifkan.";

            return $request->ajax()
                ? response()->json(['success' => false, 'message' => $error], 422)
                : back()->with('error', $error);
        }

        $turningOn = ! $tld->is_active;
        $deactivated = [];

        if ($turningOn) {
            // Nonaktifkan saudara se-ekstensi dari registrar lain.
            $siblings = Tld::where('extension', $tld->extension)
                ->where('id', '!=', $tld->id)
                ->where('is_active', true)
                ->with('registrar')
                ->get();

            foreach ($siblings as $sibling) {
                $sibling->update(['is_active' => false]);
                $deactivated[] = $sibling->registrar->name ?? 'Manual';
            }
        }

        $tld->update(['is_active' => $turningOn]);

        $message = "TLD {$tld->extension} berhasil " . ($turningOn ? 'diaktifkan' : 'dinonaktifkan') . '.';

        if ($deactivated) {
            $message .= ' Otomatis dinonaktifkan dari: ' . implode(', ', $deactivated) . '.';
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'is_active' => $turningOn,
                'extension' => $tld->extension,
                'tld_id' => $tld->id,
                // Dipakai halaman untuk mematikan switch saudara
                // se-ekstensi tanpa perlu reload.
                'deactivated_ids' => $turningOn
                    ? Tld::where('extension', $tld->extension)->where('id', '!=', $tld->id)->pluck('id')->all()
                    : [],
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Terapkan margin ke banyak TLD sekaligus — meniru panel reseller
     * (Set prices using Profit Margin).
     *
     * Margin bisa diatur terpisah untuk Register / Renew / Transfer,
     * dinyatakan dalam persen atau rupiah tetap, lalu dibulatkan sesuai
     * selera. Perhitungan SELALU dari harga modal, bukan dari harga jual
     * sebelumnya, jadi aman dijalankan berulang kali.
     */
    public function bulkMarkup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profit_type'      => ['required', 'in:percent,fixed'],
            'margin_register'  => ['required', 'numeric', 'min:0'],
            'margin_renew'     => ['nullable', 'numeric', 'min:0'],
            'margin_transfer'  => ['nullable', 'numeric', 'min:0'],

            'round_mode'       => ['required', 'in:none,multiple,ending'],
            'round_step'       => ['nullable', 'integer', 'min:1'],
            'round_tail'       => ['nullable', 'integer', 'min:0'],

            'scope'            => ['required', 'in:all,selected,filtered'],
            'selected_ids'     => ['nullable', 'string'],
            'search'           => ['nullable', 'string'],
            // Dikirim halaman TLD Pricing supaya markup cuma mengenai TLD
            // milik registrar yang sedang dibuka. Kalau tidak dikirim
            // (mis. dipanggil dari tempat lain), perilakunya seperti dulu:
            // berlaku untuk semua registrar.
            'registrar_scope'  => ['nullable', 'string'],

            'only_empty'       => ['nullable', 'boolean'],
        ], [
            'margin_register.required' => 'Margin untuk Register wajib diisi.',
        ]);

        $type = $data['profit_type'];

        // Renew & Transfer mengikuti Register kalau dikosongkan.
        $margins = [
            'register' => (float) $data['margin_register'],
            'renew'    => $data['margin_renew'] !== null && $data['margin_renew'] !== ''
                ? (float) $data['margin_renew'] : (float) $data['margin_register'],
            'transfer' => $data['margin_transfer'] !== null && $data['margin_transfer'] !== ''
                ? (float) $data['margin_transfer'] : (float) $data['margin_register'],
        ];

        $query = Tld::query();

        // Dibatasi DULUAN sebelum scope lain -- ini pagar paling penting,
        // supaya markup yang dijalankan dari halaman satu registrar tidak
        // pernah menyentuh harga registrar lain.
        $registrarScope = $data['registrar_scope'] ?? null;

        if ($registrarScope === 'none') {
            $query->whereNull('registrar_id');
        } elseif ($registrarScope !== null && $registrarScope !== '') {
            $query->where('registrar_id', (int) $registrarScope);
        }

        if ($data['scope'] === 'selected') {
            $ids = array_filter(array_map('intval', explode(',', (string) ($data['selected_ids'] ?? ''))));

            if (empty($ids)) {
                return back()->with('error', 'Belum ada TLD yang dicentang di tabel.');
            }

            $query->whereIn('id', $ids);
        } elseif ($data['scope'] === 'filtered' && ! empty($data['search'])) {
            $query->where('extension', 'like', '%' . $data['search'] . '%');
        }

        if ($request->boolean('only_empty')) {
            $query->where(fn ($q) => $q->whereNull('register_price')->orWhere('register_price', '<=', 0));
        }

        $updated = 0;
        $skipped = 0;

        foreach ($query->get() as $tld) {
            if ((float) $tld->cost_register <= 0) {
                $skipped++;
                continue;
            }

            $costs = [
                'register' => (float) $tld->cost_register,
                'renew'    => (float) $tld->cost_renew ?: (float) $tld->cost_register,
                'transfer' => (float) $tld->cost_transfer ?: (float) $tld->cost_register,
            ];

            $prices = [];

            foreach ($costs as $field => $cost) {
                $price = $type === 'percent'
                    ? $cost * (1 + $margins[$field] / 100)
                    : $cost + $margins[$field];

                $prices[$field] = $this->roundPrice(
                    $price,
                    $data['round_mode'],
                    (int) ($data['round_step'] ?? 1000),
                    (int) ($data['round_tail'] ?? 0)
                );
            }

            // Harga JUAL per tahun (2-10 thn) ikut dihitung dari harga
            // MODAL per tahun yang disinkron dari registrar
            // (cost_year_prices) -- pakai margin & pembulatan yang sama.
            //
            // Sebelumnya markup cuma menyentuh harga 1 tahun, jadi kolom
            // "Harga per Tahun" tetap kosong/"otomatis" walau markup
            // sudah dijalankan. Padahal harga otomatis (harga 1thn x N)
            // sering meleset jauh dari harga modal sebenarnya: registrar
            // biasanya TIDAK mengenakan kelipatan lurus untuk durasi
            // panjang, jadi tanpa ini margin durasi panjang bisa jauh
            // lebih tipis (atau malah rugi) tanpa ketahuan.
            $yearPrices = $this->markupYearPrices(
                $tld->cost_year_prices,
                $type,
                $margins['register'],
                $data
            );

            $yearRenewPrices = $this->markupYearPrices(
                $tld->cost_year_renew_prices,
                $type,
                $margins['renew'],
                $data
            );

            $update = [
                'register_price' => $prices['register'],
                'renew_price'    => $prices['renew'],
                'transfer_price' => $prices['transfer'],
                // is_active SENGAJA tidak disentuh di sini. Aktivasi
                // sekarang eksklusif per ekstensi (lihat status()), jadi
                // mengaktifkan massal lewat markup bisa diam-diam
                // menggeser registrar yang sudah sengaja dipilih admin
                // untuk ekstensi yang sama.
            ];

            // Cuma ditimpa kalau memang ada modal per-tahun untuk dihitung
            // -- kalau registrar tidak menyediakannya, harga per tahun
            // yang sudah diisi manual admin dibiarkan apa adanya.
            if ($yearPrices) {
                $update['year_prices'] = $yearPrices;
            }

            if ($yearRenewPrices) {
                $update['year_renew_prices'] = $yearRenewPrices;
            }

            $tld->update($update);

            $updated++;
        }

        if ($updated === 0 && $skipped > 0) {
            return back()->with('error',
                "Tidak ada harga yang berubah. Semua {$skipped} TLD belum punya harga modal, " .
                'jadi margin tidak bisa dihitung. Isi kolom Modal dulu, atau pakai Impor Harga.'
            );
        }

        if ($updated === 0) {
            return back()->with('error', 'Tidak ada TLD yang cocok dengan kriteria yang dipilih.');
        }

        $label = $type === 'percent'
            ? "Margin {$margins['register']}%"
            : 'Margin Rp ' . number_format($margins['register'], 0, ',', '.');

        $msg = "{$label} diterapkan ke {$updated} TLD.";
        $msg .= $skipped > 0 ? " {$skipped} TLD dilewati karena belum punya harga modal." : '';

        return back()->with('success', $msg);
    }

    /**
     * Bulatkan harga sesuai mode yang dipilih.
     *
     * - none     : biarkan apa adanya
     * - multiple : bulatkan ke atas ke kelipatan tertentu (mis. 1.000)
     * - ending   : paksa digit akhir tertentu (mis. selalu berakhir 9.000)
     */
    /**
     * Halaman terpisah khusus ID Protection -- dulu numpuk di TLD
     * Pricing, dipisah supaya tabel TLD Pricing tidak makin padat.
     * Tiga tingkat harga (dari yang paling spesifik):
     *   1. Per-TLD (kalau diisi admin)
     *   2. Per-registrar (kalau diisi admin)
     *   3. Global/default (fallback terakhir)
     */
    public function privacy(Request $request): View
    {
        $registrars = Registrar::orderBy('name')->get();

        $tlds = Tld::with('registrar')
            ->when($request->search, fn ($q) => $q->where('extension', 'like', "%{$request->search}%"))
            ->when($request->registrar === 'none', fn ($q) => $q->whereNull('registrar_id'))
            ->when($request->registrar && $request->registrar !== 'none', fn ($q) => $q->where('registrar_id', $request->registrar))
            ->orderBy('extension')
            ->paginate(min((int) $request->input('per_page', 50), 200))
            ->withQueryString();

        return view('admin.tlds.privacy', compact('registrars', 'tlds'));
    }

    public function privacyBootstrap(Request $request): View
    {
        return $this->privacy($request);
    }

    /**
     * Simpan harga per-registrar -- satu form untuk semua baris
     * sekaligus, sama pola dengan tabel TLD Pricing.
     */
    public function updatePrivacyRegistrars(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registrars'                       => ['required', 'array'],
            'registrars.*.whois_privacy_price'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $changed = 0;

        foreach ($data['registrars'] as $id => $row) {
            $registrar = Registrar::find($id);

            if (! $registrar) {
                continue;
            }

            $price = $row['whois_privacy_price'] ?? null;
            $registrar->whois_privacy_price = ($price === null || $price === '') ? null : round((float) $price, 2);

            if ($registrar->isDirty()) {
                $registrar->save();
                $changed++;
            }
        }

        return back()->with('success', "{$changed} registrar berhasil diperbarui.");
    }

    /**
     * Simpan eligibilitas + harga per-TLD -- terpisah dari bulkUpdate()
     * TLD Pricing supaya menyimpan harga register/renew/transfer TIDAK
     * ikut menimpa status ID Protection TLD yang tidak sedang ditampilkan
     * di halaman itu, dan sebaliknya.
     */
    public function updatePrivacyTlds(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows'                        => ['required', 'array'],
            'rows.*.whois_privacy_price'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $eligibleIds = array_map('intval', (array) $request->input('eligible', []));
        $changed = 0;

        $tlds = Tld::whereIn('id', array_keys($data['rows']))->get()->keyBy('id');

        foreach ($data['rows'] as $id => $row) {
            $tld = $tlds->get((int) $id);

            if (! $tld) {
                continue;
            }

            $tld->whois_privacy_eligible = in_array((int) $id, $eligibleIds, true);

            $price = $row['whois_privacy_price'] ?? null;
            $tld->whois_privacy_price = ($price === null || $price === '') ? null : round((float) $price, 2);

            if ($tld->isDirty()) {
                $tld->save();
                $changed++;
            }
        }

        return back()->with('success', "{$changed} TLD berhasil diperbarui.");
    }

    /**
     * Hitung harga JUAL per tahun (2-10 thn) dari harga MODAL per tahun
     * yang disinkron dari registrar, memakai margin & pembulatan yang
     * sama dengan harga 1 tahun.
     *
     * Dipisah jadi method sendiri (bukan inline di bulkMarkup) supaya
     * alurnya lugas -- versi sebelumnya memakai referensi di dalam array
     * literal (&$target), yang sulit dipastikan benar sekilas baca.
     *
     * @return array<string, float>  kosong kalau registrar tidak
     *                               menyediakan harga modal per tahun
     */
    private function markupYearPrices($costYears, string $type, float $margin, array $data): array
    {
        if (! is_array($costYears) || empty($costYears)) {
            return [];
        }

        $result = [];

        foreach ($costYears as $year => $costYear) {
            $costYear = (float) $costYear;

            if ($costYear <= 0) {
                continue;
            }

            $price = $type === 'percent'
                ? $costYear * (1 + $margin / 100)
                : $costYear + $margin;

            $result[(string) $year] = $this->roundPrice(
                $price,
                $data['round_mode'] ?? 'multiple',
                (int) ($data['round_step'] ?? 1000),
                (int) ($data['round_tail'] ?? 0)
            );
        }

        return $result;
    }

    private function roundPrice(float $price, string $mode, int $step, int $tail): float
    {
        if ($mode === 'multiple' && $step > 0) {
            return ceil($price / $step) * $step;
        }

        if ($mode === 'ending' && $step > 0) {
            $base = floor($price / $step) * $step + $tail;

            // Kalau hasilnya jadi lebih murah dari harga aslinya, naikkan
            // satu kelipatan supaya margin tidak berkurang.
            if ($base < $price) {
                $base += $step;
            }

            return $base;
        }

        return round($price, 2);
    }

    /**
     * Tarik harga modal langsung dari registrar, lalu tampilkan sebagai
     * tabel pratinjau. Tidak ada copy-paste dan belum ada yang disimpan.
     */
    public function syncPreview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'registrar_id' => ['nullable', 'exists:registrars,id'],
            'markup'       => ['required', 'numeric', 'min:0', 'max:1000'],
            'round_to'     => ['nullable', 'integer', 'min:0'],
            'only_sellable' => ['nullable', 'boolean'],
        ]);

        $registrar = ! empty($data['registrar_id'])
            ? Registrar::find($data['registrar_id'])
            : Registrar::where('is_active', true)->orderByDesc('is_default')->first();

        if (! $registrar) {
            return back()->with('error', 'Belum ada registrar aktif. Tambahkan dulu di tab Registrar.');
        }

        $service = DomainRegistrarFactory::make($registrar);

        if (! method_exists($service, 'listPrices')) {
            return back()->with('error', "Provider {$registrar->provider} belum mendukung pengambilan harga otomatis.");
        }

        $result = $service->listPrices();

        if (! $result['success']) {
            return back()->with('error', 'Gagal mengambil harga: ' . $result['message']);
        }

        $markup  = (float) $data['markup'];
        $roundTo = (int) ($data['round_to'] ?? 1000);
        $onlySellable = $request->boolean('only_sellable');

        $existing = Tld::whereIn('extension', array_keys($result['prices']))->get()->keyBy('extension');

        $rows = [];

        foreach ($result['prices'] as $ext => $price) {
            // Lewati TLD yang belum diaktifkan untuk dijual di panel registrar.
            if ($onlySellable && isset($price['sellable']) && ! $price['sellable']) {
                continue;
            }

            $cost = (float) $price['register'];

            if ($cost <= 0) {
                continue;
            }

            $selling = $cost * (1 + $markup / 100);

            if ($roundTo > 0) {
                $selling = ceil($selling / $roundTo) * $roundTo;
            }

            $tld = $existing->get($ext);

            $rows[] = [
                'extension' => $ext,
                'cost'      => $cost,
                'selling'   => $selling,
                'exists'    => (bool) $tld,
                'tld_id'    => $tld?->id,
                // TLD yang sudah aktif tetap dicentang; yang baru mengikuti
                // status "Sell" di panel registrar.
                'active'    => (bool) ($tld?->is_active ?: ($price['sellable'] ?? false)),
                'old_cost'  => (float) ($tld?->cost_register ?? 0),
                'old_price' => (float) ($tld?->register_price ?? 0),
            ];
        }

        if (empty($rows)) {
            return back()->with('error', 'Tidak ada harga yang bisa ditampilkan dari registrar ini.');
        }

        usort($rows, fn ($a, $b) => strcmp($a['extension'], $b['extension']));

        return view('admin.tlds.import-preview', [
            'rows'      => $rows,
            'markup'    => $markup,
            'roundTo'   => $roundTo,
            'source'    => $registrar->name,
            'registrarId' => $registrar->id,
        ]);
    }

    /**
     * Langkah 1 impor: baca teks yang ditempel, lalu tampilkan sebagai
     * tabel pratinjau. BELUM ada yang disimpan di sini — supaya kamu bisa
     * memeriksa dan menyesuaikan tiap baris sebelum benar-benar diterapkan.
     */
    public function importPreview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'price_text'   => ['required', 'string'],
            'multiplier'   => ['required', 'numeric', 'min:1'],
            // Panel "Manage Prices" Liqu.id menampilkan dua angka per baris:
            // angka ke-1 = harga jual ke customer, angka ke-2 = harga modal
            // reseller. Yang dipakai untuk markup adalah yang ke-2.
            'price_column' => ['required', 'integer', 'min:1', 'max:3'],
            'markup'       => ['required', 'numeric', 'min:0', 'max:1000'],
            'round_to'     => ['nullable', 'integer', 'min:0'],
        ]);

        $parsed = $this->parsePriceText(
            $data['price_text'],
            (int) $data['price_column'],
            (float) $data['multiplier']
        );

        if (empty($parsed)) {
            return back()->with('error',
                'Tidak ada harga yang terbaca. Pastikan tiap baris berisi ekstensi lalu angkanya, contoh: ".COM Domain Names  170.33 163.44  4.22 %"'
            );
        }

        $markup  = (float) $data['markup'];
        $roundTo = (int) ($data['round_to'] ?? 1000);

        $existing = Tld::whereIn('extension', array_keys($parsed))->get()->keyBy('extension');

        $rows = [];

        foreach ($parsed as $ext => $cost) {
            $tld = $existing->get($ext);

            $selling = $cost * (1 + $markup / 100);

            if ($roundTo > 0) {
                $selling = ceil($selling / $roundTo) * $roundTo;
            }

            $rows[] = [
                'extension' => $ext,
                'cost'      => $cost,
                'selling'   => $selling,
                'exists'    => (bool) $tld,
                'tld_id'    => $tld?->id,
                // TLD yang sudah aktif tetap dicentang; yang baru dibiarkan
                // mati supaya tidak langsung tampil sebelum dicek.
                'active'    => (bool) ($tld?->is_active),
                'old_cost'  => (float) ($tld?->cost_register ?? 0),
                'old_price' => (float) ($tld?->register_price ?? 0),
            ];
        }

        return view('admin.tlds.import-preview', [
            'rows'    => $rows,
            'markup'  => $markup,
            'roundTo' => $roundTo,
            'source'  => null,
            // Impor dari teks tempel tidak terikat registrar mana pun --
            // hasilnya masuk sebagai TLD "Manual (Tidak Ditentukan)".
            'registrarId' => null,
        ]);
    }

    /**
     * Langkah 2 impor: simpan baris-baris yang dicentang dari pratinjau.
     */
    public function importApply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows'                  => ['required', 'array'],
            'rows.*.extension'      => ['required', 'string', 'max:30'],
            'rows.*.cost'           => ['nullable', 'numeric', 'min:0'],
            'rows.*.selling'        => ['nullable', 'numeric', 'min:0'],
            'include'               => ['nullable', 'array'],
            'registrar_id'          => ['nullable', 'integer', 'exists:registrars,id'],
        ]);

        $registrarId = $data['registrar_id'] ?? null;

        $include = array_map('strval', (array) $request->input('include', []));
        $activate = array_map('strval', (array) $request->input('active', []));

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($data['rows'] as $key => $row) {
            // Baris yang tidak dicentang sengaja dilewati.
            if (! in_array((string) $key, $include, true)) {
                $skipped++;
                continue;
            }

            $ext = '.' . ltrim(strtolower(trim($row['extension'])), '.');
            $cost = round((float) ($row['cost'] ?? 0), 2);
            $selling = round((float) ($row['selling'] ?? 0), 2);
            $isActive = in_array((string) $key, $activate, true) && $selling > 0;

            // Aktivasi EKSKLUSIF per ekstensi -- sama seperti aturan di
            // status(). Tanpa ini, impor bisa mengaktifkan ".com" milik
            // registrar ini sementara ".com" milik registrar lain juga
            // masih aktif, dan sistem tidak punya cara menentukan lewat
            // registrar mana order harus diproses.
            if ($isActive) {
                Tld::where('extension', $ext)
                    ->when($registrarId, fn ($q) => $q->where('registrar_id', '!=', $registrarId))
                    ->when(! $registrarId, fn ($q) => $q->whereNotNull('registrar_id'))
                    ->update(['is_active' => false]);
            }

            // Dicocokkan per (extension, registrar) -- sejak satu ekstensi
            // boleh dimiliki beberapa registrar, mencari lewat extension
            // saja bisa salah menimpa baris milik registrar LAIN.
            $tld = Tld::where('extension', $ext)
                ->where('registrar_id', $registrarId)
                ->first();

            // Baris manual (belum punya registrar) di-claim kalau ada,
            // supaya tidak dobel percuma.
            if (! $tld && $registrarId) {
                $tld = Tld::where('extension', $ext)->whereNull('registrar_id')->first();
            }

            $values = [
                'cost_register'  => $cost,
                'cost_renew'     => $cost,
                'cost_transfer'  => $cost,
                'cost_currency'  => 'IDR',
                'cost_synced_at' => $cost > 0 ? now() : null,
                'register_price' => $selling,
                'renew_price'    => $selling,
                'transfer_price' => $selling,
                'is_active'      => $isActive,
            ];

            if ($tld) {
                $values['registrar_id'] = $registrarId;
                $tld->update($values);
                $updated++;
            } else {
                Tld::create(array_merge([
                    'extension' => $ext,
                    'registrar_id' => $registrarId,
                    'min_years' => 1,
                    'max_years' => 10,
                ], $values));
                $created++;
            }
        }

        $backTo = route('admin.tlds.pricing', $registrarId ? ['registrar' => $registrarId] : []);

        if ($created === 0 && $updated === 0) {
            return redirect()->to($backTo)
                ->with('error', 'Tidak ada baris yang dicentang, jadi tidak ada yang disimpan.');
        }

        $msg = "Impor selesai — {$updated} TLD diperbarui";
        $msg .= $created > 0 ? ", {$created} TLD baru dibuat." : '.';
        $msg .= $skipped > 0 ? " {$skipped} baris dilewati." : '';

        return redirect()->to($backTo)->with('success', $msg);
    }

    /**
     * Baca teks daftar harga jadi map [ekstensi => harga modal].
     *
     * Menerima pemisah tab, koma, titik koma, atau spasi. Teks seperti
     * "Domain Names", "IDR", dan "%" diabaikan karena bukan angka.
     */
    private function parsePriceText(string $text, int $column, float $multiplier): array
    {
        $result = [];

        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\t|,|;|\s{2,}|\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);

            if (count($parts) < 2) {
                continue;
            }

            $ext = '.' . ltrim(strtolower(trim($parts[0])), '.');

            $numbers = [];

            foreach (array_slice($parts, 1) as $part) {
                $clean = str_replace(',', '', trim($part));

                if (is_numeric($clean) && (float) $clean > 0) {
                    $numbers[] = (float) $clean;
                }
            }

            $price = $numbers[$column - 1] ?? ($numbers[0] ?? null);

            if ($price === null || $price <= 0) {
                continue;
            }

            $result[$ext] = round($price * $multiplier, 2);
        }

        return $result;
    }

    /**
     * Simpan perubahan harga yang diketik langsung di tabel.
     *
     * Jauh lebih praktis daripada membuka form edit satu per satu, dan
     * hanya baris yang benar-benar berubah yang disentuh database.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows'                    => ['required', 'array'],
            'rows.*.search_group'     => ['nullable', 'string', 'max:50'],
        ]);

        $searchIds = array_map('intval', (array) $request->input('in_search', []));
        $changed = 0;
        $blocked = [];

        $tlds = Tld::whereIn('id', array_keys($data['rows']))->get()->keyBy('id');

        foreach ($data['rows'] as $id => $row) {
            $tld = $tlds->get((int) $id);

            if (! $tld) {
                continue;
            }

            $register = (float) $tld->register_price;

            // PENTING: is_active TIDAK lagi diurus di sini. Switch "Aktif"
            // di halaman Status TLD sekarang berjalan lewat AJAX ke
            // status(), karena aktivasi harus eksklusif per ekstensi
            // (mengaktifkan satu registrar mematikan yang lain). Kalau
            // is_active tetap ikut disimpan dari form ini, tombol Simpan
            // akan menonaktifkan SEMUA TLD -- form-nya sudah tidak lagi
            // mengirim input active[].
            //
            // Harga juga tidak diurus di sini lagi (pindah ke halaman
            // TLD Pricing) -- yang tersisa cuma Tampil di Web & Grup.
            $values = [
                // TLD tanpa harga jual tidak boleh tampil di halaman publik,
                // apa pun centangnya — kalau tidak, pengunjung melihat
                // domain seharga Rp 0.
                'show_in_search' => in_array((int) $id, $searchIds, true) && $register > 0,
                'search_group'   => $row['search_group'] ?? $tld->search_group,
            ];

            if (in_array((int) $id, $searchIds, true) && $register <= 0) {
                $blocked[] = $tld->extension;
            }

            $tld->fill($values);

            if ($tld->isDirty()) {
                $tld->save();
                $changed++;
            }
        }

        if ($changed === 0) {
            $msg = 'Tidak ada nilai yang berubah, jadi tidak ada yang perlu disimpan.';

            return $request->wantsJson()
                ? response()->json(['ok' => true, 'changed' => 0, 'message' => $msg])
                : back()->with('info', $msg);
        }

        $msg = "{$changed} TLD berhasil diperbarui.";

        if ($blocked) {
            $count = count($blocked);
            $sample = implode(', ', array_slice($blocked, 0, 5));
            $msg .= " {$count} TLD tidak jadi ditampilkan di web karena harga register-nya masih 0 ({$sample}" . ($count > 5 ? ', …' : '') . ').';
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'changed' => $changed, 'message' => $msg]);
        }

        return back()->with($blocked ? 'error' : 'success', $msg);
    }

    /**
     * Ubah input jadi angka; kosong berarti pakai nilai lama.
     */
    private function toNumber(mixed $value, mixed $fallback): float
    {
        if ($value === null || $value === '') {
            return (float) $fallback;
        }

        return round((float) $value, 2);
    }

    public function create(): View
    {
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();

        return view('admin.tlds.form', ['tld' => new Tld(), 'registrars' => $registrars]);
    }

    public function createBootstrap(): View
    {
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();

        return view('admin.tlds.form', ['tld' => new Tld(), 'registrars' => $registrars]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        Tld::create($data);

        return redirect()->route('admin.tlds.index')->with('success', 'TLD berhasil ditambahkan.');
    }

    public function edit(Tld $tld): View
    {
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();

        return view('admin.tlds.form', ['tld' => $tld, 'registrars' => $registrars]);
    }

    public function editBootstrap(Tld $tld): View
    {
        $registrars = Registrar::where('is_active', true)->orderBy('name')->get();

        return view('admin.tlds.form', ['tld' => $tld, 'registrars' => $registrars]);
    }

    public function update(Request $request, Tld $tld): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        $tld->update($data);

        return redirect()->route('admin.tlds.index')->with('success', 'TLD berhasil diperbarui.');
    }

    public function destroy(Tld $tld): RedirectResponse
    {
        $tld->delete();

        return redirect()->route('admin.tlds.index')->with('success', 'TLD berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'extension'      => ['required', 'string', 'max:30', 'unique:tlds,extension,' . $request->route('tld')?->id],
            'registrar_id'   => ['nullable', 'exists:registrars,id'],
            'register_price' => ['required', 'numeric', 'min:0'],
            'renew_price'    => ['required', 'numeric', 'min:0'],
            'transfer_price' => ['required', 'numeric', 'min:0'],
            'min_years'      => ['required', 'integer', 'min:1', 'max:10'],
            'max_years'      => ['required', 'integer', 'min:1', 'max:10'],
            'is_active'      => ['nullable', 'boolean'],
            'year_prices'    => ['nullable', 'array'],
            'year_prices.*'  => ['nullable', 'numeric', 'min:0'],
            'year_renew_prices'   => ['nullable', 'array'],
            'year_renew_prices.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Buang durasi yang dikosongkan supaya tidak tersimpan sebagai 0 —
        // nilai kosong berarti "pakai perhitungan otomatis".
        foreach (['year_prices', 'year_renew_prices'] as $field) {
            $data[$field] = collect($data[$field] ?? [])
                ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
                ->map(fn ($v) => (float) $v)
                ->all() ?: null;
        }

        return $data;
    }
}
