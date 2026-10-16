<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsCategory;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\NavMenu;
use App\Models\PromoBanner;
use Illuminate\View\View;

class CmsDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.cms.index', [
            'counts' => [
                'pages' => CmsPage::count(),
                'published_pages' => CmsPage::published()->count(),
                'posts' => CmsPost::count(),
                'categories' => CmsCategory::count(),
                'menus' => NavMenu::count(),
                'banners' => PromoBanner::count(),
            ],
        ]);
    }
}
