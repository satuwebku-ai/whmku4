<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\Backup\BackupSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BackupSignatureTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_signed_backup_manifest_is_validated_against_every_archive_file(): void
    {
        $path = $this->createBackupZip([
            'database.sql' => 'CREATE TABLE example (id INT);',
            'storage-app/uploads/receipt.txt' => 'receipt content',
        ], true);

        $this->assertSame('signed', app(BackupSignature::class)->inspectPath($path)['status']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertTrue($zip->deleteName('database.sql'));
        $this->assertTrue($zip->addFromString('database.sql', 'ALTERED SQL'));
        $zip->close();

        $this->assertSame('signed', app(BackupSignature::class)->inspectPath($path, false)['status']);
        $this->assertSame('invalid', app(BackupSignature::class)->inspectPath($path)['status']);
    }

    public function test_manifest_can_be_created_from_the_final_zip_contents(): void
    {
        $path = $this->createBackupZip([
            'database.sql' => 'CREATE TABLE example (id INT);',
            'storage-app/uploads/receipt.txt' => 'receipt content',
        ], false);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $manifest = app(BackupSignature::class)->createManifestFromArchive($zip);
        $this->assertTrue($zip->addFromString(BackupSignature::MANIFEST_NAME, $manifest));
        $this->assertTrue($zip->close());

        $this->assertSame('signed', app(BackupSignature::class)->inspectPath($path)['status']);
    }

    public function test_backup_without_manifest_is_recognized_as_legacy(): void
    {
        $path = $this->createBackupZip([
            'database.sql' => 'CREATE TABLE example (id INT);',
        ], false);

        $this->assertSame('unsigned', app(BackupSignature::class)->inspectPath($path)['status']);
    }

    public function test_backup_with_path_traversal_entry_is_invalid(): void
    {
        $path = $this->createBackupZip([
            'database.sql' => 'CREATE TABLE example (id INT);',
            'storage-app/../../outside.txt' => 'not safe',
        ], false);

        $this->assertSame('invalid', app(BackupSignature::class)->inspectPath($path)['status']);
    }

    public function test_noninteractive_restore_requires_explicit_legacy_backup_confirmation(): void
    {
        $path = $this->createBackupZip([
            'database.sql' => 'CREATE TABLE example (id INT);',
        ], false);

        $this->artisan('lumora:restore', [
            'file' => $path,
            '--force' => true,
        ])->assertExitCode(1);
    }

    public function test_admin_sees_legacy_warning_and_must_confirm_before_restore(): void
    {
        $filename = $this->copyBackupToStorage([
            'database.sql' => 'CREATE TABLE example (id INT);',
        ], false);
        $this->actingAs(Admin::factory()->superadmin()->create(), 'admin');

        $this->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Backup lama — tanpa tanda tangan')
            ->assertSee('confirm_unsigned', false);

        $this->post(route('admin.backups.restore', ['filename' => $filename]))
            ->assertSessionHas('error', 'Backup lama tanpa tanda tangan. Centang konfirmasi restore backup lama hanya jika sumber file dapat dipercaya.');
    }

    public function test_dedicated_signing_key_signs_new_backups_and_app_key_backups_still_verify(): void
    {
        $files = ['database.sql' => 'CREATE TABLE example (id INT);'];

        // Ditandatangani dengan APP_KEY (belum ada kunci khusus).
        $legacySigned = $this->createBackupZip($files, true);

        config(['security.backup_signing_key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
        $dedicatedSigned = $this->createBackupZip($files, true);

        $this->assertSame('signed', app(BackupSignature::class)->inspectPath($dedicatedSigned)['status']);
        // APP_KEY tetap diterima sebagai kandidat verifikasi.
        $this->assertSame('signed', app(BackupSignature::class)->inspectPath($legacySigned)['status']);
    }

    public function test_rotated_keys_require_previous_key_to_verify_old_backups(): void
    {
        $oldKey = 'base64:'.base64_encode(str_repeat('a', 32));
        $newKey = 'base64:'.base64_encode(str_repeat('b', 32));
        $files = ['database.sql' => 'CREATE TABLE example (id INT);'];

        config(['security.backup_signing_key' => $oldKey]);
        $oldBackup = $this->createBackupZip($files, true);

        // Rotasi kunci; APP_KEY juga berbeda dari yang dipakai menandatangani.
        config([
            'security.backup_signing_key' => $newKey,
            'app.key' => 'base64:'.base64_encode(str_repeat('z', 32)),
        ]);
        $this->assertSame('invalid', app(BackupSignature::class)->inspectPath($oldBackup)['status']);

        config(['security.backup_signing_key_previous' => $oldKey]);
        $this->assertSame('signed', app(BackupSignature::class)->inspectPath($oldBackup)['status']);
    }

    public function test_short_signing_key_is_rejected(): void
    {
        config(['security.backup_signing_key' => 'terlalu-pendek']);

        $this->expectException(\RuntimeException::class);
        app(BackupSignature::class)->createManifest([
            'database.sql' => hash('sha256', 'x'),
        ]);
    }

    private function createBackupZip(array $files, bool $signed): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lumora-backup-test-');
        if ($path === false) {
            $this->fail('Could not create a temporary ZIP path.');
        }

        unlink($path);
        $this->temporaryFiles[] = $path;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE));

        $digests = [];
        foreach ($files as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
            $digests[$name] = hash('sha256', $contents);
        }

        if ($signed) {
            $manifest = app(BackupSignature::class)->createManifest($digests);
            $this->assertTrue($zip->addFromString(BackupSignature::MANIFEST_NAME, $manifest));
        }

        $this->assertTrue($zip->close());

        return $path;
    }

    private function copyBackupToStorage(array $files, bool $signed): string
    {
        $source = $this->createBackupZip($files, $signed);
        $directory = storage_path('app/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $filename = 'lumora-backup_'.now()->format('Y-m-d_H-i-s').'_'.random_int(1000, 9999).'.zip';
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;
        $this->assertTrue(copy($source, $destination));
        $this->temporaryFiles[] = $destination;

        return $filename;
    }
}
