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
    }
}
