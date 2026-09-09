<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function pages(Request $request): View
    {
        return view('admin.pages.index', $this->indexData($request));
    }

    public function pagesBootstrap(Request $request): View
    {
        return view('admin.pages.index', $this->indexData($request));
    }

    private function indexData(Request $request): array
    {
        $pages = Page::query()
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return compact('pages');
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => new Page()]);
    }

    public function createBootstrap(): View
    {
        return view('admin.pages.form', ['page' => new Page()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->withBooleans($request, $data);

        Page::create($data);

        return redirect()->route('admin.pages')->with('success', 'Halaman berhasil dibuat.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', compact('page'));
    }

    public function editBootstrap(Page $page): View
    {
        return view('admin.pages.form', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validated($request, $page->id);
        $data = $this->withBooleans($request, $data);

        $page->update($data);

        return redirect()->route('admin.pages')->with('success', 'Halaman berhasil diperbarui.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.pages')->with('success', 'Halaman berhasil dihapus.');
    }

    /**
     * Cek ketersediaan slug — dipakai form lewat AJAX.
     */
    public function checkSlug(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string'],
            'id'   => ['nullable', 'integer'],
        ]);

        $slug = Str::slug($data['slug']);

        if (Page::isReservedSlug($slug)) {
            return response()->json([
                'available' => false,
                'reason' => 'Alamat ini dipakai oleh fitur sistem, bukan halaman lain.',
            ]);
        }

        $exists = Page::where('slug', $slug)
            ->when($data['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        return response()->json(['available' => ! $exists]);
    }

    public function status(Request $request): RedirectResponse
    {
        $page = Page::findOrFail($request->input('page_id'));
        $page->update(['is_published' => ! $page->is_published]);

        return back()->with('success', 'Status halaman diperbarui.');
    }

    private function withBooleans(Request $request, array $data): array
    {
        $data['noindex'] = $request->boolean('noindex');
        $data['is_published'] = $request->boolean('is_published');
        $data['show_in_footer'] = $request->boolean('show_in_footer');

        return $data;
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => [
                'nullable', 'string', 'max:255',
                'unique:pages,slug' . ($ignoreId ? ",{$ignoreId}" : ''),
                function ($attribute, $value, $fail) {
                    if ($value && \App\Models\Page::isReservedSlug($value)) {
                        $fail('Alamat "' . \Illuminate\Support\Str::slug($value) . '" sudah dipakai oleh fitur sistem. Pilih alamat lain, mis. "' . \Illuminate\Support\Str::slug($value) . '-1".');
                    }
                },
            ],
            'content'          => ['nullable', 'string'],
            'meta_title'       => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'meta_keywords'    => ['nullable', 'string', 'max:255'],
            'og_image'         => ['nullable', 'string', 'max:255'],
            'noindex'          => ['nullable', 'boolean'],
            'is_published'     => ['nullable', 'boolean'],
            'show_in_footer'   => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
