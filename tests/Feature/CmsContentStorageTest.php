<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\NavMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsContentStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_posts_and_blog_categories_share_one_table_without_mixing_types(): void
    {
        $this->assertTrue(Schema::hasTable('cms_contents'));
        $this->assertFalse(Schema::hasTable('cms_pages'));
        $this->assertFalse(Schema::hasTable('cms_posts'));
        $this->assertFalse(Schema::hasTable('cms_categories'));

        $category = CmsCategory::create([
            'name' => 'Panduan',
            'slug' => 'panduan',
        ]);

        $post = CmsPost::create([
            'cms_category_id' => $category->id,
            'title' => 'Artikel pertama',
            'slug' => 'artikel-pertama',
            'content' => '<p>Isi artikel</p>',
            'status' => 'published',
        ]);

        $page = CmsPage::create([
            'title' => 'Tentang Kami',
            'slug' => 'tentang-kami',
            'content' => '<p>Isi halaman</p>',
        ]);

        $this->assertSame(1, CmsPage::count());
        $this->assertSame(1, CmsPost::count());
        $this->assertSame(1, CmsCategory::count());
        $this->assertSame('Panduan', $post->category->name);
        $this->assertSame(1, $category->posts()->count());

        $this->assertDatabaseHas('cms_contents', [
            'id' => $page->id,
            'type' => 'page',
            'slug' => 'tentang-kami',
        ]);
        $this->assertDatabaseHas('cms_contents', [
            'id' => $post->id,
            'type' => 'post',
            'slug' => 'artikel-pertama',
        ]);
        $this->assertDatabaseHas('cms_contents', [
            'id' => $category->id,
            'type' => 'category',
            'slug' => 'panduan',
        ]);
    }

    public function test_cms_types_can_reuse_a_slug_without_violating_the_type_scoped_unique_key(): void
    {
        CmsPage::create(['title' => 'Hosting', 'slug' => 'hosting']);
        CmsPost::create([
            'title' => 'Hosting',
            'slug' => 'hosting',
            'content' => 'Artikel tentang hosting',
            'status' => 'draft',
        ]);
        CmsCategory::create(['name' => 'Hosting', 'slug' => 'hosting']);

        $this->assertSame(1, CmsPage::where('slug', 'hosting')->count());
        $this->assertSame(1, CmsPost::where('slug', 'hosting')->count());
        $this->assertSame(1, CmsCategory::where('slug', 'hosting')->count());
    }

    public function test_all_cms_models_use_the_shared_table_and_system_slugs_are_reserved(): void
    {
        foreach ([new CmsPage, new CmsPost, new CmsCategory] as $model) {
            $this->assertSame('cms_contents', $model->getTable());
        }

        foreach (['promo', 'lisensi', 'hosting', 'blog'] as $slug) {
            $this->assertTrue(CmsPage::isReservedSlug($slug), $slug);
        }

        $this->assertFalse(CmsPage::isReservedSlug('tentang-kami'));
        $this->assertSame('promo-2', CmsPage::uniqueSlug('Promo'));
    }

    public function test_nav_menu_page_link_points_to_cms_contents_and_is_nulled_on_delete(): void
    {
        $page = CmsPage::create(['title' => 'Tentang', 'slug' => 'tentang', 'content' => '<p>Isi</p>']);
        $menu = NavMenu::create([
            'label' => 'Tentang',
            'type' => 'page',
            'page_id' => $page->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame('Tentang', NavMenu::findOrFail($menu->id)->page->title);

        $page->delete();

        $this->assertNull(NavMenu::findOrFail($menu->id)->page_id);
    }

    public function test_cms_tab_bar_shows_on_every_cms_section_and_marks_the_active_one(): void
    {
        $admin = Admin::create([
            'username' => 'cms-tabs', 'name' => 'CMS tabs', 'email' => 'cms-tabs@example.test',
            'password' => bcrypt('test-password'), 'role' => 'superadmin', 'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');

        foreach ([
            'admin.settings.homepage' => 'Beranda',
            'admin.pages' => 'Halaman',
            'admin.nav-menus' => 'Navigasi',
            'admin.nav-submenus' => 'Navigasi',
            'admin.blog.index' => 'Blog',
            'admin.blog.categories' => 'Blog',
            'admin.knowledge-base.index' => 'Pusat Bantuan',
            'admin.announcements' => 'Promosi',
        ] as $route => $tab) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('aria-label="Bagian CMS"', false)
                ->assertSeeInOrder(['nav-link active', 'aria-current="page"', $tab], false);
        }
    }

    public function test_cms_dashboard_is_the_single_admin_entry_point_for_site_content(): void
    {
        $admin = Admin::create([
            'username' => 'cms-smoke',
            'name' => 'CMS smoke test',
            'email' => 'cms-smoke@example.test',
            'password' => bcrypt('test-password'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.cms.dashboard'))
            ->assertOk()
            ->assertSee('Pengelolaan CMS')
            ->assertSee('Atur beranda')
            ->assertSee('Pusat bantuan')
            ->assertSee('Template notifikasi')
            ->assertSee('Kelola navigasi');
    }
}
