<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tutorial di portal client: panduan dari Pusat Bantuan (KnowledgeBase) yang
 * sudah terbit, dibaca tanpa meninggalkan portal. Tidak ada tabel baru --
 * admin mengelolanya di Konten > Knowledge Base.
 */
class TutorialController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? KnowledgeBaseCategory::where('slug', $request->input('category'))->firstOrFail()
            : null;

        $search = trim((string) $request->input('search'));

        $articles = KnowledgeBase::published()
            ->with('category')
            ->when($category, fn ($q) => $q->where('knowledge_base_category_id', $category->id))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderByDesc('updated_at')
            ->paginate(9)
            ->withQueryString();

        $categories = KnowledgeBaseCategory::withCount(['articles' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->articles_count > 0)
            ->values();

        return view('client.tutorials.index', compact('articles', 'categories', 'category', 'search'));
    }

    public function show(string $slug): View
    {
        $article = KnowledgeBase::published()->with('category')->where('slug', $slug)->firstOrFail();

        $article->increment('views_count');
        $article->refresh();

        // Panduan lain di kategori yang sama, agar pembaca bisa lanjut belajar.
        $related = KnowledgeBase::published()
            ->where('id', '!=', $article->id)
            ->when($article->knowledge_base_category_id, fn ($q) => $q->where('knowledge_base_category_id', $article->knowledge_base_category_id))
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        return view('client.tutorials.show', compact('article', 'related'));
    }
}
