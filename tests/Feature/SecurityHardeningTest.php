<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use App\Support\CspPolicy;
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

    public function test_csp_connect_and_img_src_do_not_allow_any_https_host_by_default(): void
    {
        $csp = $this->cspFor('/admin/login');

        $this->assertSame("connect-src 'self'", $this->cspDirective($csp, 'connect-src'));

        foreach (['connect-src', 'img-src'] as $name) {
            $this->assertDoesNotMatchRegularExpression(
                '/(^|\s)(https?|wss?):(\s|$)/',
                $this->cspDirective($csp, $name),
                "{$name} masih membuka skema bebas",
            );
        }

        $this->assertSame("img-src 'self' data: blob:", $this->cspDirective($csp, 'img-src'));
        $this->assertStringNotContainsString('wss:', $csp);
        $this->assertDoesNotMatchRegularExpression('/googletagmanager|facebook|tawk|crisp/', $csp);
    }

    public function test_csp_adds_third_party_hosts_only_when_the_integration_is_enabled(): void
    {
        Setting::put('ga_measurement_id', 'G-TEST12345');
        Setting::put('fb_pixel_id', '123456789');
        Setting::put('livechat_provider', 'tawkto');
        Setting::put('livechat_property_id', 'abc123/default');

        $csp = $this->cspFor('/admin/login');

        $this->assertStringContainsString('https://www.googletagmanager.com', $this->cspDirective($csp, 'script-src'));
        $this->assertStringContainsString('https://connect.facebook.net', $this->cspDirective($csp, 'script-src'));
        $this->assertStringContainsString('https://embed.tawk.to', $this->cspDirective($csp, 'script-src'));
        $this->assertStringContainsString('https://*.google-analytics.com', $this->cspDirective($csp, 'connect-src'));
        $this->assertStringContainsString('wss://*.tawk.to', $this->cspDirective($csp, 'connect-src'));
        $this->assertStringContainsString('https://www.facebook.com', $this->cspDirective($csp, 'img-src'));
        $this->assertStringNotContainsString('crisp', $csp);
    }

    public function test_csp_livechat_hosts_need_a_property_id_like_the_view_does(): void
    {
        Setting::put('livechat_provider', 'crisp'); // tanpa property id: widget tidak dirender

        $this->assertStringNotContainsString('crisp', $this->cspFor('/admin/login'));

        Setting::put('livechat_property_id', 'site-id');

        $this->assertStringContainsString('wss://*.crisp.chat', $this->cspDirective($this->cspFor('/admin/login'), 'connect-src'));
    }

    public function test_csp_extra_hosts_are_configurable_and_cannot_inject_directives(): void
    {
        config([
            'security.csp_extra_img_src' => 'https://images.example.com, https://*.cdn.example.net',
            'security.csp_extra_connect_src' => "https://api.example.com; script-src *\nhttps://ok.example.com",
        ]);

        $csp = $this->cspFor('/admin/login');

        $this->assertStringContainsString('https://images.example.com', $this->cspDirective($csp, 'img-src'));
        $this->assertStringContainsString('https://*.cdn.example.net', $this->cspDirective($csp, 'img-src'));
        $this->assertStringContainsString('https://ok.example.com', $this->cspDirective($csp, 'connect-src'));
        $this->assertStringNotContainsString('script-src *', $csp);
        $this->assertSame(1, substr_count($csp, 'script-src '));

        config(['security.csp_img_allow_any_https' => true]);
        $this->assertMatchesRegularExpression('/(^|\s)https:(\s|$)/', $this->cspDirective($this->cspFor('/admin/login'), 'img-src'));
    }

    public function test_every_external_script_and_stylesheet_host_in_views_is_allowed_by_the_csp(): void
    {
        $scriptHosts = [];
        $styleHosts = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());

            foreach ([
                '#<script\b[^>]*\ssrc=["\']https://([A-Za-z0-9.-]+)#i',   // <script src="https://...">
                '#\.src\s*=\s*["\']https://([A-Za-z0-9.-]+)#i',           // s.src = 'https://...'
                '#["\']https://([A-Za-z0-9.-]+)/[^"\'\s]*(?:\.js|/js)\b#i', // URL skrip yang dilempar sebagai argumen
            ] as $pattern) {
                preg_match_all($pattern, $source, $m);
                array_push($scriptHosts, ...$m[1]);
            }

            preg_match_all('#<link\b[^>]*>#i', $source, $links);
            foreach ($links[0] as $link) {
                if (preg_match('#rel=["\']stylesheet["\']#i', $link) && preg_match('#href=["\']https://([A-Za-z0-9.-]+)#i', $link, $h)) {
                    $styleHosts[] = $h[1];
                }
            }
        }

        $scriptHosts = array_values(array_unique($scriptHosts));
        $styleHosts = array_values(array_unique($styleHosts));
        $this->assertNotEmpty($scriptHosts);
        $this->assertNotEmpty($styleHosts);

        // Semua integrasi aktif; tawk.to dan Crisp tidak bisa aktif bersamaan, jadi digabung.
        $all = ['ga' => true, 'gtm' => true, 'fb_pixel' => true];
        $policies = implode('; ', [
            CspPolicy::build('n', $all + ['livechat' => 'tawkto']),
            CspPolicy::build('n', $all + ['livechat' => 'crisp']),
        ]);
        $scriptSrc = $this->allDirectives($policies, 'script-src');
        $styleSrc = $this->allDirectives($policies, 'style-src');

        foreach ($scriptHosts as $host) {
            $this->assertTrue($this->hostMatches($host, $scriptSrc), "Host skrip {$host} dipakai di view tapi tidak ada di script-src");
        }
        foreach ($styleHosts as $host) {
            $this->assertTrue($this->hostMatches($host, $styleSrc), "Host stylesheet {$host} dipakai di view tapi tidak ada di style-src");
        }
    }

    public function test_browser_side_fetch_calls_only_target_the_application_itself(): void
    {
        // connect-src hanya 'self'; fetch ke host luar akan diblokir saat CSP diberlakukan.
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                '#\b(?:fetch|axios\.[a-z]+)\s*\(\s*[\'"`](?:https?:)?//#i',
                file_get_contents($file->getPathname()),
                "{$file->getPathname()} memanggil fetch/axios ke host eksternal",
            );
        }
    }

    private function cspFor(string $path): string
    {
        return (string) $this->get($path)->headers->get('Content-Security-Policy-Report-Only');
    }

    private function cspDirective(string $csp, string $name): string
    {
        foreach (explode('; ', $csp) as $directive) {
            if ($directive === $name || str_starts_with($directive, $name.' ')) {
                return $directive;
            }
        }

        return '';
    }

    private function allDirectives(string $csp, string $name): string
    {
        return implode(' ', array_filter(array_map(
            fn (string $d) => str_starts_with($d, $name.' ') ? $d : '',
            explode('; ', $csp),
        )));
    }

    private function hostMatches(string $host, string $directiveValue): bool
    {
        foreach (preg_split('/\s+/', $directiveValue) as $token) {
            if (! str_starts_with($token, 'https://')) {
                continue;
            }
            $allowed = substr($token, strlen('https://'));
            if ($allowed === $host || (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1)))) {
                return true;
            }
        }

        return false;
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
