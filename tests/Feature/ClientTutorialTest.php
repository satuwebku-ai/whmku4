<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tutorial di portal client: panduan Pusat Bantuan (yang terbit) dibaca di dalam portal. */
class ClientTutorialTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $title, ?KnowledgeBaseCategory $category = null, bool $published = true, string $content = '<p>Isi</p>'): KnowledgeBase
    {
        return KnowledgeBase::create([
            'knowledge_base_category_id' => $category?->id,
            'title' => $title, 'slug' => str($title)->slug()->toString(),
            'content' => $content, 'is_published' => $published,
        ]);
    }

    private function login(): Client
    {
        $client = Client::factory()->create();
        $this->actingAs($client, 'client');

        return $client;
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get(route('client.tutorials'))->assertRedirect(route('client.login'));
        $this->get(route('client.tutorials.show', 'apa-saja'))->assertRedirect(route('client.login'));
    }

    public function test_the_list_shows_only_published_guides(): void
    {
        $this->article('Cara Login cPanel');
        $this->article('Draf Rahasia', published: false);
        $this->login();

        $this->get(route('client.tutorials'))
            ->assertOk()
            ->assertSee('Cara Login cPanel')
            ->assertDontSee('Draf Rahasia');
    }

    public function test_search_and_category_filters_narrow_the_list(): void
    {
        $hosting = KnowledgeBaseCategory::create(['name' => 'Hosting', 'slug' => 'hosting']);
        $domain = KnowledgeBaseCategory::create(['name' => 'Domain', 'slug' => 'domain']);
        $this->article('Login cPanel', $hosting);
        $this->article('Atur Nameserver', $domain);
        $this->login();

        $this->get(route('client.tutorials', ['category' => 'domain']))
            ->assertOk()->assertSee('Atur Nameserver')->assertDontSee('Login cPanel');

        $this->get(route('client.tutorials', ['search' => 'cpanel']))
            ->assertOk()->assertSee('Login cPanel')->assertDontSee('Atur Nameserver');

        $this->get(route('client.tutorials', ['category' => 'tidak-ada']))->assertNotFound();
    }

    public function test_a_percent_sign_in_the_search_is_matched_literally(): void
    {
        $this->article('Diskon 50% Domain');
        $this->article('Panduan Umum');
        $this->login();

        $this->get(route('client.tutorials', ['search' => '%']))
            ->assertOk()->assertSee('Diskon 50% Domain')->assertDontSee('Panduan Umum');
    }

    public function test_reading_a_guide_counts_a_view_and_strips_scripts(): void
    {
        $guide = $this->article('Cara Login', null, true, '<p>Buka <b>cPanel</b></p><script>alert(1)</script>');
        $this->login();

        $this->get(route('client.tutorials.show', $guide->slug))
            ->assertOk()
            ->assertSee('<b>cPanel</b>', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->assertSame(1, (int) $guide->fresh()->views_count);
    }

    public function test_a_draft_cannot_be_read(): void
    {
        $draft = $this->article('Draf Rahasia', published: false);
        $this->login();

        $this->get(route('client.tutorials.show', $draft->slug))->assertNotFound();
    }

    public function test_the_menu_links_to_the_tutorials(): void
    {
        $this->assertContains('client.tutorials', array_column(\App\Support\ClientMenu::items(), 'route'));
    }
}
