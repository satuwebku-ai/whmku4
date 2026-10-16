<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseCategory;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): View
    {
        $articles = KnowledgeBase::query()
            ->with('category')
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%' . $request->input('search') . '%'))
            ->when(in_array($request->input('status'), ['published', 'draft'], true), fn ($query) => $query->where('is_published', $request->input('status') === 'published'))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.knowledge-base.index', compact('articles'));
    }

    public function create(): View
    {
        return view('admin.knowledge-base.form', [
            'article' => new KnowledgeBase(),
            'categories' => KnowledgeBaseCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title']);
        $data['is_published'] = $request->boolean('is_published');
        KnowledgeBase::create($data);

        return redirect()->route('admin.knowledge-base.index')->with('success', 'Artikel bantuan berhasil dibuat.');
    }

    public function edit(KnowledgeBase $article): View
    {
        return view('admin.knowledge-base.form', [
            'article' => $article,
            'categories' => KnowledgeBaseCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, KnowledgeBase $article): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title'], $article->id);
        $data['is_published'] = $request->boolean('is_published');
        $article->update($data);

        return redirect()->route('admin.knowledge-base.index')->with('success', 'Artikel bantuan berhasil diperbarui.');
    }

    public function destroy(KnowledgeBase $article): RedirectResponse
    {
        $article->delete();

        return redirect()->route('admin.knowledge-base.index')->with('success', 'Artikel bantuan berhasil dihapus.');
    }

    public function toggleStatus(KnowledgeBase $article): RedirectResponse
    {
        $article->update(['is_published' => ! $article->is_published]);

        return back()->with('success', 'Status artikel bantuan diperbarui.');
    }

    public function categories(): View
    {
        $categories = KnowledgeBaseCategory::query()
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.knowledge-base.categories', compact('categories'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validatedCategory($request);
        $data['slug'] = $this->uniqueCategorySlug(($data['slug'] ?? '') ?: $data['name']);
        KnowledgeBaseCategory::create($data);

        return redirect()->route('admin.knowledge-base.categories')->with('success', 'Kategori bantuan berhasil dibuat.');
    }

    public function updateCategory(Request $request, KnowledgeBaseCategory $category): RedirectResponse
    {
        $data = $this->validatedCategory($request, $category);
        $data['slug'] = $this->uniqueCategorySlug(($data['slug'] ?? '') ?: $data['name'], $category->id);
        $category->update($data);

        return redirect()->route('admin.knowledge-base.categories')->with('success', 'Kategori bantuan berhasil diperbarui.');
    }

    public function destroyCategory(KnowledgeBaseCategory $category): RedirectResponse
    {
        // Foreign key knowledge_bases.knowledge_base_category_id uses nullOnDelete().
        $category->delete();

        return redirect()->route('admin.knowledge-base.categories')->with('success', 'Kategori dihapus. Artikel terkait tetap tersimpan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'knowledge_base_category_id' => ['nullable', 'integer', 'exists:knowledge_base_categories,id'],
            'content' => ['required', 'string'],
        ]);
    }

    private function validatedCategory(Request $request, ?KnowledgeBaseCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('knowledge_base_categories', 'slug')->ignore($category?->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        return UniqueSlug::make($value, 'artikel', fn (string $slug) => KnowledgeBase::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists());
    }

    private function uniqueCategorySlug(string $value, ?int $ignoreId = null): string
    {
        return UniqueSlug::make($value, 'kategori', fn (string $slug) => KnowledgeBaseCategory::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists());
    }
}
