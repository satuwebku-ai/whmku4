<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\ProductGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(array $attributes = []): Admin
    {
        return Admin::factory()->superadmin()->create($attributes);
    }

    public function test_notification_feed_returns_unread_activity_for_polling(): void
    {
        $this->actingAs($this->superadmin(), 'admin');

        ActivityLog::record('ticket', 'Klien membalas tiket: Tidak bisa login', 'Tiket #LMR-1', route('admin.tickets'), 'warning');

        $this->get(route('admin.activities.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('tickets_needing_attention', 0)
            ->assertJsonPath('items.0.title', 'Klien membalas tiket: Tidak bisa login')
            ->assertJsonPath('items.0.read', false)
            ->assertJsonPath('items.0.url', route('admin.activities.open', ActivityLog::first()));
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_its_site_path(): void
    {
        $this->actingAs($this->superadmin(), 'admin');
        $activity = ActivityLog::record('ticket', 'Tiket baru', 'Tiket #LMR-2', route('admin.tickets'), 'warning');

        $this->get(route('admin.activities.open', $activity))
            ->assertRedirect(route('admin.tickets'));

        $this->assertNotNull($activity->fresh()->read_at);
    }

    public function test_admin_layout_polls_the_notification_feed_without_reloading(): void
    {
        $this->actingAs($this->superadmin(), 'admin');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('notificationFeed', false)
            ->assertSee('setInterval(refreshFeed, 15000)', false)
            ->assertSee('ticketAttentionCount', false);
    }

    public function test_notification_open_rejects_external_redirects_but_still_marks_read(): void
    {
        $this->actingAs($this->superadmin(), 'admin');
        $activity = ActivityLog::record('ticket', 'Tiket baru', null, 'https://attacker.example/phishing', 'warning');

        $this->get(route('admin.activities.open', $activity))
            ->assertRedirect(route('admin.activities'));

        $this->assertNotNull($activity->fresh()->read_at);
    }

    public function test_backup_restore_requires_the_enabled_admin_two_factor_session(): void
    {
        $admin = $this->superadmin(['two_factor_enabled' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.backups.selective', ['filename' => 'older-backup.zip']))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_product_group_explicitly_uses_the_existing_table_name(): void
    {
        $this->assertSame('product_groups', (new ProductGroup())->getTable());
    }
}