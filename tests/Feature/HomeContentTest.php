<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use App\Support\HomeContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teks Keunggulan & Ajakan beranda disimpan di database (CMS), bukan di kode.
 */
class HomeContentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create([
            'username' => 'cms', 'name' => 'CMS', 'email' => 'cms@contoh.test',
            'password' => bcrypt('Rahasia-123'), 'role' => 'superadmin', 'is_active' => true,
        ]);
    }

    public function test_helper_falls_back_to_theme_defaults_when_nothing_saved(): void
    {
        $fallback = [['icon' => 'fa-bolt', 'title' => 'Bawaan', 'desc' => 'Teks bawaan']];

        $this->assertSame($fallback, HomeContent::benefits($fallback));
        $this->assertSame('Bawaan CTA', HomeContent::cta(['title' => 'Bawaan CTA', 'text' => '', 'primary' => '', 'secondary' => ''])['title']);
    }

    public function test_saved_benefits_keep_icon_from_fallback_by_position(): void
    {
        Setting::put('home_benefits', json_encode([['title' => 'Baru', 'desc' => 'Deskripsi baru']]));

        $items = HomeContent::benefits([['icon' => 'fa-bolt', 'title' => 'Lama', 'desc' => 'x']]);

        $this->assertCount(1, $items);
        $this->assertSame('Baru', $items[0]['title']);
        $this->assertSame('fa-bolt', $items[0]['icon']);
    }

    public function test_admin_saves_texts_and_homepage_shows_them_escaped(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.settings.homepage.content.update'), [
            'benefits' => [
                ['title' => 'Uptime <b>99%</b>', 'desc' => 'Server stabil'],
                ['title' => '', 'desc' => 'baris kosong dibuang'],
            ],
            'cta' => ['title' => 'Ayo mulai sekarang', 'text' => '', 'primary' => '', 'secondary' => ''],
        ])->assertRedirect();

        $this->assertSame('Uptime <b>99%</b>', HomeContent::benefits(HomeContent::DEFAULT_BENEFITS)[0]['title']);
        $this->assertCount(1, HomeContent::benefits(HomeContent::DEFAULT_BENEFITS));

        $cta = HomeContent::cta(HomeContent::DEFAULT_CTA);
        $this->assertSame('Ayo mulai sekarang', $cta['title']);
        $this->assertSame(HomeContent::DEFAULT_CTA['text'], $cta['text']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Ayo mulai sekarang')
            ->assertSee('Uptime &lt;b&gt;99%&lt;/b&gt;', false)
            ->assertDontSee('<b>99%</b>', false);
    }

    public function test_saving_default_texts_or_empty_form_resets_to_theme_defaults(): void
    {
        $this->actingAs($this->admin(), 'admin');

        Setting::put('home_benefits', json_encode([['title' => 'Kustom', 'desc' => 'x']]));
        Setting::put('home_cta', json_encode(['title' => 'Kustom']));

        $this->post(route('admin.settings.homepage.content.update'), [
            'benefits' => HomeContent::DEFAULT_BENEFITS,
            'cta' => HomeContent::DEFAULT_CTA,
        ])->assertRedirect();

        $this->assertSame('', (string) Setting::get('home_benefits'));
        $this->assertSame('', (string) Setting::get('home_cta'));
    }

    public function test_hero_text_and_steps_are_saved_and_reset(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.settings.homepage.content.update'), [
            'hero_text' => 'Hosting lokal tercepat',
            'steps' => [['title' => 'Daftar', 'desc' => 'Buat akun'], ['title' => '', 'desc' => 'dibuang']],
        ])->assertRedirect();

        $this->assertSame('Hosting lokal tercepat', HomeContent::heroText('bawaan'));
        $this->assertSame([['title' => 'Daftar', 'desc' => 'Buat akun']], HomeContent::steps(HomeContent::DEFAULT_STEPS));
        $this->get(route('home'))->assertOk()->assertSee('Hosting lokal tercepat');

        $this->post(route('admin.settings.homepage.content.update'), [
            'hero_text' => '',
            'steps' => HomeContent::DEFAULT_STEPS,
        ])->assertRedirect();

        $this->assertSame('bawaan', HomeContent::heroText('bawaan'));
        $this->assertSame(HomeContent::DEFAULT_STEPS, HomeContent::steps(HomeContent::DEFAULT_STEPS));
    }

    public function test_admin_page_renders_content_form(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.settings.homepage'))
            ->assertOk()
            ->assertSee('Teks Section Beranda')
            ->assertSee('Aktif Otomatis');
    }

    public function test_validation_rejects_too_long_title(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.settings.homepage.content.update'), [
            'benefits' => [['title' => str_repeat('a', 61), 'desc' => '']],
        ])->assertSessionHasErrors('benefits.0.title');
    }
}
