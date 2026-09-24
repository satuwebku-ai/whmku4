<?php

namespace Tests\Feature;

use App\Models\CronJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronSchedulerPhase16Test extends TestCase
{
    use RefreshDatabase;

    public function test_builtin_scheduler_contains_billing_reconciliation(): void
    {
        CronJob::syncBuiltIn();

        $this->assertDatabaseHas('cron_jobs', [
            'key' => 'reconcile_billing',
            'command' => 'lumora:reconcile-billing --repair',
            'interval_minutes' => 60,
            'is_enabled' => true,
        ]);

        $this->assertDatabaseMissing('cron_jobs', [
            'key' => 'expire_trials',
        ]);

    }

    public function test_cron_runner_records_a_job_once_when_command_records_itself(): void
    {
        CronJob::syncBuiltIn();

        $this->artisan('lumora:cron', ['--job' => 'close_inactive_chats'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('cron_jobs', [
            'key' => 'close_inactive_chats',
            'last_status' => 'success',
            'run_count' => 1,
        ]);
    }
}
