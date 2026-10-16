<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Mengompilasi SEMUA view Blade dan memastikan hasil PHP-nya valid.
 *
 * Menangkap kesalahan yang baru muncul saat halaman dibuka (error 500), mis.
 * teks arobase+php di dalam komentar Blade: compiler mengekstrak blok itu
 * SEBELUM membuang komentar, lalu "menelan" kode di bawahnya.
 */
class BladeViewsCompileTest extends TestCase
{
    public function test_every_blade_view_compiles_to_valid_php(): void
    {
        $failures = [];
        $checked = 0;

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $checked++;
            $relative = str_replace(resource_path('views').'/', '', $file->getPathname());

            try {
                token_get_all(Blade::compileString(file_get_contents($file->getPathname())), TOKEN_PARSE);
            } catch (\Throwable $e) {
                $failures[] = "{$relative}: ".$e->getMessage();
            }
        }

        $this->assertGreaterThan(100, $checked, 'Folder view tidak terbaca?');
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
