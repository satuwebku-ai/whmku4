<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? KnowledgeBaseCategory::query()->where('slug', $request->input('category'))->firstOrFail()
            : null;

        $articles = KnowledgeBase::published()
            ->with('category')
            ->when($category, fn ($query) => $query->where('knowledge_base_category_id', $category->id))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%' . $request->input('search') . '%'))
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        $categories = KnowledgeBaseCategory::query()->withCount([
            'articles' => fn ($query) => $query->published(),
        ])->orderBy('sort_order')->orderBy('name')->get();

        return view('public.knowledge-base.index', compact('articles', 'categories', 'category'));
    }

    public function show(string $slug): View
    {
        $article = KnowledgeBase::published()
            ->with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        $article->increment('views_count');
        $article->refresh();

        return view('public.knowledge-base.show', compact('article'));
    }
}
