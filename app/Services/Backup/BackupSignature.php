<?php

namespace App\Services\Backup;

use RuntimeException;
use ZipArchive;

/**
 * Authenticated manifest for Lumora backups.
 *
 * Signing key: BACKUP_SIGNING_KEY when set, otherwise APP_KEY (fallback).
 * Verification accepts the current signing key, any key listed in
 * BACKUP_SIGNING_KEY_PREVIOUS (comma separated) and APP_KEY, so rotating the
 * key does not orphan older signed backups as long as the old key is kept in
 * the previous list. Unsigned legacy archives can still be restored explicitly.
 */
class BackupSignature
{
    public const MANIFEST_NAME = 'lumora-manifest.json';

    private const VERSION = 1;

    private const ALGORITHM = 'hmac-sha256-v1';

    private const MAX_MANIFEST_BYTES = 4_194_304;

    /**
     * @param  array<string, string>  $files  Archive path => lowercase SHA-256
     */
    public function createManifest(array $files): string
    {
        if (! isset($files['database.sql'])) {
            throw new RuntimeException('Manifest backup harus mencakup database.sql.');
        }

        ksort($files, SORT_STRING);
        foreach ($files as $name => $digest) {
            if (! $this->isSafeArchiveFileName($name) || ! is_string($digest) || preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
                throw new RuntimeException('Daftar file untuk manifest backup tidak valid.');
            }
        }

        $unsigned = [
            'version' => self::VERSION,
            'algorithm' => self::ALGORITHM,
            'files' => $files,
        ];

        return json_encode($unsigned + [
            'signature' => hash_hmac('sha256', $this->canonicalPayload($files), $this->primarySigningKey()),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function createManifestFromArchive(ZipArchive $zip): string
    {
        $files = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! is_string($name) || $name === self::MANIFEST_NAME) {
                throw new RuntimeException('ZIP berisi nama file yang tidak valid sebelum penandatanganan.');
            }

            if (str_ends_with($name, '/')) {
                if (! $this->isSafeArchiveDirectoryName($name)) {
                    throw new RuntimeException('ZIP berisi folder di luar struktur backup Lumora.');
                }

                continue;
            }

            if (! $this->isSafeArchiveFileName($name) || isset($files[$name])) {
                throw new RuntimeException('ZIP berisi file di luar struktur backup Lumora atau nama file ganda.');
            }

            $digest = $this->hashArchiveFile($zip, $name);
            if ($digest === null) {
                throw new RuntimeException("Checksum file {$name} gagal dibuat dari ZIP.");
            }

            $files[$name] = $digest;
        }

        return $this->createManifest($files);
    }

    /**
     * @return array{status: 'signed'|'unsigned'|'invalid', message: string}
     */
    public function inspectPath(string $path, bool $verifyContents = true): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return $this->invalid('File ZIP tidak valid atau rusak.');
        }

        try {
            return $this->inspectArchive($zip, $verifyContents);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{status: 'signed'|'unsigned'|'invalid', message: string}
     */
    public function inspectArchive(ZipArchive $zip, bool $verifyContents = true): array
    {
        $fileNames = [];
        $manifestCount = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! is_string($name) || $name === '') {
                return $this->invalid('ZIP berisi nama file yang tidak valid.');
            }

            if ($name === self::MANIFEST_NAME) {
                $manifestCount++;

                continue;
            }

            if (str_ends_with($name, '/')) {
                if (! $this->isSafeArchiveDirectoryName($name)) {
                    return $this->invalid('ZIP berisi folder di luar struktur backup Lumora.');
                }

                continue;
            }

            if (! $this->isSafeArchiveFileName($name) || isset($fileNames[$name])) {
                return $this->invalid('ZIP berisi file di luar struktur backup Lumora atau nama file ganda.');
            }

            $fileNames[$name] = true;
        }

        if (! isset($fileNames['database.sql'])) {
            return $this->invalid('File ZIP tidak memuat database.sql.');
        }

        if ($manifestCount === 0) {
            return [
                'status' => 'unsigned',
                'message' => 'Backup lama ini belum memiliki tanda tangan digital.',
            ];
        }

        if ($manifestCount !== 1) {
            return $this->invalid('ZIP memiliki lebih dari satu manifest tanda tangan.');
        }

        $manifestStat = $zip->statName(self::MANIFEST_NAME);
        if (! is_array($manifestStat) || ($manifestStat['size'] ?? self::MAX_MANIFEST_BYTES + 1) > self::MAX_MANIFEST_BYTES) {
            return $this->invalid('Manifest tanda tangan tidak valid atau terlalu besar.');
        }

