<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tabel untuk seluruh konten CMS. Kolom `type` membedakan:
 *   page     = halaman statis (/{slug})
 *   post     = artikel blog
 *   category = kategori blog
 * Slug unik per tipe, jadi halaman dan artikel boleh memakai slug yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_contents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('title')->nullable();
            $table->string('name')->nullable();
            $table->string('slug');
            $table->longText('content')->nullable();

            // ── SEO per halaman ──
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_image')->nullable();
            $table->boolean('noindex')->default(false);

            // ── Halaman ──
            $table->boolean('is_published')->default(true);
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            // ── Artikel ──
            $table->string('excerpt')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('cms_category_id')->nullable()
                ->constrained('cms_contents')
                ->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->timestamps();
            $table->unique(['type', 'slug'], 'cms_contents_type_slug_unique');
            $table->index(['type', 'is_published', 'sort_order'], 'cms_contents_page_listing_index');
            $table->index(['type', 'status', 'published_at'], 'cms_contents_post_visibility_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_contents');
    }
};
