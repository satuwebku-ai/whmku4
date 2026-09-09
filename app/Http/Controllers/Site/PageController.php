<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        return view('public.page', compact('page'));
    }

    public function showBootstrap(string $slug): View
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        return view('public.page', compact('page'));
    }

    public function announcements(): View
    {
        $announcements = Announcement::live()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(10);

        return view('public.announcements', compact('announcements'));
    }

    public function announcementsBootstrap(): View
    {
        $announcements = Announcement::live()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate(10);

        return view('public.announcements', compact('announcements'));
    }

    public function announcement(string $slug): View
    {
        $announcement = Announcement::live()->where('slug', $slug)->firstOrFail();

        return view('public.announcement', compact('announcement'));
    }

    public function announcementBootstrap(string $slug): View
    {
        $announcement = Announcement::live()->where('slug', $slug)->firstOrFail();

        return view('public.announcement', compact('announcement'));
    }
}
