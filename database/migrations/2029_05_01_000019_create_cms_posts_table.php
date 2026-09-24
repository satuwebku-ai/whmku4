<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur blog sepenuhnya baru -- CmsPage (sebelumnya Page) untuk
     * halaman statis (Syarat & Ketentuan, dst), CmsPost untuk artikel
     * blog/berita yang tampil berurutan waktu dengan kategori & penulis.
     */
    public function up(): void
    {
        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt')->nullable();
            $table->longText('content');
            $table->string('cover_image')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_posts');
    }
};
