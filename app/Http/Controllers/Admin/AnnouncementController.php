<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function announcements(Request $request): View
    {
        return view('admin.announcements.index', $this->indexData($request));
    }

    public function announcementsBootstrap(Request $request): View
    {
        return view('admin.announcements.index', $this->indexData($request));
    }

    private function indexData(Request $request): array
    {
        $announcements = Announcement::query()
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(15)
            ->withQueryString();

        return compact('announcements');
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement()]);
    }

    public function createBootstrap(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->withBooleans($request, $data);

        Announcement::create($data);

        return redirect()->route('admin.announcements')->with('success', 'Pengumuman berhasil dibuat.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function editBootstrap(Announcement $announcement): View
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request, $announcement->id);
        $data = $this->withBooleans($request, $data);

        $announcement->update($data);

        return redirect()->route('admin.announcements')->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('admin.announcements')->with('success', 'Pengumuman berhasil dihapus.');
    }

    private function withBooleans(Request $request, array $data): array
    {
        $data['is_published'] = $request->boolean('is_published');
        $data['is_pinned'] = $request->boolean('is_pinned');

        return $data;
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => ['nullable', 'string', 'max:255', 'unique:announcements,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'content'          => ['required', 'string'],
            'category'         => ['required', 'in:info,promo,maintenance,incident'],
            'meta_title'       => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'published_at'     => ['nullable', 'date'],
            'is_published'     => ['nullable', 'boolean'],
            'is_pinned'        => ['nullable', 'boolean'],
        ]);
    }
}
