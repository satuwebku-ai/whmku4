<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\UrlGuard;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Strict-Transport-Security'); // http biasa

        $this->get('https://localhost/up')->assertHeader('Strict-Transport-Security');
    }

    public function test_csp_header_nonce_matches_nonce_printed_in_inline_scripts(): void
    {
        $response = $this->get('/admin/login');
        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($csp);
        $this->assertSame(1, preg_match("/script-src [^;]*'nonce-([A-Za-z0-9_-]+)'/", $csp, $m));
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);

        $html = $response->getContent();
        $this->assertGreaterThan(0, preg_match_all('/<script(?![^>]*\ssrc=)[^>]*>/i', $html, $tags));
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $tag);
        }
    }

    public function test_nonce_differs_between_requests(): void
    {
        $extract = function (): string {
            $csp = $this->get('/admin/login')->headers->get('Content-Security-Policy-Report-Only');
            preg_match("/'nonce-([A-Za-z0-9_-]+)'/", $csp, $m);

            return $m[1];
        };

        $first = $extract();
        $this->refreshApplication();
        $this->assertNotSame($first, $extract());
    }

    public function test_every_inline_script_in_views_uses_the_nonce_directive(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if (preg_match_all('/<script(?![^>]*\ssrc=)(?![^>]*@nonce)[^>]*>/i', $code, $found)) {
                $offenders[] = $file->getPathname().' => '.implode(', ', $found[0]);
            }
        }

        $this->assertSame([], $offenders, "Inline <script> tanpa @nonce:\n".implode("\n", $offenders));
    }

    public function test_inline_event_handlers_are_blocked_by_default_and_rollback_is_configurable(): void
    {
        $default = $this->get('/admin/login')->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("script-src-attr 'none'", $default);

        config(['security.csp_allow_inline_handlers' => true]);
        $rollback = $this->get('/admin/login')->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("script-src-attr 'unsafe-inline'", $rollback);
    }

    public function test_views_contain_no_inline_event_handlers(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            if (preg_match_all('/\son[a-z]+\s*=\s*["\']/i', file_get_contents($file->getPathname()), $found)) {
                $offenders[] = $file->getPathname().' => '.implode(', ', array_map('trim', $found[0]));
            }
        }

        $this->assertSame([], $offenders, "Event handler inline ditemukan:\n".implode("\n", $offenders));
    }

    public function test_every_data_call_action_is_registered_in_lumora_actions(): void
    {
        $calls = [];
        $registered = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            preg_match_all('/data-call="([^"]+)"/', $code, $c);
            preg_match_all('/LumoraActions \|\| \{\}\)\.(\w+)\s*=/', $code, $r);
            $calls = array_merge($calls, $c[1]);
            $registered = array_merge($registered, $r[1]);
        }

        $this->assertNotEmpty($calls);
        $this->assertSame([], array_values(array_diff(array_unique($calls), $registered)));
    }

    public function test_action_dispatcher_is_rendered_by_the_layouts_and_ignores_data_confirm(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('window.LumoraActions', $html);

        $partial = file_get_contents(resource_path('views/partials/csp-actions.blade.php'));
        $this->assertStringNotContainsString("'[data-confirm]'", $partial);
    }

    public function test_csp_report_endpoint_accepts_browser_reports(): void
    {
        $this->postJson('/csp-report', [
            'csp-report' => [
                'document-uri' => 'https://example.test/admin?session=not-logged',
                'blocked-uri' => 'https://cdn.example.test/script.js?token=not-logged',
                'violated-directive' => 'script-src',
            ],
        ])->assertNoContent();
    }

    /** @dataProvider blockedUrls */
    public function test_url_guard_blocks_internal_targets(string $url): void
    {
        $this->assertFalse(UrlGuard::isPublicHttpUrl($url), $url);
    }

    public static function blockedUrls(): array
    {
        return [
            ['http://127.0.0.1/admin'], ['http://localhost/x'], ['http://10.0.0.5/'], ['http://192.168.1.1/'],
            ['http://172.16.0.9/'], ['http://169.254.169.254/latest/meta-data/'], ['http://[::1]/'],
            ['ftp://93.184.216.34/'], ['file:///etc/passwd'], ['https://user:pw@93.184.216.34/'], [''], ['not a url'],
        ];
    }

    public function test_url_guard_allows_public_ip_literals(): void
    {
        $this->assertTrue(UrlGuard::isPublicHttpUrl('https://93.184.216.34/api'));
    }

    public function test_admin_seeder_has_no_hardcoded_credentials_and_never_overwrites(): void
    {
        config(['lumora.admin.email' => 'owner@contoh.test', 'lumora.admin.password' => 'Pa55w0rd-Uji!']);

        $this->seed(AdminSeeder::class);
        $admin = Admin::where('username', 'admin')->firstOrFail();
        $this->assertSame('owner@contoh.test', $admin->email);
        $this->assertTrue(Hash::check('Pa55w0rd-Uji!', $admin->password));

        // Seed ulang dengan nilai lain tidak boleh menimpa akun yang sudah ada.
        config(['lumora.admin.password' => 'lain-lagi']);
        $this->seed(AdminSeeder::class);
        $this->assertTrue(Hash::check('Pa55w0rd-Uji!', $admin->fresh()->password));
        $this->assertSame(1, Admin::where('username', 'admin')->count());
    }

    public function test_seeder_source_contains_no_password_literal(): void
    {
        $src = file_get_contents(database_path('seeders/AdminSeeder.php'));
        $this->assertStringNotContainsString('Hash::make(\'', $src);
    }
}
