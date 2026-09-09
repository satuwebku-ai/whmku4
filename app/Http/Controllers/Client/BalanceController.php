<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
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
    public function topup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000', 'max:50000000'],
        ], [
            'amount.min' => 'Minimal isi ulang Rp 10.000.',
            'amount.max' => 'Maksimal isi ulang Rp 50.000.000 per transaksi.',
        ]);

        $client = Auth::guard('client')->user();

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'amount' => $data['amount'],
            'tax' => 0,
            'discount' => 0,
            'status' => 'unpaid',
            'issue_date' => now(),
            'due_date' => now()->addDays(3),
            'is_topup' => true,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Isi Ulang Saldo',
            'amount' => $data['amount'],
        ]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice isi ulang saldo dibuat. Saldo bertambah otomatis setelah dibayar.');
    }

    /**
     * Bayar invoice mana pun pakai saldo — kalau cukup, langsung lunas
     * seketika tanpa lewat gateway pembayaran sama sekali.
     */
    public function payWithBalance(Invoice $invoice): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $this->authorizeOwner($invoice);

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Invoice ini sudah lunas.');
        }

        if ($invoice->is_topup) {
            return back()->with('error', 'Invoice isi ulang saldo tidak bisa dibayar pakai saldo.');
        }

        if ((float) $client->balance < (float) $invoice->total) {
            return back()->with('error', 'Saldo Anda tidak cukup untuk membayar invoice ini.');
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'payment_gateway_id' => null,
            'amount' => $invoice->amount,
            'fee' => 0,
            'total' => $invoice->total,
            'currency' => 'IDR',
            'status' => 'initiated',
            'payment_method' => 'Saldo',
        ]);

        $client->adjustBalance(
            -1 * (float) $invoice->total,
            'payment',
            "Bayar invoice {$invoice->invoice_number}",
            $invoice,
        );

        // markAsPaid() menangani update status invoice + memicu seluruh
        // hook yang sama seperti pembayaran lewat gateway (provisioning,
        // perpanjangan, upgrade) — supaya bayar pakai saldo diperlakukan
        // identik dengan metode pembayaran lain, bukan jalur pintas
        // terpisah yang bisa berbeda hasilnya.
        $payment->markAsPaid('Saldo');

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice berhasil dibayar pakai saldo.');
    }
}
