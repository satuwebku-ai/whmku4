<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('category')
            ? CmsCategory::query()->where('slug', $request->input('category'))->firstOrFail()
            : null;

        $posts = CmsPost::published()
            ->with(['category', 'author'])
            ->when($category, fn ($query) => $query->where('cms_category_id', $category->id))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%' . $request->input('search') . '%'))
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->paginate(9)
            ->withQueryString();

        $categories = CmsCategory::query()->withCount([
            'posts' => fn ($query) => $query->published(),
        ])->orderBy('name')->get();

        return view('public.blog.index', compact('posts', 'categories', 'category'));
    }

    public function show(string $slug): View
    {
        $post = CmsPost::published()
            ->with(['category', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.blog.show', compact('post'));
    }
}
