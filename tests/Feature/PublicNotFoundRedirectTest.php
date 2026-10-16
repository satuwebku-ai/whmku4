<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNotFoundRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_slug_on_public_host_redirects_home(): void
    {
        $this->get('http://localhost/halaman-yang-tidak-ada')
            ->assertRedirect(route('home'))
            ->assertStatus(302);
    }

    public function test_unknown_nested_path_on_public_host_redirects_home(): void
    {
        $this->get('http://localhost/foo/bar/baz')->assertRedirect(route('home'));
        $this->get('http://localhost/blog/artikel-tidak-ada')->assertRedirect(route('home'));
    }

    public function test_existing_pages_are_not_redirected(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('blog.index'))->assertOk();
        $this->get(route('announcements.index'))->assertOk();
    }

    public function test_missing_assets_and_technical_paths_stay_404(): void
    {
        $this->get('http://localhost/gambar-hilang.jpg')->assertNotFound();
        $this->get('http://localhost/branding/tidak-ada')->assertNotFound();
    }

    public function test_json_and_non_get_requests_are_not_redirected(): void
    {
        $this->getJson('http://localhost/tidak-ada')->assertNotFound();
        $this->post('http://localhost/tidak-ada')->assertStatus(405);
    }

    public function test_admin_and_member_hosts_keep_their_404(): void
    {
        $this->get('http://admin.localhost/tidak-ada')->assertNotFound();
        $this->get('http://member.localhost/tidak-ada')->assertNotFound();
    }
}
