<?php

namespace Tests\Feature;

use App\Models\CmsPost;
use App\Models\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEditorialContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_blog_lists_only_published_posts_and_sanitizes_content(): void
    {
        CmsPost::create([
            'title' => 'Artikel yang sudah terbit',
            'slug' => 'artikel-terbit',
            'excerpt' => 'Ringkasan aman.',
            'content' => '<p>Teks aman</p><script>alert("xss")</script>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        CmsPost::create([
            'title' => 'Artikel draf rahasia',
            'slug' => 'artikel-draf',
            'content' => '<p>Belum untuk publik.</p>',
            'status' => 'draft',
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Artikel yang sudah terbit')
            ->assertDontSee('Artikel draf rahasia');

        $this->get(route('blog.show', 'artikel-terbit'))
            ->assertOk()
            ->assertSee('Teks aman')
            ->assertDontSee('<script>', false)
            ->assertDontSee('alert("xss")', false);
    }

    public function test_public_knowledge_base_hides_drafts_and_counts_article_views(): void
    {
        KnowledgeBase::create([
            'title' => 'Panduan publik',
            'slug' => 'panduan-publik',
            'content' => '<p>Langkah yang aman.</p>',
            'is_published' => true,
        ]);

        KnowledgeBase::create([
            'title' => 'Panduan internal',
            'slug' => 'panduan-internal',
            'content' => 'Jangan tampilkan.',
            'is_published' => false,
        ]);

        $this->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('Panduan publik')
            ->assertDontSee('Panduan internal');

        $this->get(route('knowledge-base.show', 'panduan-publik'))
            ->assertOk()
            ->assertSee('Langkah yang aman.');

        $this->assertDatabaseHas('knowledge_bases', [
            'slug' => 'panduan-publik',
            'views_count' => 1,
        ]);

        // Artikel non-publik tidak tampil; pengunjung dikembalikan ke beranda.
        $this->get(route('knowledge-base.show', 'panduan-internal'))->assertRedirect(route('home'));
    }
}