        $json = $zip->getFromName(self::MANIFEST_NAME);
        if (! is_string($json)) {
            return $this->invalid('Manifest tanda tangan tidak dapat dibaca.');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->invalid('Manifest tanda tangan bukan JSON yang valid.');
        }

        if (! is_array($manifest) || array_is_list($manifest)) {
            return $this->invalid('Struktur manifest tanda tangan tidak valid.');
        }

        $keys = array_keys($manifest);
        sort($keys, SORT_STRING);
        $expectedKeys = ['algorithm', 'files', 'signature', 'version'];
        if ($keys !== $expectedKeys
            || ($manifest['version'] ?? null) !== self::VERSION
            || ($manifest['algorithm'] ?? null) !== self::ALGORITHM
            || ! is_array($manifest['files'] ?? null)
            || array_is_list($manifest['files'])
            || ! is_string($manifest['signature'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/i', $manifest['signature']) !== 1) {
            return $this->invalid('Struktur manifest tanda tangan tidak didukung.');
        }

        $files = $manifest['files'];
        ksort($files, SORT_STRING);

        if (! isset($files['database.sql'])) {
            return $this->invalid('Manifest tidak mencakup database.sql.');
        }

        foreach ($files as $name => $digest) {
            if (! $this->isSafeArchiveFileName($name) || ! is_string($digest) || preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
                return $this->invalid('Manifest memuat nama file atau checksum yang tidak valid.');
            }
        }

        $actualNames = array_keys($fileNames);
        $signedNames = array_keys($files);
        sort($actualNames, SORT_STRING);
        sort($signedNames, SORT_STRING);
        if ($actualNames !== $signedNames) {
            return $this->invalid('Daftar file dalam backup berbeda dari manifest tanda tangan.');
        }

        try {
            $payload = $this->canonicalPayload($files);
            $candidateKeys = $this->verificationKeys();
        } catch (RuntimeException $e) {
            return $this->invalid($e->getMessage());
        }

        $provided = strtolower($manifest['signature']);
        $matched = false;
        foreach ($candidateKeys as $key) {
            if (hash_equals(hash_hmac('sha256', $payload, $key), $provided)) {
                $matched = true;
            }
        }

        if (! $matched) {
            return $this->invalid('Tanda tangan backup tidak valid (atau kunci penandatangan sudah berganti; isi BACKUP_SIGNING_KEY_PREVIOUS dengan kunci lama).');
        }

        if (! $verifyContents) {
            return [
                'status' => 'signed',
                'message' => 'Tanda tangan manifest valid; isi file diverifikasi saat restore.',
            ];
        }

        foreach ($files as $name => $expectedDigest) {
            $actualDigest = $this->hashArchiveFile($zip, $name);
            if ($actualDigest === null || ! hash_equals($expectedDigest, $actualDigest)) {
                return $this->invalid("Isi file {$name} tidak cocok dengan manifest tanda tangan.");
            }
        }

        return [
            'status' => 'signed',
            'message' => 'Tanda tangan backup valid.',
        ];
    }

    /**
     * Extract only Lumora backup paths, without ZipArchive::extractTo() so
     * crafted ZIP entries cannot write outside the temporary restore folder.
     */
    public function extractSafely(ZipArchive $zip, string $destination): void
    {
        if (! is_dir($destination) && ! mkdir($destination, 0700, true) && ! is_dir($destination)) {
            throw new RuntimeException('Folder sementara restore tidak dapat dibuat.');
        }

        $root = realpath($destination);
        if ($root === false) {
            throw new RuntimeException('Folder sementara restore tidak dapat diverifikasi.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! is_string($name) || $name === self::MANIFEST_NAME) {
                continue;
            }

            $isDirectory = str_ends_with($name, '/');
            if ($isDirectory) {
                if (! $this->isSafeArchiveDirectoryName($name)) {
                    throw new RuntimeException('ZIP memuat folder yang tidak diizinkan.');
                }

                $relativePath = rtrim($name, '/');
                $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                if (! is_dir($target) && ! mkdir($target, 0700, true) && ! is_dir($target)) {
                    throw new RuntimeException('Folder dari backup tidak dapat dibuat.');
                }

                $this->assertWithinRoot($root, $target);

                continue;
            }

            if (! $this->isSafeArchiveFileName($name)) {
                throw new RuntimeException('ZIP memuat jalur file yang tidak diizinkan.');
            }

            $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
            $parent = dirname($target);
            if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
                throw new RuntimeException('Folder dari backup tidak dapat dibuat.');
            }

            $this->assertWithinRoot($root, $parent);
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException('ZIP memuat nama file yang ganda.');
            }

            $input = $zip->getStream($name);
            $output = fopen($target, 'xb');
            if ($input === false || $output === false) {
                if (is_resource($input)) {
                    fclose($input);
                }
                if (is_resource($output)) {
                    fclose($output);
                }

                throw new RuntimeException("File {$name} dari backup tidak dapat dibaca.");
            }

            try {
                if (stream_copy_to_stream($input, $output) === false) {
                    throw new RuntimeException("File {$name} dari backup gagal diekstrak.");
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            chmod($target, 0600);
        }
    }

    private function canonicalPayload(array $files): string
    {
        ksort($files, SORT_STRING);

        return json_encode([
            'version' => self::VERSION,
            'algorithm' => self::ALGORITHM,
            'files' => $files,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Key used to sign new backups: dedicated key if configured, else APP_KEY. */
    private function primarySigningKey(): string
    {
        $dedicated = trim((string) config('security.backup_signing_key', ''));

        return $dedicated !== ''
            ? $this->deriveKey($dedicated, 'BACKUP_SIGNING_KEY')
            : $this->deriveKey((string) config('app.key'), 'APP_KEY');
    }

    /**
     * Every key a manifest may legitimately have been signed with.
     *
     * @return list<string>
     */
    private function verificationKeys(): array
    {
        $keys = [$this->primarySigningKey()];

        $previous = (string) config('security.backup_signing_key_previous', '');
        foreach (array_filter(array_map('trim', explode(',', $previous))) as $raw) {
            $keys[] = $this->deriveKey($raw, 'BACKUP_SIGNING_KEY_PREVIOUS');
        }

        // APP_KEY tetap diterima agar backup lama (sebelum kunci khusus dipakai) bisa diverifikasi.
        $appKey = (string) config('app.key');
        if ($appKey !== '') {
            try {
                $keys[] = $this->deriveKey($appKey, 'APP_KEY');
            } catch (RuntimeException) {
                // APP_KEY tidak valid: abaikan sebagai kandidat, kunci lain tetap dicoba.
            }
        }

        return array_values(array_unique($keys));
    }

    private function deriveKey(string $raw, string $label): string
    {
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded === false) {
                throw new RuntimeException("{$label} tidak valid (base64 rusak) untuk backup.");
            }
            $raw = $decoded;
        }

        if (strlen($raw) < 32) {
            throw new RuntimeException("{$label} harus berisi minimal 32 byte sebelum backup bisa ditandatangani.");
        }

        return hash_hmac('sha256', 'lumora-backup-manifest-v1', $raw, true);
    }

    private function hashArchiveFile(ZipArchive $zip, string $name): ?string
    {
        $stream = $zip->getStream($name);
        if ($stream === false) {
            return null;
        }

        $context = hash_init('sha256');
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                fclose($stream);

                return null;
            }
            hash_update($context, $chunk);
        }

        fclose($stream);

        return hash_final($context);
    }

    private function isSafeArchiveFileName(string $name): bool
    {
        if ($name === 'database.sql') {
            return true;
        }

        if (! str_starts_with($name, 'storage-app/')) {
            return false;
        }

        return $this->hasSafePathSegments(substr($name, strlen('storage-app/')));
    }

    private function isSafeArchiveDirectoryName(string $name): bool
    {
        $directory = rtrim($name, '/');
        if ($directory === 'storage-app') {
            return true;
        }

        if (! str_starts_with($directory, 'storage-app/')) {
            return false;
        }

        return $this->hasSafePathSegments(substr($directory, strlen('storage-app/')));
    }

    private function hasSafePathSegments(string $path): bool
    {
        if ($path === '' || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return ! str_starts_with($path, '/');
    }

    private function assertWithinRoot(string $root, string $path): void
    {
        $resolved = realpath($path);
        if ($resolved === false || ($resolved !== $root && ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Jalur ekstraksi backup keluar dari folder sementara.');
        }
    }

    /**
     * @return array{status: 'invalid', message: string}
     */
    private function invalid(string $message): array
    {
        return [
            'status' => 'invalid',
            'message' => $message,
        ];
    }
}
