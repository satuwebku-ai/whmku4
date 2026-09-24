<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentSecurityPhase19Test extends TestCase
{
    use RefreshDatabase;

    public function test_payment_webhook_routes_are_rate_limited_and_driver_constrained(): void
    {
        $route = Route::getRoutes()->getByName('payment.webhook');

        $this->assertNotNull($route);
        $this->assertContains('throttle:120,1', $route->middleware());
        // Laravel 12 compiles whereIn() into the route's regex constraint.
        $this->assertSame('midtrans|xendit|duitku', $route->wheres['driver'] ?? null);
    }

    public function test_payment_gateway_model_hides_secrets(): void
    {
        $gateway = new PaymentGateway([
            'server_key' => 'secret',
            'client_key' => 'secret',
            'callback_token' => 'secret',
        ]);

        $hidden = $gateway->getHidden();
        $this->assertContains('server_key', $hidden);
        $this->assertContains('client_key', $hidden);
        $this->assertContains('callback_token', $hidden);
    }

    public function test_qris_initialization_is_not_a_get_request(): void
    {
        $route = Route::getRoutes()->getByName('client.invoices.qris');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    public function test_payment_proofs_use_private_storage(): void
    {
        $clientController = file_get_contents(
            app_path('Http/Controllers/Client/InvoiceController.php')
        );
        $adminController = file_get_contents(
            app_path('Http/Controllers/Admin/PaymentController.php')
        );

        $this->assertStringContainsString("store('payment-proofs', 'local')", $clientController);
        $this->assertStringContainsString("disk('local')->response", $clientController);
        $this->assertStringContainsString("disk('local')->response", $adminController);
        $this->assertStringNotContainsString("store('payment-proofs', 'public')", $clientController);
    }
}
