<?php

namespace Tests\Feature\Billing;

use Tests\TestCase;

class ClientBillingPortalPhase18Test extends TestCase
{
    public function test_client_billing_portal_route_and_controller_are_present(): void
    {
        $routes = file_get_contents(base_path('routes/client.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Client/BillingController.php'));
        $view = file_get_contents(base_path('resources/views/client/billing/index.blade.php'));

        $this->assertStringContainsString("Route::get('billing', [BillingController::class, 'index'])->name('billing');", $routes);
        $this->assertStringContainsString('class BillingController', $controller);
        $this->assertStringContainsString("view('client.billing.index'", $controller);
        $this->assertStringContainsString("route('client.invoices.show'", $view);
        $this->assertStringContainsString("route('client.balance')", $view);
    }
}
