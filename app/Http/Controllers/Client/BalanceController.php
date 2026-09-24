<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\Billing\BillingException;
use App\Http\Controllers\Controller;
use App\Services\Billing\BillingService;
use App\Services\Billing\TopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BalanceController extends Controller
{
    use AuthorizesClientOwnership;

    public function index(): View
    {
        return view('client.balance.index', $this->indexData());
    }

    public function indexBootstrap(): View
    {
        return view('client.balance.index', $this->indexData());
    }

    private function indexData(): array
    {
        $client = Auth::guard('client')->user();

        $logs = $client->balanceLogs()->latest()->paginate(15);

        return compact('client', 'logs');
    }

    /**
     * Klien minta isi ulang — dibuatkan invoice khusus (is_topup=true)
     * lalu diarahkan ke halaman bayar invoice BIASA. Sengaja dibuat
     * begini supaya seluruh jalur pembayaran yang sudah ada (QRIS,
     * Midtrans, Xendit, Duitku, transfer manual + upload bukti) langsung
     * bisa dipakai tanpa perlu membangun jalur pembayaran terpisah.
     */
    public function topup(Request $request, TopupService $topups): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000', 'max:50000000'],
        ], [
            'amount.min' => 'Minimal isi ulang Rp 10.000.',
            'amount.max' => 'Maksimal isi ulang Rp 50.000.000 per transaksi.',
        ]);

        $client = Auth::guard('client')->user();
        $invoice = $topups->createInvoice($client, (float) $data['amount']);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice isi ulang saldo dibuat. Saldo bertambah otomatis setelah dibayar.');
    }

    /**
     * Bayar invoice mana pun pakai saldo — kalau cukup, langsung lunas
     * seketika tanpa lewat gateway pembayaran sama sekali.
     */
    public function payWithBalance(Invoice $invoice, BillingService $billing): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $this->authorizeOwner($invoice);

        try {
            $billing->payInvoiceWithBalance($invoice, $client);
        } catch (BillingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice berhasil dibayar pakai saldo.');
    }
}
