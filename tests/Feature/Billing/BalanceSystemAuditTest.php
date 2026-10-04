<?php

namespace Tests\Feature\Billing;

use App\Exceptions\Billing\BillingException;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Payment;
use App\Services\Billing\BillingReconciliationService;
use App\Services\Billing\RefundService;
use App\Services\Billing\TopupService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSystemAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_uses_cents_without_float_drift(): void
    {
        $this->assertSame(30, Money::cents(0.1 + 0.2));
        $this->assertSame('0.30', Money::fromCents(Money::cents(0.1 + 0.2)));
        $this->assertSame('-12.50', Money::fromCents(-1250));
        $this->assertTrue(Money::gte('100.00', 99.999));
    }

    public function test_adjust_balance_rejects_negative_result_unless_allowed(): void
    {
        $client = Client::factory()->create(['balance' => 50000]);

        try {
            $client->adjustBalance(-60000, 'admin_adjustment', 'uji');
            $this->fail('Harus melempar BillingException');
        } catch (BillingException) {
            $this->assertSame('50000.00', $client->fresh()->balance);
            $this->assertSame(0, Credit::count());
        }

        $credit = $client->adjustBalance(-60000, 'topup_reversal', 'uji', null, null, null, allowNegative: true);
        $this->assertSame('-10000.00', $credit->balance_after);
        $this->assertSame('-10000.00', $client->balance, 'instance pemanggil ikut tersinkron');
    }

    public function test_adjust_balance_is_idempotent_and_rejects_unknown_type(): void
    {
        $client = Client::factory()->create(['balance' => 0]);

        $a = $client->adjustBalance(25000, 'topup', 'a', null, null, 'k-1');
        $b = $client->adjustBalance(25000, 'topup', 'a', null, null, 'k-1');

        $this->assertSame($a->id, $b->id);
        $this->assertSame('25000.00', $client->fresh()->balance);

        $this->expectException(\InvalidArgumentException::class);
        $client->adjustBalance(1000, 'bukan_jenis', 'x');
    }

    public function test_gateway_topup_refund_pulls_credit_back_and_can_go_negative(): void
    {
        $client = Client::factory()->create(['balance' => 0]);
        $invoice = app(TopupService::class)->createInvoice($client, 100000);
        $invoice->update(['status' => 'paid']);
        app(TopupService::class)->applyPaidInvoice($invoice->fresh());
        $this->assertSame('100000.00', $client->fresh()->balance);

        // Sebagian saldo sudah terpakai.
        $client->fresh()->adjustBalance(-70000, 'usage_charge', 'pakai');

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'status' => 'refunded', 'payment_method' => 'QRIS',
            'amount' => 100000, 'total' => 100000,
        ]);

        app(RefundService::class)->apply($payment);
        $this->assertSame('-70000.00', $client->fresh()->balance);
        $this->assertSame(1, Credit::where('type', 'topup_reversal')->count());

        // Diulang (webhook ganda) tidak menarik dua kali.
        app(RefundService::class)->apply($payment->fresh());
        $this->assertSame('-70000.00', $client->fresh()->balance);
        $this->assertSame(1, Credit::where('type', 'topup_reversal')->count());
    }

    public function test_refund_before_credit_applied_changes_nothing(): void
    {
        $client = Client::factory()->create(['balance' => 0]);
        $invoice = app(TopupService::class)->createInvoice($client, 100000);

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'status' => 'refunded', 'payment_method' => 'QRIS',
            'amount' => 100000, 'total' => 100000,
        ]);

        app(RefundService::class)->apply($payment);

        $this->assertSame('0.00', $client->fresh()->balance);
        $this->assertSame(0, Credit::count());

        // Dan top-up tidak bisa dikredit belakangan karena invoice refunded.
        app(TopupService::class)->applyPaidInvoice($invoice->fresh());
        $this->assertSame(0, Credit::count());
    }

    private function adminOf(string $role): Admin
    {
        return Admin::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_admin_adjust_requires_reason_blocks_negative_result_and_is_idempotent(): void
    {
        $admin = $this->adminOf('superadmin');
        $client = Client::factory()->create(['balance' => 50000]);
        $url = route('admin.client.balance.adjust', $client);

        // Hasil minus ditolak.
        $this->actingAs($admin, 'admin')
            ->post($url, ['amount' => -60000, 'description' => 'koreksi salah', 'token' => 'tok-1'])
            ->assertSessionHas('error');
        $this->assertSame('50000.00', $client->fresh()->balance);

        // Alasan terlalu pendek ditolak.
        $this->post($url, ['amount' => 1000, 'description' => 'x', 'token' => 'tok-2'])->assertSessionHasErrors('description');

        // Dobel kirim dengan token sama hanya diproses sekali.
        $payload = ['amount' => 20000, 'description' => 'kompensasi gangguan', 'token' => 'tok-3'];
        $this->post($url, $payload)->assertSessionHas('success');
        $this->post($url, $payload)->assertSessionHas('success');
        $this->assertSame('70000.00', $client->fresh()->balance);
        $this->assertSame(1, Credit::where('type', 'admin_adjustment')->count());
    }

    public function test_non_superadmin_is_capped_and_hard_limit_applies(): void
    {
        $client = Client::factory()->create(['balance' => 0]);
        $url = route('admin.client.balance.adjust', $client);

        $this->actingAs($this->adminOf('admin'), 'admin')
            ->post($url, ['amount' => 2_000_000, 'description' => 'terlalu besar', 'token' => 'a1'])
            ->assertSessionHas('error');
        $this->assertSame('0.00', $client->fresh()->balance);

        $this->actingAs($this->adminOf('superadmin'), 'admin')
            ->post($url, ['amount' => 60_000_000, 'description' => 'melebihi batas keras', 'token' => 'a2'])
            ->assertSessionHas('error');
        $this->assertSame('0.00', $client->fresh()->balance);

        $this->post($url, ['amount' => 2_000_000, 'description' => 'oleh superadmin', 'token' => 'a3'])
            ->assertSessionHas('success');
        $this->assertSame('2000000.00', $client->fresh()->balance);
    }

    public function test_reconciliation_flags_balance_that_differs_from_ledger(): void
    {
        $ok = Client::factory()->create(['balance' => 0]);
        $ok->adjustBalance(10000, 'topup', 'ok');

        $bad = Client::factory()->create(['balance' => 0]);
        $bad->adjustBalance(10000, 'topup', 'awal');
        $bad->newQuery()->whereKey($bad->id)->update(['balance' => 99000]); // diubah tanpa jejak

        $rows = app(BillingReconciliationService::class)->balanceMismatches();

        $this->assertCount(1, $rows);
        $this->assertSame($bad->id, $rows[0]['client_id']);
        $this->assertSame('99000.00', $rows[0]['balance']);
        $this->assertSame('10000.00', $rows[0]['ledger']);
        $this->assertSame(1, app(BillingReconciliationService::class)->scan()['balance_ledger_mismatch']);
    }

    public function test_credit_type_label_covers_new_types(): void
    {
        $c = new Credit(['type' => 'usage_charge']);
        $this->assertSame('Pemakaian Layanan', $c->type_label);
        $this->assertSame('Pembatalan Isi Ulang', (new Credit(['type' => 'topup_reversal']))->type_label);
    }
}
