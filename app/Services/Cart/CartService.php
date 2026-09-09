<?php

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\Tld;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Keranjang belanja berbasis session — sengaja TIDAK memakai tabel
 * database, supaya pengunjung yang belum login/daftar tetap bisa
 * menambah item. Isi keranjang baru "menjadi nyata" sebagai Order +
 * Invoice saat checkout (Fase 7c), setelah klien login/registrasi.
 *
 * Struktur satu item:
 *   [
 *     'key'           => string acak, id baris di keranjang
 *     'type'          => 'product' | 'domain'
 *     'name'          => nama yang tampil
 *     'billing_cycle' => untuk type=product
 *     'base_price'    => untuk type=product, harga produk SAJA (tanpa opsi)
 *     'price'         => harga total per siklus/tahun (base_price + opsi, snapshot saat ditambahkan)
 *     'selected_options' => untuk type=product, array opsi konfigurasi terpilih:
 *                           [['group_id','group_name','option_id','name','price'], ...]
 *     'years'         => untuk type=domain
 *     'product_id' / 'tld_id' => referensi ke data asli
 *     'domain_mode'   => 'register' | 'existing' | null — untuk produk hosting
 *     'domain_name'   => nama domain yang menyertai produk, kalau ada
 *   ]
 */
class CartService
{
    private const SESSION_KEY = 'cart';

