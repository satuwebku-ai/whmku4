<?php

namespace Tests\Feature;

use App\Services\QueueDrainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueDrainerTest extends TestCase
{
    use RefreshDatabase;

    private function job(int $availableAt, ?int $reservedAt = null): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => $reservedAt,
            'available_at' => $availableAt,
            'created_at' => $availableAt,
        ]);
    }

    public function test_status_counts_pending_and_stuck_jobs(): void
    {
        config(['queue.default' => 'database']);

        $this->job(time());                 // baru, belum tertahan
        $this->job(time() - 1800);          // 30 menit tidak tersentuh
        $this->job(time() - 3600, time());  // sedang diproses, tidak dihitung

        $status = app(QueueDrainer::class)->status();

        $this->assertTrue($status['applicable']);
        $this->assertSame(2, $status['pending']);
        $this->assertSame(1, $status['stuck']);
        $this->assertGreaterThanOrEqual(29, $status['oldest_minutes']);
    }

    public function test_status_is_not_applicable_for_sync_driver(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertFalse(app(QueueDrainer::class)->status()['applicable']);
    }

    public function test_drain_is_skipped_when_disabled_or_not_database(): void
    {
        config(['queue.default' => 'sync']);
        $this->assertFalse(app(QueueDrainer::class)->drain()['ran']);

        config(['queue.default' => 'database', 'queue.drain_in_cron' => false]);
        $this->assertFalse(app(QueueDrainer::class)->drain()['ran']);
    }
}
