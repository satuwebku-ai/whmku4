<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = CmsPost::query()
            ->with(['category', 'author'])
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%' . $request->input('search') . '%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.blog.index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin.blog.form', [
            'post' => new CmsPost(),
            'categories' => CmsCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title']);
        $data['admin_id'] = auth('admin')->id();
        $this->setPublicationDate($data);

        CmsPost::create($data);

        return redirect()->route('admin.blog.index')->with('success', 'Artikel blog berhasil dibuat.');
    }

    public function edit(CmsPost $post): View
    {
        return view('admin.blog.form', [
            'post' => $post,
            'categories' => CmsCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CmsPost $post): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title'], $post->id);
        $this->setPublicationDate($data, $post);
        $post->update($data);

        return redirect()->route('admin.blog.index')->with('success', 'Artikel blog berhasil diperbarui.');
    }

    public function destroy(CmsPost $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.blog.index')->with('success', 'Artikel blog berhasil dihapus.');
    }

    public function toggleStatus(CmsPost $post): RedirectResponse
    {
        if ($post->status === 'published') {
            $post->update(['status' => 'draft']);
        } else {
            $post->update([
                'status' => 'published',
                'published_at' => $post->published_at ?? now(),
            ]);
        }

        return back()->with('success', 'Status artikel diperbarui.');
    }

    public function categories(): View
    {
        $categories = CmsCategory::query()
            ->withCount('posts')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.blog.categories', compact('categories'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $this->validatedCategory($request);
        $data['slug'] = $this->uniqueCategorySlug(($data['slug'] ?? '') ?: $data['name']);
        CmsCategory::create($data);

        return redirect()->route('admin.blog.categories')->with('success', 'Kategori blog berhasil dibuat.');
    }

    public function updateCategory(Request $request, CmsCategory $category): RedirectResponse
    {
        $data = $this->validatedCategory($request, $category);
        $data['slug'] = $this->uniqueCategorySlug(($data['slug'] ?? '') ?: $data['name'], $category->id);
        $category->update($data);

        return redirect()->route('admin.blog.categories')->with('success', 'Kategori blog berhasil diperbarui.');
    }

    public function destroyCategory(CmsCategory $category): RedirectResponse
    {
        // The shared CMS content table uses nullOnDelete() for category links.
        $category->delete();

        return redirect()->route('admin.blog.categories')->with('success', 'Kategori blog dihapus. Artikel terkait tetap tersimpan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'cms_category_id' => [
                'nullable',
                'integer',
                Rule::exists('cms_contents', 'id')->where('type', CmsCategory::CONTENT_TYPE),
            ],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'cover_image' => ['nullable', 'url:http,https', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function validatedCategory(Request $request, ?CmsCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('cms_contents', 'slug')
                    ->where('type', CmsCategory::CONTENT_TYPE)
                    ->ignore($category?->id),
            ],
        ]);
    }

    private function setPublicationDate(array &$data, ?CmsPost $post = null): void
    {
        if ($data['status'] === 'published' && blank($data['published_at'] ?? null)) {
            $data['published_at'] = $post?->published_at ?? now();
        }
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        return UniqueSlug::make($value, 'artikel', fn (string $slug) => CmsPost::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists());
    }

    private function uniqueCategorySlug(string $value, ?int $ignoreId = null): string
    {
        return UniqueSlug::make($value, 'kategori', fn (string $slug) => CmsCategory::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists());
    }
}
