<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class CleanActivity extends Command
{
    protected $signature = 'lumora:clean-activity {--days=30 : Umur minimal catatan yang dihapus}';

    protected $description = 'Hapus catatan aktivitas lama yang sudah dibaca';

    
    public function handle(): int
    {
        ob_start();
        $result = $this->handleJob();
        $output = ob_get_clean();
        echo $output;

        \App\Models\CronJob::recordExecution('lumora:clean-activity', $result === self::SUCCESS, $output);

        return $result;
    }

    private function handleJob(): int
    {
        $days = (int) $this->option('days');

        // Hanya yang SUDAH DIBACA yang dihapus — catatan yang belum dibaca
        // berarti belum sempat ditindaklanjuti admin, jadi dibiarkan.
        $count = ActivityLog::whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("{$count} catatan aktivitas lama dihapus.");

        return self::SUCCESS;
    }
}
