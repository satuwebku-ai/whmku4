<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coupon;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tld;
use App\Services\Billing\CouponService;
use App\Services\Cart\CartService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(CartService $cart, CouponService $coupons): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang Anda masih kosong.');
        }

        return view('client.checkout.index', $this->checkoutData($cart, $coupons));
    }

    public function indexBootstrap(CartService $cart, CouponService $coupons): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang Anda masih kosong.');
        }

        return view('client.checkout.index', $this->checkoutData($cart, $coupons));
    }

    private function checkoutData(CartService $cart, CouponService $coupons): array
    {
        $subtotal = $cart->subtotal();
        $coupon = $this->sessionCoupon();
        $discount = $coupons->discountFor($coupon, $cart->items());

        return [
            'items'    => collect($cart->items()),
            'subtotal' => $subtotal,
            'coupon'   => $coupon,
            'discount' => $discount,
            'client'   => Auth::guard('client')->user(),
            'issues'   => $this->validateCart($cart),
        ];
    }

    /**
     * Terapkan kode kupon ke sesi checkout — belum menyentuh invoice
     * apapun, hanya disimpan sementara sampai order benar-benar dibuat.
     */
    public function applyCoupon(Request $request, CartService $cart, CouponService $coupons): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);

        $coupon = $coupons->findByCode($data['code']);

        if (! $coupon) {
            return back()->with('error', 'Kode kupon tidak ditemukan.');
        }

        $client = Auth::guard('client')->user();
        $error = $coupons->validationError($coupon, $client, $cart->items());

        if ($error) {
            return back()->with('error', $error);
        }

        session(['checkout.coupon_id' => $coupon->id]);

        return back()->with('success', "Kupon {$coupon->code} berhasil diterapkan.");
    }

    public function removeCoupon(): RedirectResponse
    {
        session()->forget('checkout.coupon_id');

        return back()->with('success', 'Kupon dibatalkan.');
    }

    private function sessionCoupon(): ?Coupon
    {
        $id = session('checkout.coupon_id');

        return $id ? Coupon::find($id) : null;
    }

    public function store(CartService $cart, CouponService $coupons): RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang Anda masih kosong.');
        }

        $issues = $this->validateCart($cart);

        if ($issues) {
            return redirect()->route('client.checkout')->with('error', implode(' ', $issues));
        }

        /** @var Client $client */
        $client = Auth::guard('client')->user();

        // Divalidasi ulang di sini (bukan cuma saat diterapkan) karena isi
        // keranjang atau batas pemakaian kupon bisa berubah di antara
        // waktu kupon diterapkan dan tombol bayar ditekan.
        $coupon = $this->sessionCoupon();

        if ($coupon) {
            $error = $coupons->validationError($coupon, $client, $cart->items());

            if ($error) {
                session()->forget('checkout.coupon_id');

                return redirect()->route('client.checkout')->with('error', "Kupon tidak bisa dipakai: {$error}");
            }
        }

        try {
            $invoice = null;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $invoice = DB::transaction(function () use ($cart, $client, $coupon, $coupons) {
                // PENTING: baris & total dihitung DULU, sebelum invoice dibuat --
                // supaya event static::created() di model Invoice (yang
                // mengirim notifikasi ke klien & admin) langsung menerima
                // total yang benar. Sebelumnya invoice dibuat dengan
                // amount:0 lalu di-update belakangan, tapi notifikasi sudah
                // keburu terkirim saat masih 0 (bug: email admin "Total: Rp 0").
                $lines = [];
                $total = 0;

                foreach ($cart->items() as $item) {
                    foreach ($this->buildLinesForItem($client, $item) as $line) {
                        $lines[] = $line;
                        $total += $line['amount'];
                    }
                }

                $discount = $coupons->discountFor($coupon, $cart->items());

                $invoice = Invoice::create([
                    'client_id'  => $client->id,
                    'amount'     => $total,
                    'tax'        => 0,
                    'discount'   => $discount,
                    'coupon_id'  => $coupon?->id,
                    'status'     => 'unpaid',
                    'issue_date' => now(),
                    'due_date'   => now()->addDays(3),
                ]);

                foreach ($lines as $line) {
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'order_id'    => $line['order']->id,
                        'description' => $line['description'],
                        'amount'      => $line['amount'],
                    ]);
                }

                if ($coupon) {
                    $coupons->reserveForInvoice($coupon, $client, $invoice, $discount);
                }

                        return $invoice;
                    });
                    break;
                } catch (QueryException $e) {
                    // order_number/invoice_number memiliki UNIQUE index. Jika
                    // dua checkout bersamaan menghasilkan nomor yang sama,
                    // transaksi kedua diulang sehingga generator mengambil
                    // nomor terbaru setelah transaksi pertama commit.
                    $message = strtolower($e->getMessage());
                    if ($attempt >= 3 || ! str_contains($message, 'duplicate')) {
                        throw $e;
                    }
                }
            }
        } catch (\RuntimeException $e) {
            // Stok habis pas transaksi berjalan (lihat buildHostingLines) --
            // seluruh transaksi otomatis di-rollback (termasuk decrement
            // stok yang sempat terjadi), jadi aman diberi tahu ke klien
            // tanpa ada efek samping yang tertinggal.
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        $cart->clear();
        session()->forget('checkout.coupon_id');

        // Kalau ada domain di pesanan ini yang butuh berkas persyaratan,
        // klien diarahkan ke halaman berkas DULU -- bukan ke invoice.
        //
        // Alasannya: pembayarannya toh akan ditolak gerbang berkas
        // (lihat InvoiceController::documentBlocker). Mengarahkan ke
        // invoice lebih dulu cuma membuat klien menekan "Bayar", ditolak,
        // lalu bingung harus ke mana. Lebih jelas kalau langsung
        // ditunjukkan apa yang harus dilengkapi.
        // Domain dicari lewat order_id yang tercatat di item invoice --
        // $order sendiri dibuat di dalam transaksi dan tidak tersedia
        // di sini, dan satu invoice bisa memuat lebih dari satu order.
        $orderIds = $invoice->items()->pluck('order_id')->filter()->unique();

        $perluBerkas = $orderIds->isEmpty() ? null : \App\Models\Domain::with(['tld', 'documents'])
            ->whereIn('order_id', $orderIds)
            ->get()
            ->first(fn ($d) => ! \App\Models\DomainDocument::progressFor($d)['complete']);

        if ($perluBerkas) {
            return redirect()->route('client.domains.documents', $perluBerkas)->with(
                'success',
                "Pesanan {$invoice->invoice_number} berhasil dibuat. Domain {$perluBerkas->domain_name} membutuhkan berkas persyaratan — lengkapi dulu di bawah ini. Setelah semua berkas disetujui tim kami, Anda bisa melanjutkan pembayaran."
            );
        }

        return redirect()->route('client.invoices.show', $invoice)->with(
            'success',
            "Pesanan {$invoice->invoice_number} berhasil dibuat! Pilih metode pembayaran di bawah untuk mengaktifkan layanan Anda."
        );
    }

    /**
     * Validasi yang HARUS lolos sebelum checkout diproses — saat ini
     * cuma satu: domain yang mau didaftarkan baru harus punya ekstensi
     * yang benar-benar dijual (ada harganya), karena form produk di
     * Fase 7b menerima nama domain bebas teks tanpa validasi TLD.
     *
     * @return string[]
     */
    private function validateCart(CartService $cart): array
    {
        $issues = [];

        foreach ($cart->items() as $item) {
            $mode = $item['domain_mode'] ?? null;

            if ($item['type'] === 'product' && ! empty($item['product_id'])) {
                $product = Product::with('server')->find($item['product_id']);

                if ($product && ! $product->isInStock()) {
                    $issues[] = "Maaf, stok \"{$item['name']}\" sudah habis. Hapus item ini dari keranjang.";
                }

                if ($product?->server && in_array($product->server->panel, ['directadmin', 'plesk'], true)) {
                    $issues[] = "Paket \"{$item['name']}\" belum dapat dipesan karena panel {$product->server->panel} belum didukung untuk provisioning otomatis.";
                }
            }

            if ($item['type'] === 'product' && in_array($mode, ['register', 'transfer'], true)) {
                $domainName = $item['domain_name'] ?? '';

                if (! $this->resolveTld($domainName)) {
                    $label = $mode === 'transfer' ? 'Transfer domain' : 'Daftarkan domain baru';
                    $issues[] = "Domain \"{$domainName}\" pada paket \"{$item['name']}\" tidak bisa diproses (ekstensi belum dijual atau nama domain tidak lengkap). Hapus item ini dari keranjang dan tambahkan ulang tanpa opsi \"{$label}\", atau pilih domain lain.";
                }

                if ($mode === 'transfer' && blank($item['transfer_auth_code'] ?? null)) {
                    $issues[] = "Kode EPP/Auth untuk transfer domain \"{$domainName}\" belum diisi. Hapus item ini dan tambahkan ulang dengan kode EPP-nya.";
                }
            }

            // Cegah pesanan "hosting baru" untuk domain yang ternyata
            // SUBDOMAIN dari domain yang sudah punya hosting aktif --
            // cPanel/WHM tidak mengizinkan subdomain punya akun cPanel
            // sendiri yang terpisah (harus jadi subdomain DI DALAM akun
            // induknya). Tanpa pengecekan ini, order akan lolos checkout
            // & terbayar, tapi provisioning otomatis akan GAGAL TOTAL
            // saat WHM menolaknya sebagai "already exists in userdata".
            if ($item['type'] === 'product' && filled($item['domain_name'] ?? null)) {
                $parentDomain = $this->findExistingParentHosting($item['domain_name']);

                if ($parentDomain) {
                    $issues[] = "\"{$item['domain_name']}\" adalah SUBDOMAIN dari \"{$parentDomain}\" yang sudah punya hosting aktif. "
                        . 'Subdomain tidak bisa dijadikan akun hosting terpisah (harus ditambahkan di dalam akun hosting yang sudah ada) — hapus item ini dari keranjang, lalu hubungi support untuk menambahkan subdomain tersebut ke hosting yang sudah ada.';
                }
            }
        }

        return $issues;
    }

    /**
     * Cek apakah $domainName adalah subdomain dari domain lain yang
     * SUDAH punya hosting_account aktif/pending di sistem ini. Mengecek
     * bertahap dari induk terdekat (satu label dibuang) sampai domain
     * dasar (2 label) -- supaya subdomain bertingkat (mis.
     * a.b.contoh.com) tetap terdeteksi kalau "b.contoh.com" atau
     * "contoh.com" sudah dihosting.
     */
    private function findExistingParentHosting(string $domainName): ?string
    {
        $labels = explode('.', strtolower(trim($domainName)));

        // Minimal 3 label (mis. cloud.contoh.com) supaya ada kandidat
        // induk yang tersisa 2 label -- domain dasar sendiri (2 label)
        // tidak mungkin jadi subdomain siapa pun.
        if (count($labels) < 3) {
            return null;
        }

        for ($i = 1; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));

            $exists = HostingAccount::whereRaw('LOWER(domain) = ?', [$candidate])
                ->whereNotIn('status', ['terminated', 'cancelled'])
                ->exists();

            if ($exists) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveTld(string $domainName): ?Tld
    {
        if (! str_contains($domainName, '.')) {
            return null;
        }

        $ext = '.' . Str::after($domainName, '.');
        $ext = strtolower($ext);

        // Sumber kebenaran penjualan: hanya TLD aktif yang terhubung ke
        // registrar aktif. Admin boleh menyimpan beberapa registrar untuk
        // extension yang sama, tetapi hanya satu yang boleh dijual.
        return Tld::query()
            ->whereRaw('LOWER(extension) = ?', [$ext])
            ->where('is_active', true)
            ->where('register_price', '>', 0)
            ->whereHas('registrar', fn ($q) => $q
                ->where('is_active', true)
                ->whereNotIn('provider', ['resellbiz']))
            ->with('registrar')
            ->first();
    }

    /**
     * Satu item keranjang bisa menghasilkan lebih dari satu baris invoice —
     * mis. hosting yang dibundel dengan pendaftaran domain baru menjadi
     * 2 order (hosting + domain) tapi tetap 1 invoice. Opsi konfigurasi
     * yang dipilih klien (RAM Tambahan, dst) juga jadi baris tersendiri,
     * tapi menumpang di ORDER hosting-nya, bukan order baru — opsi bukan
     * entitas yang diprovisioning terpisah.
     *
     * @return array<int, array{order: Order, amount: float, description: string}>
     */
    private function buildLinesForItem(Client $client, array $item): array
    {
        if ($item['type'] === 'domain') {
            return [$this->buildStandaloneDomainLine($client, $item)];
        }

        $lines = $this->buildHostingLines($client, $item);

        if (($item['domain_mode'] ?? null) === 'register' && filled($item['domain_name'] ?? null)) {
            $tld = $this->resolveTld($item['domain_name']);

            if ($tld) {
                $lines[] = $this->buildBundledDomainLine($client, $item['domain_name'], $tld);
            }
        } elseif (($item['domain_mode'] ?? null) === 'transfer' && filled($item['domain_name'] ?? null)) {
            $tld = $this->resolveTld($item['domain_name']);

            if ($tld) {
                $lines[] = $this->buildTransferDomainLine($client, $item['domain_name'], $tld, $item['transfer_auth_code'] ?? '');
            }
        }

        return $lines;
    }

    /**
     * Bikin HostingAccount + Order untuk item hosting, lalu satu baris
     * invoice untuk harga dasarnya, DITAMBAH satu baris invoice terpisah
     * per opsi konfigurasi yang dipilih klien (kalau ada) -- supaya
     * klien lihat persis apa yang mereka bayar, bukan cuma satu angka
     * gabungan. HostingAccount::price SENGAJA cuma harga dasar (tanpa
     * opsi), karena itu yang dipakai lagi sebagai basis tagihan di
     * setiap invoice perpanjangan (lihat HostingAccount::renewalAmount()
     * & createRenewalInvoice(), yang menjumlahkan basis ini dengan opsi
     * & addon aktif secara terpisah) -- opsi ikut ditagih lewat baris
     * hosting_account_options-nya sendiri, bukan dobel terhitung di sini.
     *
     * @return array<int, array{order: Order, amount: float, description: string}>
     */
    private function buildHostingLines(Client $client, array $item): array
    {
        $product = ! empty($item['product_id']) ? Product::with('server')->find($item['product_id']) : null;
        $domainName = $item['domain_name'] ?? null;
        $clientPricing = $product?->pricingForClientCycle($client->client_group_id, $item['billing_cycle']);
        if ($product && ! $clientPricing) {
            throw new \RuntimeException("Harga paket \"{$item['name']}\" untuk siklus tersebut sudah tidak tersedia.");
        }
        $basePrice = (float) ($clientPricing['price'] ?? ($item['base_price'] ?? $item['price']));
        $setupFee = (float) ($clientPricing['setup_fee'] ?? ($item['setup_fee'] ?? 0));

        // Produk billing_mode='deposit' (VPS Pay As You Grow) TIDAK ditagih
        // di muka lewat harga siklus -- itu cuma dipakai admin untuk kartu
        // katalog. Pemakaian sesungguhnya ditagih per jam dari saldo lewat
        // ChargeHourlyUsage setelah layanan aktif. Kalau basePrice tetap
        // dipungut di sini, klien kena tagih dua kali (invoice muka +
        // potongan saldo per jam) -- jadi dipaksa 0, biaya setup tetap
        // jalan seperti biasa (itu ongkos provisioning, bukan pemakaian).
        $isDeposit = $product?->billing_mode === 'deposit';
        if ($isDeposit) {
            $basePrice = 0;
        }

        // Auto-provisioning butuh DUA hal: server tujuan DAN nama paket WHM
        // yang persis sama dengan yang ada di server itu. Server saja tidak
        // cukup — kalau nama paketnya kosong, memaksa tetap mencoba hanya
        // akan gagal dengan error mentah dari WHM ("package not found")
        // yang membingungkan. Diperlakukan sama seperti "belum diatur sama
        // sekali", jatuh ke mode manual dengan pesan yang jelas.
        $readyForAutoProvision = $product?->server_id && filled($product?->panel_package);

        // Stok terbatas: dikunci & dikurangi DI SINI (di dalam transaksi
        // checkout), bukan cuma dicek waktu tambah ke keranjang seperti
        // sebelumnya. lockForUpdate() mencegah dua client checkout produk
        // stok-1 nyaris bersamaan sama-sama lolos -- yang kedua akan
        // menunggu row lock yang pertama, lalu lihat stok sudah 0, gagal
        // dengan jelas alih-alih dua-duanya berhasil.
        if ($product && $product->stock !== null) {
            $locked = Product::where('id', $product->id)->lockForUpdate()->first();
            $reserved = (int) ($locked?->reserved_stock ?? 0);
            $available = $locked ? ((int) $locked->stock - $reserved) : 0;

            if (! $locked || $available <= 0) {
                throw new \RuntimeException("Maaf, stok \"{$item['name']}\" baru saja habis. Item ini sudah dihapus dari keranjang, silakan pilih paket lain.");
            }

            $locked->increment('reserved_stock');
        }

        $hostingAccount = HostingAccount::create([
            'client_id'        => $client->id,
            'product_id'       => $product?->id,
            'server_id'        => $readyForAutoProvision ? $product->server_id : null,
            'domain'           => $domainName ?: ('layanan-' . Str::lower(Str::random(6))),
            'package'          => $product?->panel_package ?: ($product?->name ?? $item['name']),
            'panel'            => $product?->server?->panel ?? 'cpanel',
            'price'            => $basePrice,
            'billing_cycle'    => $item['billing_cycle'],
            'billing_mode'     => $isDeposit ? 'deposit' : 'invoice',
            'status'           => 'pending',
            'provision_status' => 'manual',
            'provision_message' => $readyForAutoProvision
                ? null
                : ($product?->server_id ? 'Nama paket WHM belum diatur di produk ini — aktivasi perlu dilakukan manual oleh admin.' : null),
            // Layanan deposit tidak punya siklus jatuh tempo -- tidak
            // pernah ditagih ulang lewat GenerateRenewalInvoices, jadi
            // next_due_date dikosongkan supaya command itu (yang jalan
            // berdasar next_due_date) tidak pernah menyentuhnya.
            'next_due_date'    => $isDeposit ? null : $this->nextDueDate($item['billing_cycle'], $product),
        ]);

        $optionsTotalForOrder = collect($item['selected_options'] ?? [])->sum(fn ($o) => (float) ($o['price'] ?? 0));

        $order = Order::create([
            'client_id'          => $client->id,
            'product_id'         => $product?->id,
            'hosting_account_id' => $hostingAccount->id,
            'product_name'       => $item['name'],
            'order_type'         => 'hosting',
            'amount'             => $basePrice + $optionsTotalForOrder + $setupFee, // total termasuk opsi & setup fee -- dipakai laporan/riwayat order.
            'status'             => \App\Enums\OrderStatus::PendingPayment,
            'stock_reservation_status' => $product && $product->stock !== null ? 'reserved' : 'none',
        ]);

        $cycleLabel = Product::CYCLES[$item['billing_cycle']] ?? $item['billing_cycle'];

        // Untuk deposit/PAYG tidak ada baris harga siklus (basePrice sudah
        // dipaksa 0 di atas) -- cukup baris keterangan aktivasi supaya
        // klien tetap lihat item ini di invoice/riwayat order, tanpa nilai
        // yang bikin bingung ("kok Rp 0 tapi tetap ada di invoice").
        $lines = $isDeposit
            ? [['order' => $order, 'amount' => 0, 'description' => "Aktivasi {$item['name']} — ditagih per jam dari saldo setelah aktif"]]
            : [['order' => $order, 'amount' => $basePrice, 'description' => "{$item['name']} ({$cycleLabel})"]];

        // Biaya setup: SEKALI di awal saja, TIDAK ikut disimpan di
        // hosting_accounts.price (itu basis renewal, dan setup fee tidak
        // boleh ikut ditagih ulang tiap perpanjangan) -- makanya baris
        // terpisah di sini, bukan digabung ke $basePrice.
        if ($setupFee > 0) {
            $lines[] = ['order' => $order, 'amount' => $setupFee, 'description' => "Biaya Setup — {$item['name']}"];
        }

        foreach ($item['selected_options'] ?? [] as $selected) {
            \App\Models\HostingAccountOption::create([
                'hosting_account_id'      => $hostingAccount->id,
                'product_option_id'       => $selected['option_id'],
                'product_option_group_id' => $selected['group_id'],
                'group_name'              => $selected['group_name'],
                'name'                    => $selected['name'],
                'price'                   => $selected['price'],
            ]);

            $lines[] = [
                'order' => $order,
                'amount' => (float) $selected['price'],
                'description' => "{$selected['name']} ({$item['name']}, {$cycleLabel})",
            ];
        }

        return $lines;
    }

    private function buildBundledDomainLine(Client $client, string $domainName, Tld $tld): array
    {
        $domain = Domain::create([
            'client_id'        => $client->id,
            'registrar_id'     => $tld->registrar_id,
            'tld_id'           => $tld->id,
            'domain_name'      => $domainName,
            'price'            => 0,
            'years'            => 1,
            'status'           => 'pending',
            'provision_status' => 'manual',
            // Domain bundel (gratis, ikut paket hosting) tidak melalui
            // halaman Keranjang tempat klien bisa memilih, jadi default
            // dinyalakan — sama seperti default checkbox di keranjang.
            'whois_privacy'    => false,
        ]);

        $order = Order::create([
            'client_id'    => $client->id,
            'product_name' => "Registrasi Domain {$domainName}",
            'order_type'   => 'domain',
            'amount'       => 0,
            'status'       => \App\Enums\OrderStatus::PendingPayment,
        ]);

        // Domain::order() adalah belongsTo lewat kolom domains.order_id
        // (Fase 4) — order_id BARU diketahui setelah $order dibuat, jadi
        // domain-nya diisi belakangan di sini, bukan saat Domain::create().
        $domain->update(['order_id' => $order->id]);

        return ['order' => $order, 'amount' => 0.0, 'description' => "Registrasi Domain {$domainName} (1 tahun) — GRATIS BUNDLE"];
    }

    /**
     * Sama seperti buildBundledDomainLine(), tapi untuk transfer domain
     * dari registrar lain — bukan registrasi baru. Bedanya cuma tiga:
     * harga pakai transfer_price (bukan register_price), domainnya
     * ditandai is_transfer=true supaya ProvisioningService tahu harus
     * memanggil transferDomain() bukan registerDomain(), dan kode EPP
     * klien ikut disimpan (terenkripsi) untuk dipakai saat itu.
     */
    private function buildTransferDomainLine(Client $client, string $domainName, Tld $tld, string $authCode): array
    {
        $domain = Domain::create([
            'client_id'          => $client->id,
            'registrar_id'       => $tld->registrar_id,
            'tld_id'             => $tld->id,
            'domain_name'        => $domainName,
            'price'              => $tld->transfer_price,
            'years'              => 1,
            'status'             => 'pending',
            'provision_status'   => 'manual',
            'whois_privacy'      => (bool) $tld->whois_privacy_eligible,
            'is_transfer'        => true,
            'transfer_auth_code' => $authCode,
        ]);

        $order = Order::create([
            'client_id'    => $client->id,
            'product_name' => "Transfer Domain {$domainName}",
            'order_type'   => 'domain',
            'amount'       => $tld->transfer_price,
            'status'       => \App\Enums\OrderStatus::PendingPayment,
        ]);

        $domain->update(['order_id' => $order->id]);

        return ['order' => $order, 'amount' => (float) $tld->transfer_price, 'description' => "Transfer Domain {$domainName} (+1 tahun)"];
    }

    private function buildStandaloneDomainLine(Client $client, array $item): array
    {
        $tld = $this->resolveTld((string) ($item['domain_name'] ?? ''));
        if (! $tld) {
            throw new \RuntimeException('TLD domain pada keranjang sudah tidak aktif untuk dijual. Silakan hapus item ini dan tambahkan kembali.');
        }
        $years = (int) ($item['years'] ?? 1);
        $isTransfer = ($item['domain_mode'] ?? null) === 'transfer';

        $domain = Domain::create([
            'client_id'        => $client->id,
            'registrar_id'     => $tld?->registrar_id,
            'tld_id'           => $tld->id,
            'domain_name'      => $item['domain_name'],
            'price'            => $isTransfer ? (float) $tld->transfer_price : (float) $tld->priceForYears($years),
            'years'            => $years,
            'status'           => 'pending',
            'provision_status' => 'manual',
            'whois_privacy'    => $tld->whois_privacy_eligible && (bool) ($item['whois_privacy'] ?? false),
            // Menandai ini transfer masuk, bukan registrasi baru --
            // ProvisioningService membaca ini untuk memanggil
            // transferDomain() alih-alih registerDomain().
            'is_transfer'        => $isTransfer,
            'transfer_auth_code' => $isTransfer ? ($item['transfer_auth_code'] ?? null) : null,
        ]);

        $label = $isTransfer ? 'Transfer Domain' : 'Registrasi Domain';

        $finalDomainPrice = $isTransfer ? (float) $tld->transfer_price : (float) $tld->priceForYears($years);
        if ($tld->whois_privacy_eligible && ($item['whois_privacy'] ?? false)) {
            $finalDomainPrice += (float) ($item['whois_privacy_price'] ?? 0);
        }

        $order = Order::create([
            'client_id'    => $client->id,
            'product_name' => "{$label} {$item['domain_name']}",
            'order_type'   => 'domain',
            'amount'       => $finalDomainPrice,
            'status'       => \App\Enums\OrderStatus::PendingPayment,
        ]);

        $domain->update(['order_id' => $order->id]);

        $hasPaidPrivacy = ($item['whois_privacy'] ?? false) && ($item['whois_privacy_price'] ?? 0) > 0;
        $description = "{$label} {$item['domain_name']}"
            . ($isTransfer ? ' (+1 tahun)' : " ({$years} tahun)")
            . ($hasPaidPrivacy ? ' + ID Protection' : '');

        return ['order' => $order, 'amount' => $finalDomainPrice, 'description' => $description];
    }

    private function nextDueDate(string $cycle, ?\App\Models\Product $product = null): Carbon
    {
        if ($cycle === 'custom') {
            return now()->addDays($product?->custom_cycle_days ?: 30);
        }

        return match ($cycle) {
            'monthly'       => now()->addMonth(),
            'quarterly'     => now()->addMonths(3),
            'semi_annually' => now()->addMonths(6),
            'annually'      => now()->addYear(),
            default         => now()->addMonth(),
        };
    }
}
