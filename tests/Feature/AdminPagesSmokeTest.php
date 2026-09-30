<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Merender halaman admin sungguhan supaya error Blade/route (seperti @endif
 * nyasar di halaman komisi affiliate) ketahuan sebelum sampai ke produksi.
 */
class AdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'username' => 'smoke', 'name' => 'Smoke', 'email' => 'smoke@contoh.test',
            'password' => bcrypt('Rahasia-123'), 'role' => 'superadmin', 'is_active' => true,
        ]);
    }

    public function test_dashboard_shows_setup_checklist_modal_once_per_login(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $first = $this->get(route('admin.dashboard'));
        $first->assertOk()->assertSee('setupChecklistModal', false)->assertSee('id="toastWrap"', false);

        // Halaman berikutnya di sesi yang sama tidak memunculkan modal lagi.
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('setupChecklistModal', false);

        // ?setup=1 membukanya kembali secara manual.
        $this->get(route('admin.dashboard', ['setup' => 1]))->assertOk()->assertSee('setupChecklistModal', false);
    }

    public function test_toast_style_settings_page_saves_and_flash_toast_uses_saved_colors(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.settings.toast.edit'))->assertOk()->assertSee('Tampilan Notifikasi');

        $payload = [
            'toast_position' => 'bottom-right', 'toast_duration' => 6, 'toast_duration_error' => 10,
            'toast_width' => 400, 'toast_radius' => 8, 'toast_show_icon' => 1,
        ];
        foreach (['success', 'error', 'warning', 'info'] as $t) {
            $payload["toast_{$t}_bg"] = '#123456';
            $payload["toast_{$t}_border"] = '#654321';
            $payload["toast_{$t}_text"] = '#ffffff';
        }

        $this->post(route('admin.settings.toast.update'), $payload)->assertRedirect();

        $page = $this->get(route('admin.settings.toast.edit'));
        $page->assertSee('--lt-success-bg:#123456', false)->assertSee('lt-pos-bottom-right', false);

        $this->post(route('admin.settings.toast.reset'))->assertRedirect();
        $this->get(route('admin.settings.toast.edit'))->assertSee('--lt-success-bg:#ecfdf5', false);
    }

    public function test_toast_rejects_invalid_color(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.settings.toast.update'), ['toast_position' => 'top-right', 'toast_success_bg' => 'red'])
            ->assertSessionHasErrors('toast_success_bg');
    }

    public function test_affiliate_commissions_page_renders(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.affiliate.commissions.index'))->assertOk();
    }

    public function test_sync_migrations_command_is_a_noop_on_fresh_install(): void
    {
        $this->artisan('lumora:sync-migrations')->assertSuccessful();
    }
}
