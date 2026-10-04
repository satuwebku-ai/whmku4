<?php

namespace Tests\Feature;

use App\Models\CronJob;
use App\Console\Commands\RunCron;
use App\Services\Billing\BillingReconciliationService;
use App\Services\Hosting\CpanelCronService;
use App\Services\SetupChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CronSchedulerPhase16Test extends TestCase
{
    use RefreshDatabase;

    public function test_builtin_scheduler_contains_billing_reconciliation(): void
    {
        CronJob::syncBuiltIn();

        $this->assertDatabaseHas('cron_jobs', [
            'key' => 'reconcile_billing',
            'command' => 'lumora:reconcile-billing',
            'interval_minutes' => 60,
            'is_enabled' => true,
        ]);
    }

    public function test_every_builtin_job_points_to_a_registered_artisan_command(): void
    {
        CronJob::syncBuiltIn();

        $registeredCommands = array_keys(Artisan::all());

        foreach (CronJob::BUILT_IN as $key => $config) {
            $command = strtok($config['command'], ' ');

            $this->assertContains(
                $command,
                $registeredCommands,
                "Cron job [{$key}] references unregistered Artisan command [{$command}]."
            );

            $this->assertDatabaseHas('cron_jobs', [
                'key' => $key,
                'command' => $config['command'],
                'is_enabled' => true,
            ]);
        }
    }

    public function test_dispatcher_passes_repair_option_as_an_artisan_option(): void
    {
        CronJob::syncBuiltIn();

        $reconciliation = \Mockery::mock(BillingReconciliationService::class);
        $reconciliation->shouldReceive('scan')->once()->andReturn([
            'balance_ledger_mismatch' => 0,
            'paid_payment_invoice_mismatch' => 0,
            'paid_invoice_missing_charge' => 0,
            'paid_topup_missing_credit' => 0,
            'paid_invoice_with_unfinished_order' => 0,
        ]);
        $reconciliation->shouldReceive('repair')->once()->andReturn([
            'invoice_status_repaired' => 0,
            'charge_repaired' => 0,
            'topup_repaired' => 0,
        ]);
        $this->app->instance(BillingReconciliationService::class, $reconciliation);

        $exitCode = Artisan::call('lumora:cron', ['--job' => 'reconcile_billing']);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('cron_jobs', [
            'key' => 'reconcile_billing',
            'last_status' => 'success',
            'run_count' => 1,
        ]);
    }

    public function test_legacy_database_job_is_preserved_but_never_dispatched(): void
    {
        CronJob::syncBuiltIn();
        CronJob::whereIn('key', array_keys(CronJob::BUILT_IN))->update(['is_enabled' => false]);

        $legacy = CronJob::create([
            'key' => 'removed_legacy_job',
            'name' => 'Job lama',
            'description' => 'Data historis',
            'command' => 'command:that-no-longer-exists',
            'interval_minutes' => 60,
            'is_enabled' => true,
            'run_count' => 0,
            'next_run_at' => now()->subMinute(),
        ]);

        $exitCode = Artisan::call('lumora:cron', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('cron_jobs', ['id' => $legacy->id, 'run_count' => 0]);
    }

    public function test_setup_check_ignores_overdue_legacy_cron_jobs(): void
    {
        CronJob::syncBuiltIn();
        CronJob::where('key', 'invoice_reminder')->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addDay(),
        ]);

        CronJob::create([
            'key' => 'removed_legacy_job',
            'name' => 'Job lama',
            'description' => 'Data historis',
            'command' => 'command:that-no-longer-exists',
            'interval_minutes' => 60,
            'is_enabled' => true,
            'run_count' => 1,
            'last_run_at' => now()->subDays(2),
            'next_run_at' => now()->subHours(2),
        ]);

        $cronItem = collect(app(SetupChecklistService::class)->summary()['items'])
            ->firstWhere('key', 'cron');

        $this->assertNotNull($cronItem);
        $this->assertTrue($cronItem['ok']);
        $this->assertNull($cronItem['detail']);
    }

    public function test_run_now_reports_a_busy_lock_instead_of_using_old_status_as_success(): void
    {
        CronJob::syncBuiltIn();
        $job = CronJob::where('key', 'close_inactive_chats')->firstOrFail();
        $job->update(['last_status' => 'failed', 'last_output' => 'old failure', 'run_count' => 3]);

        $lock = Cache::lock("lumora:cron:{$job->key}", 21600);
        $this->assertTrue($lock->get());

        try {
            $response = app(\App\Http\Controllers\Admin\CronController::class)->runNow($job);
        } finally {
            $lock->release();
        }

        $this->assertSame('info', session()->get('_flash.new')[0] ?? null);
        $this->assertSame(3, (int) $job->fresh()->run_count);
        $this->assertSame('failed', $job->fresh()->last_status);
        $this->assertStringContainsString('dilewati', (string) session()->get('info'));
    }

    public function test_trial_expiry_job_is_removed_hidden_and_not_executable(): void
    {
        $legacy = CronJob::create([
            'key' => 'expire_trials',
            'name' => 'Suspend Trial Habis',
            'description' => 'Job lama',
            'command' => 'lumora:expire-trials',
            'interval_minutes' => 60,
            'is_enabled' => true,
            'run_count' => 2,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->assertArrayNotHasKey('expire_trials', CronJob::BUILT_IN);
        $this->assertArrayNotHasKey('lumora:expire-trials', Artisan::all());

        $view = app(\App\Http\Controllers\Admin\CronController::class)
            ->indexBootstrap(app(CpanelCronService::class));
        $jobs = $view->getData()['jobs'];
        $this->assertFalse($jobs->contains(fn (CronJob $job) => $job->key === 'expire_trials'));

        $exitCode = Artisan::call('lumora:cron', ['--job' => 'expire_trials']);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseHas('cron_jobs', ['id' => $legacy->id, 'key' => 'expire_trials']);
    }
}