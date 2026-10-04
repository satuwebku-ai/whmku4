<?php

namespace Tests\Feature\Billing;

use App\Exceptions\Billing\BillingException;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Billing\BillingService;
use App\Services\Billing\TopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Audit sistem saldo: jalur Duitku (callback, refund), bayar pakai saldo,
 * dan pengaman klien. Lihat juga BalanceSystemAuditTest.
 */
class DuitkuAndRefundAuditTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT = 'D0001';
    private const KEY = 'api-key-uji';

    private function gateway(): PaymentGateway
    {
        return PaymentGateway::create([
            'name' => 'Duitku',
            'driver' => 'duitku',
            'mode' => 'sandbox',
            'server_key' => self::KEY,
            'client_key' => self::MERCHANT,
            'is_active' => true,
        ]);
    }

    private function callback(Payment $payment, string $amount, string $resultCode): array
    {
        return [
            'merchantCode' => self::MERCHANT,
            'amount' => $amount,
            'merchantOrderId' => $payment->reference,
            'resultCode' => $resultCode,
            'paymentCode' => 'SP',
            'reference' => 'DUITKU-REF-1',
            'signature' => md5(self::MERCHANT . $amount . $payment->reference . self::KEY),
        ];
    }

    private function paymentFor(PaymentGateway $gateway, string $status, float $total = 100000): Payment
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => $total]);

        return Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'payment_gateway_id' => $gateway->id,
            'payment_method' => 'SP',
            'amount' => $total,
            'total' => $total,
            'status' => $status,
        ]);
    }

    private function superadmin(): Admin
    {
        return Admin::factory()->create(['role' => 'superadmin', 'is_active' => true]);
    }

    // ── Callback Duitku ──────────────────────────────────────────────

    public function test_late_failed_callback_does_not_overwrite_a_paid_payment(): void
    {
        $payment = $this->paymentFor($this->gateway(), 'paid');

        $this->postJson('/payment/webhook/duitku', $this->callback($payment, '100000', '01'))
            ->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_failed_callback_closes_a_running_payment(): void
    {
        $payment = $this->paymentFor($this->gateway(), 'initiated');

        $this->postJson('/payment/webhook/duitku', $this->callback($payment, '100000', '01'))
            ->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_callback_amount_is_compared_in_whole_rupiah(): void
    {
        // Total punya pecahan; Duitku menerima dan mengirim balik rupiah bulat.
        $payment = $this->paymentFor($this->gateway(), 'initiated', 100000.50);

        $this->postJson('/payment/webhook/duitku', $this->callback($payment, '100001', '01'))
            ->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_callback_with_wrong_amount_is_still_rejected(): void
    {
        $payment = $this->paymentFor($this->gateway(), 'initiated');

        $this->postJson('/payment/webhook/duitku', $this->callback($payment, '90000', '00'))
            ->assertStatus(400);

        $this->assertSame('initiated', $payment->fresh()->status);
    }

    public function test_paid_callback_for_closed_payment_is_flagged_not_silent(): void
    {
        $payment = $this->paymentFor($this->gateway(), 'expired');
        $before = ActivityLog::count();

        $this->postJson('/payment/webhook/duitku', $this->callback($payment, '100000', '00'))
            ->assertOk();

        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertGreaterThan($before, ActivityLog::count());
    }

    // ── Refund admin ─────────────────────────────────────────────────

    public function test_admin_refund_of_duitku_topup_pulls_credit_back_once(): void
    {
        $gateway = $this->gateway();
        $client = Client::factory()->create(['balance' => 0]);
        $invoice = app(TopupService::class)->createInvoice($client, 100000);
        $invoice->update(['status' => 'paid']);
        app(TopupService::class)->applyPaidInvoice($invoice->fresh());
        $this->assertSame('100000.00', $client->fresh()->balance);

        $payment = Payment::factory()->paid()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'payment_gateway_id' => $gateway->id,
            'payment_method' => 'SP',
            'amount' => 100000,
            'total' => 100000,
        ]);

        $url = route('admin.payment.refund', $payment);
        $this->actingAs($this->superadmin(), 'admin');

        // Alasan wajib.
        $this->post($url, ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->assertSame('paid', $payment->fresh()->status);

        $this->post($url, ['reason' => 'klien minta pengembalian dana'])->assertSessionHas('success');

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('0.00', $client->fresh()->balance);
        $this->assertSame(1, Credit::where('type', 'topup_reversal')->count());

        // Dikirim ulang: ditolak, tidak menarik dua kali.
        $this->post($url, ['reason' => 'klik ganda tidak boleh'])->assertSessionHas('error');
        $this->assertSame('0.00', $client->fresh()->balance);
        $this->assertSame(1, Credit::where('type', 'topup_reversal')->count());
    }

    public function test_refund_is_refused_for_unpaid_payment(): void
    {
        $payment = $this->paymentFor($this->gateway(), 'initiated');

        $this->actingAs($this->superadmin(), 'admin')
            ->post(route('admin.payment.refund', $payment), ['reason' => 'belum dibayar'])
            ->assertSessionHas('error');

        $this->assertSame('initiated', $payment->fresh()->status);
    }

    public function test_reject_cannot_flip_a_paid_payment_to_failed(): void
    {
        $payment = $this->paymentFor($this->gateway(), 'paid');

        $this->actingAs($this->superadmin(), 'admin')
            ->post(route('admin.payment.reject'), ['payment_id' => $payment->id])
            ->assertSessionHas('error');

        $this->assertSame('paid', $payment->fresh()->status);
    }

    // ── Bayar pakai saldo ────────────────────────────────────────────

    public function test_client_can_pay_invoice_with_balance_through_the_route(): void
    {
        Bus::fake();

        $client = Client::factory()->create(['balance' => 150000]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => 50000]);

        $this->actingAs($client, 'client')
            ->post(route('client.balance.pay', $invoice))
            ->assertRedirect(route('client.invoices.show', $invoice))
            ->assertSessionHas('success');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('100000.00', $client->fresh()->balance);
        $this->assertSame(1, Credit::where('type', 'payment')->count());
    }

    public function test_insufficient_balance_pays_nothing(): void
    {
        $client = Client::factory()->create(['balance' => 10000]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => 50000]);

        $this->expectException(BillingException::class);

        try {
            app(BillingService::class)->payInvoiceWithBalance($invoice, $client);
        } finally {
            $this->assertSame('10000.00', $client->fresh()->balance);
            $this->assertSame('unpaid', $invoice->fresh()->status);
            $this->assertSame(0, Credit::count());
        }
    }

    // ── Pengaman model ───────────────────────────────────────────────

    public function test_balance_cannot_be_mass_assigned(): void
    {
        $client = Client::factory()->create(['balance' => 1000]);

        $client->update(['balance' => 999999, 'name' => 'Nama Baru']);

        $this->assertSame('1000.00', $client->fresh()->balance);
        $this->assertSame('Nama Baru', $client->fresh()->name);

        // Jalur resmi tetap bekerja dan tercatat di ledger.
        $client->adjustBalance(500, 'topup', 'uji');
        $this->assertSame('1500.00', $client->fresh()->balance);
        $this->assertSame(1, Credit::count());
    }

    public function test_topup_is_not_credited_after_invoice_was_refunded_in_between(): void
    {
        $client = Client::factory()->create(['balance' => 0]);
        $invoice = app(TopupService::class)->createInvoice($client, 100000);
        $invoice->update(['status' => 'paid']);

        // Instance usang di tangan pemanggil; status di database sudah berubah.
        $stale = $invoice->fresh();
        Invoice::whereKey($invoice->id)->update(['status' => 'refunded']);

        app(TopupService::class)->applyPaidInvoice($stale);

        $this->assertSame('0.00', $client->fresh()->balance);
        $this->assertSame(0, Credit::count());
    }
}
