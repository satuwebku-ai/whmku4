<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Client;
use App\Models\HostingAccount;
use App\Models\Setting;
use App\Models\Server;
use App\Services\Hosting\CpanelCronService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CronAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpanel_install_fails_closed_when_existing_lines_cannot_be_read(): void
    {
        $this->configureCpanel();
        Http::fake(fn () => Http::response(['errors' => ['access denied']], 200));

        $result = app(CpanelCronService::class)->install();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Tidak bisa memeriksa cron', $result['message']);
        Http::assertSentCount(1);
    }

    public function test_near_prefix_path_does_not_count_as_an_existing_app_cron(): void
    {
        $this->configureCpanel();
        $otherAppPath = base_path() . '-another-app';
        Http::fake(function (Request $request) use ($otherAppPath) {
            if (str_contains($request->url(), '/list_lines')) {
                return Http::response([
                    'data' => [
                        'data' => [[
                            'command' => "cd {$otherAppPath} && /usr/bin/php artisan lumora:cron >> /dev/null 2>&1",
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['data' => ['result' => 1]], 200);
        });

        $result = app(CpanelCronService::class)->install();

        $this->assertTrue($result['success']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/add_line'));
    }

    public function test_reminder_queue_failure_makes_the_command_exit_unsuccessfully(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => 'unpaid',
            'due_date' => today()->toDateString(),
        ]);
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('invoiceReminder')
            ->once()
            ->withArgs(fn (Invoice $sentInvoice) => $sentInvoice->is($invoice))
            ->andReturn('failed');
        $this->app->instance(NotificationService::class, $notifications);

        $exitCode = Artisan::call('lumora:send-reminders');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('1 gagal', Artisan::output());
    }

    public function test_deposit_service_retries_failed_panel_suspend_without_debiting_twice(): void
    {
        $now = now()->startOfMinute();
        $this->travelTo($now);
        config(['app.key' => 'base64:' . base64_encode(str_repeat('A', 32))]);

        $client = Client::factory()->create(['balance' => 5]);
        $server = Server::create([
            'name' => 'WHM test',
            'hostname' => 'whm.example.test',
            'port' => 2087,
            'panel' => 'cpanel',
            'api_username' => 'root',
            'api_token' => 'fake-test-token',
            'verify_ssl' => false,
        ]);
        $account = HostingAccount::factory()->create([
            'client_id' => $client->id,
            'server_id' => $server->id,
            'username' => 'testacct',
            'billing_mode' => 'deposit',
            'hourly_rate' => 10,
            'last_billed_at' => $now->copy()->subHour(),
            'status' => 'active',
        ]);

        $requestCount = 0;
        Http::fake(function (Request $request) use (&$requestCount) {
            $requestCount++;

            return Http::response([
                'metadata' => [
                    'result' => $requestCount === 1 ? 0 : 1,
                    'reason' => $requestCount === 1 ? 'temporary panel error' : 'OK',
                ],
            ], 200);
        });

        $firstExitCode = Artisan::call('lumora:charge-hourly-usage');

        $this->assertSame(1, $firstExitCode);
        $this->assertSame('active', $account->fresh()->status);
        $this->assertStringContainsString('temporary panel error', $account->fresh()->panel_suspend_error);
        $this->assertSame('0.00', (string) $client->fresh()->balance);
        $this->assertDatabaseCount('credits', 1);

        // Same time means no new billed interval: only the failed panel
        // suspend is retried, not the debit.
        $secondExitCode = Artisan::call('lumora:charge-hourly-usage');

        $this->assertSame(0, $secondExitCode);
        $this->assertSame('suspended', $account->fresh()->status);
        $this->assertNull($account->fresh()->panel_suspend_error);
        $this->assertDatabaseCount('credits', 1);
        $this->assertSame(2, $requestCount);
    }

    private function configureCpanel(): void
    {
        Setting::putMany([
            'cpanel_host' => 'https://cpanel.test',
            'cpanel_port' => '2083',
            'cpanel_user' => 'test-user',
            'cpanel_token' => 'fake-test-token',
        ], 'cron');
    }
}