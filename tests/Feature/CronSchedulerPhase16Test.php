<?php

namespace Tests\Feature;

use App\Models\CronJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
}
