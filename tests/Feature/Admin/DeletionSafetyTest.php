<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Billing\DeletionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mengunci aturan hapus di panel admin: record keuangan tidak boleh hilang
 * diam-diam lewat cascade/null foreign key, dan layanan yang masih hidup
 * tidak boleh dihapus dari catatan.
 */
class DeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->superadmin()->create();
    }

    private function order(Client $client, string $status = 'pending_payment'): Order
    {
        return Order::create([
            'client_id' => $client->id,
            'product_name' => 'Cloud Hosting - Pro',
            'order_type' => 'hosting',
            'amount' => 100000,
            'status' => $status,
        ]);
    }

    public function test_paid_invoice_cannot_be_deleted(): void
    {
        $invoice = Invoice::factory()->paid()->create();

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.invoice.delete', $invoice))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($invoice);
    }

    public function test_open_invoice_must_be_cancelled_before_it_can_be_deleted(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'unpaid']);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.invoice.delete', $invoice))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($invoice);
    }

    public function test_cancelled_invoice_is_soft_deleted_and_can_be_restored(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'cancelled']);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.invoice.delete', $invoice))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($invoice);

        $this->artisan('records:restore', ['type' => 'invoice', 'id' => $invoice->id])->assertSuccessful();

        $this->assertNotSoftDeleted($invoice);
    }

    public function test_deleting_a_cancelled_invoice_keeps_its_items(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'cancelled']);
        $invoice->items()->create(['description' => 'Hosting', 'amount' => 100000]);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.invoice.delete', $invoice));

        $this->assertDatabaseCount('invoice_items', 1);
    }

    public function test_order_with_open_invoice_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $order = $this->order($client);
        Invoice::factory()->create(['client_id' => $client->id, 'order_id' => $order->id, 'status' => 'unpaid']);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.order.delete', $order))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($order);
    }

    public function test_completed_order_cannot_be_deleted(): void
    {
        $order = $this->order(Client::factory()->create(), 'completed');

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.order.delete', $order))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($order);
    }

    public function test_cancelled_order_without_invoices_is_soft_deleted(): void
    {
        $order = $this->order(Client::factory()->create(), 'cancelled');

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.order.delete', $order))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($order);
    }

    public function test_paid_and_pending_payments_cannot_be_deleted_but_failed_can(): void
    {
        $admin = $this->admin();
        $paid = Payment::factory()->paid()->create();
        $pending = Payment::factory()->create(['status' => 'pending']);
        $failed = Payment::factory()->create(['status' => 'failed']);

        $this->actingAs($admin, 'admin')->delete(route('admin.payment.delete', $paid))->assertSessionHas('error');
        $this->actingAs($admin, 'admin')->delete(route('admin.payment.delete', $pending))->assertSessionHas('error');
        $this->actingAs($admin, 'admin')->delete(route('admin.payment.delete', $failed))->assertSessionHas('success');

        $this->assertNotSoftDeleted($paid);
        $this->assertNotSoftDeleted($pending);
        $this->assertSoftDeleted($failed);
    }

    public function test_active_domain_cannot_be_deleted(): void
    {
        $domain = Domain::create([
            'client_id' => Client::factory()->create()->id,
            'domain_name' => 'contoh-aktif.com',
            'status' => 'active',
            'provision_status' => 'registered',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.domain.delete', $domain))
            ->assertSessionHas('error');

        $this->assertModelExists($domain);
    }

    public function test_cancelled_domain_can_be_deleted_and_its_order_is_untouched(): void
    {
        $client = Client::factory()->create();
        $order = $this->order($client, 'cancelled');
        $domain = Domain::create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'domain_name' => 'contoh-batal.com',
            'status' => 'cancelled',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.domain.delete', $domain))
            ->assertSessionHas('success');

        $this->assertModelMissing($domain);
        $this->assertNotSoftDeleted($order);
    }

    public function test_domain_with_open_renewal_invoice_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid']);
        $domain = Domain::create([
            'client_id' => $client->id,
            'domain_name' => 'contoh-renewal.com',
            'status' => 'expired',
            'renewal_invoice_id' => $invoice->id,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.domain.delete', $domain))
            ->assertSessionHas('error');

        $this->assertModelExists($domain);
    }

    public function test_active_hosting_cannot_be_deleted_but_terminated_can(): void
    {
        $admin = $this->admin();
        $active = HostingAccount::factory()->active()->create();
        $terminated = HostingAccount::factory()->create(['status' => 'terminated']);

        $this->actingAs($admin, 'admin')->delete(route('admin.hosting-account.delete', $active))->assertSessionHas('error');
        $this->actingAs($admin, 'admin')->delete(route('admin.hosting-account.delete', $terminated))->assertSessionHas('success');

        $this->assertModelExists($active);
        $this->assertModelMissing($terminated);
    }

    public function test_client_with_invoices_cannot_be_deleted_but_empty_client_can(): void
    {
        $admin = $this->admin();
        $withInvoice = Client::factory()->create();
        Invoice::factory()->create(['client_id' => $withInvoice->id, 'status' => 'cancelled']);
        $empty = Client::factory()->create();

        $this->actingAs($admin, 'admin')->delete(route('admin.client.delete', $withInvoice))->assertSessionHas('error');
        $this->actingAs($admin, 'admin')->delete(route('admin.client.delete', $empty))->assertSessionHas('success');

        $this->assertModelExists($withInvoice);
        $this->assertModelMissing($empty);
    }

    public function test_guard_reports_reason_for_client_with_balance(): void
    {
        $client = Client::factory()->create(['balance' => 50000]);

        $this->assertNotNull(app(DeletionGuard::class)->forClient($client));
    }

    public function test_soft_deleted_invoice_is_rejected_by_payment_validation(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'cancelled']);
        $invoice->delete();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.payment.add'), ['invoice_id' => $invoice->id])
            ->assertSessionHasErrors('invoice_id');
    }
}
