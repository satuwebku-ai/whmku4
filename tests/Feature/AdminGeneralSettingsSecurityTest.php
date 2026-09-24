<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminGeneralSettingsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_settings_requires_admin_authentication(): void
    {
        $this->get(route('admin.settings.general'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_regular_admin_without_system_module_cannot_open_general_settings(): void
    {
        $admin = Admin::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.general'))
            ->assertForbidden();
    }

    public function test_new_logo_wins_when_remove_checkbox_is_also_checked(): void
    {
        Storage::fake('local');

        $admin = Admin::factory()->superadmin()->create();
        Setting::put('site_logo', 'site_logo_old.png', 'general');
        Storage::disk('local')->put('branding/site_logo_old.png', 'old');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.general.update'), [
                'site_name' => 'Example Hosting',
                'remove_site_logo' => '1',
                'site_logo' => UploadedFile::fake()->image('new-logo.png'),
            ])
            ->assertSessionHasNoErrors();

        $newLogo = Setting::get('site_logo');

        $this->assertNotSame(null, $newLogo);
        $this->assertStringStartsWith('site_logo_', $newLogo);
        Storage::disk('local')->assertMissing('branding/site_logo_old.png');
        Storage::disk('local')->assertExists('branding/' . $newLogo);
    }
}