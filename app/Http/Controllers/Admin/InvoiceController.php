<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Billing\BillingException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Billing\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function invoices(Request $request): View
    {
        return $this->renderList($request, null);
    }

    public function unpaid(Request $request): View
    {
        return $this->renderList($request, 'unpaid');
    }

    public function unpaidBootstrap(Request $request): View
    {
        return view('admin.invoices.index', $this->invoiceListData($request, 'unpaid'));
    }

    public function paid(Request $request): View
    {
        return $this->renderList($request, 'paid');
    }

    public function paidBootstrap(Request $request): View
    {
        return view('admin.invoices.index', $this->invoiceListData($request, 'paid'));
    }

    public function overdue(Request $request): View
    {
        return $this->renderList($request, 'overdue');
    }

    public function overdueBootstrap(Request $request): View
    {
        return view('admin.invoices.index', $this->invoiceListData($request, 'overdue'));
    }

    public function cancelled(Request $request): View
    {
        return $this->renderList($request, 'cancelled');
    }

    public function cancelledBootstrap(Request $request): View
    {
        return view('admin.invoices.index', $this->invoiceListData($request, 'cancelled'));
    }

    /**
     * Pratinjau versi Bootstrap -- data & filter sama persis, cuma
     * tampilannya beda. Halaman asli tidak tersentuh.
     */
    public function invoicesBootstrap(Request $request): View
    {
        return view('admin.invoices.index', $this->invoiceListData($request, null));
    }

    private function renderList(Request $request, ?string $status): View
    {
        return view('admin.invoices.index', $this->invoiceListData($request, $status));
    }

    private function invoiceListData(Request $request, ?string $status): array
    {
        $invoices = Invoice::query()
            ->with('client')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q) => $q->where('invoice_number', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return ['invoices' => $invoices, 'activeStatus' => $status];
    }

    public function details(Invoice $invoice): View
    {
        $invoice->load(['client', 'order', 'items.order']);

        return view('admin.invoices.details', compact('invoice'));
    }

    /**
     * Route ini sudah ada di routes/admin.php sejak lama tapi method-nya
     * belum pernah dibuat -- klik tombol download PDF di panel admin
     * selalu 404. Memakai template PDF yang SAMA dengan
     * Client\InvoiceController::downloadPdf() (client.invoices.pdf)
     * supaya invoice yang dilihat admin & klien identik persis, bukan
     * dua template yang bisa diam-diam berbeda.
     */
    public function pdf(Invoice $invoice): Response
    {
        $invoice->load(['order', 'items.order', 'client']);

        $pdf = Pdf::loadView('client.invoices.pdf', compact('invoice'))->setPaper('a4');

        return $pdf->download("Invoice-{$invoice->invoice_number}.pdf");
    }

    public function detailsBootstrap(Invoice $invoice): View
    {
        $invoice->load(['client', 'order', 'items.order']);

        return view('admin.invoices.details', compact('invoice'));
    }

    public function create(): View
    {
        $clients = Client::orderBy('name')->get();
        $orders = Order::orderBy('order_number')->get();

        return view('admin.invoices.form', ['invoice' => new Invoice(), 'clients' => $clients, 'orders' => $orders]);
    }

    public function createBootstrap(): View
    {
        $clients = Client::orderBy('name')->get();
        $orders = Order::orderBy('order_number')->get();

        return view('admin.invoices.form', ['invoice' => new Invoice(), 'clients' => $clients, 'orders' => $orders]);
    }

    public function store(Request $request, InvoiceService $invoices): RedirectResponse
    {
        $data = $this->validated($request);

        $invoices->create($data);

        return redirect()->route('admin.invoices')->with('success', 'Invoice berhasil dibuat.');
    }

    public function edit(Invoice $invoice): View
    {
        $clients = Client::orderBy('name')->get();
        $orders = Order::orderBy('order_number')->get();

        return view('admin.invoices.form', ['invoice' => $invoice, 'clients' => $clients, 'orders' => $orders]);
    }

    public function editBootstrap(Invoice $invoice): View
    {
        $clients = Client::orderBy('name')->get();
        $orders = Order::orderBy('order_number')->get();

        return view('admin.invoices.form', ['invoice' => $invoice, 'clients' => $clients, 'orders' => $orders]);
    }

    public function update(Request $request, Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['status'] === 'paid' && ! $invoice->paid_at) {
            $data['paid_at'] = now();
        }

        $invoices->update($invoice, $data);

        return redirect()->route('admin.invoices')->with('success', 'Invoice berhasil diperbarui.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $invoice->delete();

        return redirect()->route('admin.invoices')->with('success', 'Invoice berhasil dihapus.');
    }

    /**
     * Tandai invoice lunas.
     */
    public function markPaid(Request $request, InvoiceService $invoices): RedirectResponse
    {
        try {
            $invoice = $invoices->findOrFail((int) $request->input('invoice_id'));
            $invoices->markPaid($invoice, $request->input('payment_method'));
        } catch (BillingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} ditandai lunas.");
    }

    /**
     * Tandai invoice belum lunas (batalkan status lunas).
     */
    public function markUnpaid(Request $request, InvoiceService $invoices): RedirectResponse
    {
        try {
            $invoice = $invoices->findOrFail((int) $request->input('invoice_id'));
            $invoices->markUnpaid($invoice);
        } catch (BillingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} ditandai belum lunas.");
    }

    /**
     * Batalkan invoice. Pelepasan renewal_invoice_id di domain/hosting
     * terkait (supaya layanan tidak macet permanen) ada di
     * InvoiceService::cancel(), bukan di sini lagi.
     */
    public function cancel(Request $request, InvoiceService $invoices): RedirectResponse
    {
        try {
            $invoice = $invoices->findOrFail((int) $request->input('invoice_id'));
            $invoices->cancel($invoice);
        } catch (BillingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Invoice {$invoice->invoice_number} dibatalkan.");
    }

    /**
     * Simpan catatan invoice (memakai kolom "notes" yang sudah ada sejak Fase 2).
     */
    public function invoiceNotes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);
        $invoice->update(['notes' => $data['notes']]);

        return back()->with('success', 'Catatan invoice berhasil disimpan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id'      => ['required', 'exists:clients,id'],
            'order_id'       => ['nullable', 'exists:orders,id'],
            'amount'         => ['required', 'numeric', 'min:0'],
            'tax'            => ['nullable', 'numeric', 'min:0'],
            'discount'      => ['nullable', 'numeric', 'min:0'],
            'tax_id'        => ['nullable', 'exists:taxes,id'],
            'status'         => ['required', 'in:unpaid,paid,overdue,cancelled,refunded'],
            'issue_date'     => ['required', 'date'],
            'due_date'       => ['required', 'date', 'after_or_equal:issue_date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string'],
        ]);
    }
}