    /**
     * @return array<int, array>
     */
    public function items(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /**
     * Sinkronkan ulang harga item domain di keranjang dengan harga
     * TERKINI (TLD & add-on ID Protection) — dipanggil tiap kali halaman
     * Keranjang dibuka.
     *
     * Tanpa ini, item yang sudah lebih dulu ada di keranjang akan tetap
     * memakai harga lama selamanya (harga "dibekukan" saat ditambahkan,
     * supaya tidak berubah tiba-tiba di tengah proses checkout) — kalau
     * admin baru saja mengubah harga TLD atau harga add-on setelah klien
     * menambah domain, klien akan bingung kenapa perubahan itu tidak
     * pernah terlihat. Disegarkan di sini setiap kunjungan ke halaman
     * Keranjang supaya harga yang ditampilkan selalu yang terbaru, tanpa
     * klien perlu menghapus dan menambah ulang manual.
     */
    /**
     * Harga ID Protection yang berlaku untuk satu TLD -- dicek dari yang
     * paling spesifik dulu:
     *   1. Harga khusus TLD ini (kalau admin mengisinya)
     *   2. Harga default registrar-nya (kalau admin mengisinya)
     *   3. Harga global/default (Setting whois_privacy_price)
     */
    private function privacyPriceFor(Tld $tld): float
    {
        if ($tld->whois_privacy_price !== null) {
            return (float) $tld->whois_privacy_price;
        }

        if ($tld->registrar && $tld->registrar->whois_privacy_price !== null) {
            return (float) $tld->registrar->whois_privacy_price;
        }

        return (float) \App\Models\Setting::get('whois_privacy_price', 0);
    }

    public function refreshPricing(): void
    {
        $items = $this->items();
        $changed = false;

        foreach ($items as &$item) {
            if (($item['type'] ?? null) !== 'domain' || empty($item['tld_id'])) {
                continue;
            }

            $tld = \App\Models\Tld::find($item['tld_id']);

            if (! $tld) {
                continue;
            }

            $newBase = $tld->priceForYears((int) ($item['years'] ?? 1));
            $newAddon = $this->privacyPriceFor($tld);

            // TLD di bawah .id dilarang PANDI menawarkan WHOIS Privacy --
            // kalau statusnya BARU diubah admin jadi tidak-eligible SETELAH
            // item ini sudah ada di keranjang klien, matikan add-on-nya di
            // sini juga, jangan cuma dicegah di form penambahan baru.
            if (($item['whois_privacy_eligible'] ?? null) !== $tld->whois_privacy_eligible) {
                $item['whois_privacy_eligible'] = $tld->whois_privacy_eligible;
                $changed = true;
            }

            if (! $tld->whois_privacy_eligible && ($item['whois_privacy'] ?? false)) {
                $item['whois_privacy'] = false;
                $changed = true;
            }

            if (($item['base_price'] ?? null) != $newBase || ($item['whois_privacy_price'] ?? null) != $newAddon) {
                $item['base_price'] = $newBase;
                $item['whois_privacy_price'] = $newAddon;
                $item['price'] = ($item['whois_privacy'] ?? false) ? $newBase + $newAddon : $newBase;
                $changed = true;
            }
        }
        unset($item);

        if ($changed) {
            Session::put(self::SESSION_KEY, $items);
        }
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function subtotal(): float
    {
        return array_sum(array_column($this->items(), 'price'));
    }

    /**
     * Tambah produk hosting/layanan ke keranjang.
     *
     * @return array{success: bool, message: string}
     */
    public function addProduct(Product $product, string $cycle, ?string $domainMode = null, ?string $domainName = null, ?string $transferAuthCode = null, array $selectedOptions = []): array
    {
        if (! $product->is_active) {
            return ['success' => false, 'message' => 'Produk ini sedang tidak tersedia.'];
        }

        if (! $product->isInStock()) {
            return ['success' => false, 'message' => 'Stok produk ini sedang habis.'];
        }

        $price = $product->priceForCycle($cycle);

        if ($price === null) {
            return ['success' => false, 'message' => 'Siklus tagihan yang dipilih tidak tersedia untuk produk ini.'];
        }

        if ($product->requiresDomain() && blank($domainName)) {
            return ['success' => false, 'message' => 'Produk ini wajib disertai nama domain.'];
        }

        if ($domainMode === 'transfer' && blank($transferAuthCode)) {
            return ['success' => false, 'message' => 'Kode EPP/Auth diperlukan untuk transfer domain — minta dari registrar domain Anda saat ini.'];
        }

        // Cegah lebih awal (sebelum sempat masuk keranjang): domain yang
        // merupakan SUBDOMAIN dari domain lain yang sudah punya hosting
        // aktif tidak bisa dijadikan akun hosting terpisah -- cPanel/WHM
        // akan menolaknya saat provisioning ("already exists in
        // userdata"). Pengecekan final tetap ada lagi di checkout
        // sebagai jaring pengaman kedua.
        if ($product->allowsDomain() && filled($domainName)) {
            $parent = $this->findExistingParentHosting($domainName);

            if ($parent) {
                return ['success' => false, 'message' => "\"{$domainName}\" adalah subdomain dari \"{$parent}\" yang sudah punya hosting aktif. Subdomain tidak bisa dijadikan akun hosting terpisah — hubungi support untuk menambahkannya ke hosting yang sudah ada."];
            }
        }

        [$optionLines, $optionsTotal, $optionError] = $this->resolveSelectedOptions($product, $cycle, $selectedOptions);

        if ($optionError) {
            return ['success' => false, 'message' => $optionError];
        }

        $this->push([
            'key'           => (string) Str::uuid(),
            'type'          => 'product',
            'product_id'    => $product->id,
            'name'          => $product->name,
            'billing_cycle' => $cycle,
            'base_price'    => $price,
            'selected_options' => $optionLines,
            'price'         => $price + $optionsTotal,
            'setup_fee'     => (float) $product->setup_fee,
            'domain_mode'   => $product->allowsDomain() ? $domainMode : null,
            'domain_name'   => $product->allowsDomain() ? $domainName : null,
            'transfer_auth_code' => $product->allowsDomain() && $domainMode === 'transfer' ? $transferAuthCode : null,
        ]);

        return ['success' => true, 'message' => "{$product->name} ditambahkan ke keranjang."];
    }

    /**
     * Cocokkan input opsi terpilih (dari form pemesanan, format
     * `options[group_id] = option_id` untuk grup radio atau
     * `options[group_id][] = [option_id, ...]` untuk grup checkbox)
     * dengan grup opsi produk ini, lalu hitung harganya untuk siklus
     * tagihan yang dipilih.
     *
     * @return array{0: array, 1: float, 2: ?string} [baris opsi terpilih, total harga opsi, pesan error kalau ada]
     */
    private function resolveSelectedOptions(Product $product, string $cycle, array $selectedOptions): array
    {
        $lines = [];
        $total = 0.0;

        foreach ($product->optionGroups()->active()->with('options')->get() as $group) {
            $raw = $selectedOptions[$group->id] ?? null;
            $chosenIds = is_array($raw) ? $raw : (blank($raw) ? [] : [$raw]);
            $chosenIds = array_values(array_filter($chosenIds, fn ($id) => filled($id)));

            // Grup radio cuma boleh SATU pilihan — kalau form entah
            // bagaimana mengirim lebih dari satu (mis. dimanipulasi),
            // ambil yang pertama saja, bukan tolak seluruh pesanan.
            if ($group->isRadio()) {
                $chosenIds = array_slice($chosenIds, 0, 1);

                if ($group->is_required && empty($chosenIds)) {
                    return [[], 0.0, "Pilih salah satu opsi untuk \"{$group->name}\"."];
                }
            }

            foreach ($chosenIds as $optionId) {
                $option = $group->options->firstWhere('id', (int) $optionId);

                if (! $option || ! $option->is_active) {
                    continue;
                }

                $optPrice = $option->priceForCycle($cycle);

                // Opsi tidak dijual untuk siklus tagihan ini -- dilewati
                // diam-diam (bukan error keras), supaya klien yang ganti
                // siklus tagihan tidak diblokir hanya gara-gara satu opsi
                // yang tidak relevan lagi.
                if ($optPrice === null) {
                    continue;
                }

                $lines[] = [
                    'group_id'   => $group->id,
                    'group_name' => $group->name,
                    'option_id'  => $option->id,
                    'name'       => $option->name,
                    'price'      => $optPrice,
                ];
                $total += $optPrice;
            }
        }

        return [$lines, $total, null];
    }

    /**
     * Tambah domain baru (hasil dari halaman Cek Domain) ke keranjang.
     *
     * @return array{success: bool, message: string}
     */
    public function addDomain(string $domainName, Tld $tld, int $years = 1, bool $isTransfer = false, ?string $authCode = null): array
    {
        if (! $tld->is_active) {
            return ['success' => false, 'message' => 'Ekstensi domain ini sedang tidak dijual.'];
        }

        // Transfer masuk wajib disertai kode EPP/Auth Code dari registrar
        // lama — tanpa itu registrar tujuan pasti menolak permintaannya,
        // jadi lebih baik dicegah di sini daripada gagal setelah klien
        // terlanjur bayar.
        if ($isTransfer && blank($authCode)) {
            return ['success' => false, 'message' => 'Kode EPP/Auth Code wajib diisi untuk transfer domain.'];
        }

        if ($isTransfer && ! $tld->transfer_price) {
            return ['success' => false, 'message' => "Transfer untuk ekstensi .{$tld->extension} belum tersedia. Silakan hubungi kami."];
        }

        $years = max($tld->min_years, min($years, $tld->max_years));

        $domainName = strtolower(trim($domainName));

        // Domain yang sama tidak boleh masuk dua kali — kalau lolos, klien
        // akan ditagih ganda untuk satu domain yang hanya bisa didaftarkan
        // sekali.
        foreach ($this->items() as $item) {
            if (($item['type'] ?? null) === 'domain' && strtolower($item['domain_name'] ?? '') === $domainName) {
                return ['success' => false, 'message' => "{$domainName} sudah ada di keranjang Anda."];
            }
        }

        // Domain yang sudah terdaftar di sistem tidak bisa dipesan lagi.
        if (\App\Models\Domain::where('domain_name', $domainName)
            ->whereIn('status', ['pending', 'active'])
            ->exists()) {
            return ['success' => false, 'message' => "{$domainName} sudah terdaftar dan tidak bisa dipesan lagi."];
        }

        // Harga ID Protection diambil sekali (snapshot) saat ditambahkan,
        // supaya kalau admin mengubah harganya nanti, item yang sudah ada
        // di keranjang klien lain tidak ikut berubah harganya diam-diam.
        // Pakai harga khusus TLD ini kalau ada, kalau tidak jatuh balik
        // ke harga default -- lihat privacyPriceFor().
        $privacyPrice = $this->privacyPriceFor($tld);

        // Transfer dihargai dengan transfer_price (biasanya beda dari
        // harga registrasi baru) — dan selalu 1 tahun, karena transfer
        // menambahkan tepat satu tahun ke masa berlaku yang sudah ada.
        $basePrice = $isTransfer ? (float) $tld->transfer_price : $tld->priceForYears($years);

        if ($isTransfer) {
            $years = 1;
        }

        // TLD di bawah .id dilarang PANDI menawarkan WHOIS Privacy --
        // kalau tidak eligible, ID Protection TIDAK dinyalakan default
        // (beda dari TLD lain yang defaultnya menyala).
        $privacyDefault = $tld->whois_privacy_eligible;

        $this->push([
            'key'         => (string) Str::uuid(),
            'type'        => 'domain',
            'tld_id'      => $tld->id,
            'domain_name' => $domainName,
            'years'       => $years,
            'base_price'  => $basePrice,
            'whois_privacy_price' => $privacyPrice,
            'whois_privacy_eligible' => $tld->whois_privacy_eligible,
            'price'       => $privacyDefault ? $basePrice + $privacyPrice : $basePrice,
            'whois_privacy' => $privacyDefault,
            // Dibaca CheckoutController untuk memilih jalur transfer
            // (transferDomain) alih-alih registrasi baru.
            'domain_mode'  => $isTransfer ? 'transfer' : 'register',
            'transfer_auth_code' => $isTransfer ? $authCode : null,
        ]);

        return ['success' => true, 'message' => $isTransfer
            ? "Permintaan transfer {$domainName} ditambahkan ke keranjang."
            : "{$domainName} ditambahkan ke keranjang."];
    }

    /**
     * Nyalakan/matikan ID Protection untuk satu item domain di keranjang,
     * dan sesuaikan harganya (tambah/kurangi harga add-on yang sudah
     * di-snapshot saat item ditambahkan).
     */
    public function toggleWhoisPrivacy(string $key): void
    {
        $items = $this->items();

        foreach ($items as &$item) {
            if ($item['key'] === $key && ($item['type'] ?? null) === 'domain') {
                $wantOn = ! ($item['whois_privacy'] ?? false);

                // TLD di bawah .id dilarang PANDI menawarkan WHOIS Privacy
                // -- boleh dimatikan kapan saja, tapi tidak boleh
                // dinyalakan kalau memang tidak eligible.
                if ($wantOn && ! ($item['whois_privacy_eligible'] ?? true)) {
                    continue;
                }

                $item['whois_privacy'] = $wantOn;

                $base = $item['base_price'] ?? $item['price'];
                $addon = $item['whois_privacy_price'] ?? 0;
                $item['price'] = $item['whois_privacy'] ? $base + $addon : $base;
            }
        }
        unset($item);

        Session::put(self::SESSION_KEY, $items);
    }

    public function remove(string $key): void
    {
        $items = collect($this->items())->reject(fn ($item) => $item['key'] === $key)->values()->all();

        Session::put(self::SESSION_KEY, $items);
    }

    /**
     * Ubah lama tahun untuk item domain (harga ikut dihitung ulang).
     */
    public function updateDomainYears(string $key, int $years): void
    {
        $items = $this->items();

        foreach ($items as &$item) {
            if ($item['key'] === $key && $item['type'] === 'domain') {
                $tld = Tld::find($item['tld_id']);

                if ($tld) {
                    $years = max($tld->min_years, min($years, $tld->max_years));
                    $item['years'] = $years;
                    // Harus memakai perhitungan yang sama dengan addDomain,
                    // kalau tidak harga bisa berubah hanya karena pengguna
                    // mengubah durasi bolak-balik.
                    $item['base_price'] = $tld->priceForYears($years);
                    $addon = $item['whois_privacy_price'] ?? 0;
                    $item['price'] = ($item['whois_privacy'] ?? false) ? $item['base_price'] + $addon : $item['base_price'];
                }
            }
        }
        unset($item);

        Session::put(self::SESSION_KEY, $items);
    }

    /**
     * Ubah siklus tagihan untuk item produk (harga ikut dihitung ulang
     * dari harga produk saat ini — bukan harga snapshot lama — begitu
     * juga harga tiap opsi konfigurasi yang sudah dipilih; opsi yang
     * ternyata tidak dijual untuk siklus baru otomatis gugur dari item
     * ini, bukan dianggap gratis).
     */
    public function updateProductCycle(string $key, string $cycle): void
    {
        $items = $this->items();

        foreach ($items as &$item) {
            if ($item['key'] === $key && $item['type'] === 'product') {
                $product = Product::find($item['product_id']);
                $price = $product?->priceForCycle($cycle);

                if ($price !== null) {
                    $item['billing_cycle'] = $cycle;
                    $item['base_price'] = $price;

                    $optionsTotal = 0.0;
                    $selected = [];

                    foreach ($item['selected_options'] ?? [] as $chosen) {
                        $option = \App\Models\ProductOption::find($chosen['option_id']);
                        $newPrice = $option?->priceForCycle($cycle);

                        if ($option && $option->is_active && $newPrice !== null) {
                            $selected[] = [
                                'group_id'   => $chosen['group_id'],
                                'group_name' => $chosen['group_name'],
                                'option_id'  => $option->id,
                                'name'       => $option->name,
                                'price'      => $newPrice,
                            ];
                            $optionsTotal += $newPrice;
                        }
                    }

                    $item['selected_options'] = $selected;
                    $item['price'] = $price + $optionsTotal;
                }
            }
        }
        unset($item);

        Session::put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    private function push(array $item): void
    {
        $items = $this->items();
        $items[] = $item;

        Session::put(self::SESSION_KEY, $items);
    }

    /**
     * Cek apakah $domainName adalah subdomain dari domain lain yang
     * SUDAH punya hosting_account aktif/pending di sistem ini. Sama
     * logikanya dengan pengecekan final di CheckoutController --
     * dobel sengaja, supaya klien dapat peringatan sedini mungkin
     * (saat menambah ke keranjang), bukan cuma saat checkout.
     */
    private function findExistingParentHosting(string $domainName): ?string
    {
        $labels = explode('.', strtolower(trim($domainName)));

        if (count($labels) < 3) {
            return null;
        }

        for ($i = 1; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));

            $exists = \App\Models\HostingAccount::whereRaw('LOWER(domain) = ?', [$candidate])
                ->whereNotIn('status', ['terminated', 'cancelled'])
                ->exists();

            if ($exists) {
                return $candidate;
            }
        }

        return null;
    }
}
