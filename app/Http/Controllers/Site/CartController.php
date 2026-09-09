<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(CartService $cart): View
    {
        return view('public.cart.index', $this->indexData($cart));
    }

    public function indexBootstrap(CartService $cart): View
    {
        return view('public.cart.index', $this->indexData($cart));
    }

    private function indexData(CartService $cart): array
    {
        // Menyegarkan harga domain di keranjang dengan harga terkini
        // (TLD & add-on ID Protection) — lihat penjelasan lengkap di
        // CartService::refreshPricing().
        $cart->refreshPricing();

        // Ditampilkan di sidebar keranjang supaya pengunjung tetap bisa
        // menjelajah kategori lain tanpa harus kembali ke halaman utama —
        // paling berguna justru saat keranjang masih kosong.
        $categories = ProductCategory::active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($cat) => $cat->products_count > 0);

        return [
            'items' => $cart->items(),
            'subtotal' => $cart->subtotal(),
            'categories' => $categories,
        ];
    }

    public function addProduct(Request $request, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'product_id'   => ['required', 'exists:products,id'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annually,annually,custom'],
            'domain_mode'  => ['nullable', 'in:register,transfer,existing'],
            'domain_name'  => ['nullable', 'string', 'max:255'],
            'transfer_auth_code' => ['nullable', 'string', 'max:255'],
            'options'      => ['nullable', 'array'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        $result = $cart->addProduct(
            $product,
            $data['billing_cycle'],
            $data['domain_mode'] ?? null,
            $data['domain_name'] ?? null,
            $data['transfer_auth_code'] ?? null,
            $data['options'] ?? [],
        );

        if (! $result['success']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('cart.index')->with('success', $result['message']);
    }

    public function updateProductCycle(Request $request, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,semi_annually,annually,custom'],
        ]);

        $cart->updateProductCycle($data['key'], $data['billing_cycle']);

        return back()->with('success', 'Siklus tagihan diperbarui.');
    }

    public function updateDomainYears(Request $request, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'years' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $cart->updateDomainYears($data['key'], $data['years']);

        return back()->with('success', 'Lama registrasi domain diperbarui.');
    }

    public function toggleWhoisPrivacy(Request $request, CartService $cart): RedirectResponse
    {
        $cart->toggleWhoisPrivacy($request->input('key'));

        return back();
    }

    public function remove(Request $request, CartService $cart): RedirectResponse
    {
        $cart->remove($request->input('key'));

        return back()->with('success', 'Item dihapus dari keranjang.');
    }

    public function clear(CartService $cart): RedirectResponse
    {
        $cart->clear();

        return back()->with('success', 'Keranjang dikosongkan.');
    }
}
