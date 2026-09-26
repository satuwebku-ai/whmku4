<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\NavMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NavMenuController extends Controller
{
    /**
     * MENU UTAMA
     * Hanya menampilkan item dengan parent_id = null.
     */
    public function index(): View
    {
        return view('admin.nav-menus.index', [
            'menus' => NavMenu::withCount('allChildren')
                ->with(['page', 'defaultChild'])
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function indexBootstrap(): View
    {
        return $this->index();
    }

    public function create(): View
    {
        return view('admin.nav-menus.main-form', $this->formData(new NavMenu([
            'type' => $this->requestedType(),
            'is_active' => true,
        ])));
    }

    public function createBootstrap(): View
    {
        return $this->create();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, false);
        $data['parent_id'] = null;
        $data['sort_order'] = $this->nextSortOrder(null);

        NavMenu::create($data);

        return redirect()->route('admin.nav-menus')
            ->with('success', 'Menu utama berhasil ditambahkan.');
    }

    public function edit(NavMenu $navMenu): View
    {
        abort_if($navMenu->parent_id !== null, 404);

        return view('admin.nav-menus.main-form', $this->formData($navMenu));
    }

    public function editBootstrap(NavMenu $navMenu): View
    {
        return $this->edit($navMenu);
    }

    public function update(Request $request, NavMenu $navMenu): RedirectResponse
    {
        abort_if($navMenu->parent_id !== null, 404);

        $data = $this->validated($request, false, $navMenu);
        $data['parent_id'] = null;

        $navMenu->update($data);

        return redirect()->route('admin.nav-menus')
            ->with('success', 'Menu utama berhasil diperbarui.');
    }

    /**
     * SUBMENU / SUBNAV
     * Halaman ini sengaja dipisahkan dari Menu Utama.
     */
    public function submenus(): View
    {
        $mainMenus = NavMenu::whereNull('parent_id')
            ->with(['children.page'])
            ->withCount('allChildren')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.nav-menus.submenus', compact('mainMenus'));
    }

    public function createSubmenu(Request $request): View
    {
        $parentOptions = $this->parentOptions();

        $parentId = $request->integer('parent_id') ?: null;

        if ($parentId && ! $parentOptions->contains('id', $parentId)) {
            $parentId = null;
        }

        return view('admin.nav-menus.sub-form', [
            'menu' => new NavMenu([
                'parent_id' => $parentId,
                'type' => $this->requestedType(),
                'is_active' => true,
            ]),
            'pages' => $this->publishedPages(),
            'parentOptions' => $parentOptions,
        ]);
    }

    public function storeSubmenu(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $parentId = (int) $data['parent_id'];

        $data['sort_order'] = $this->nextSortOrder($parentId);

        NavMenu::create($data);

        return redirect()->route('admin.nav-submenus')
            ->with('success', 'Submenu berhasil ditambahkan.');
    }

    public function editSubmenu(NavMenu $navMenu): View
    {
        abort_if($navMenu->parent_id === null, 404);

        return view('admin.nav-menus.sub-form', [
            'menu' => $navMenu,
            'pages' => $this->publishedPages(),
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function updateSubmenu(Request $request, NavMenu $navMenu): RedirectResponse
    {
        abort_if($navMenu->parent_id === null, 404);

        $oldParentId = $navMenu->parent_id;
        $data = $this->validated($request, true);
        $newParentId = (int) $data['parent_id'];

        // Jika pindah induk, letakkan di paling bawah submenu induk baru.
        if ($oldParentId !== $newParentId) {
            $data['sort_order'] = $this->nextSortOrder($newParentId);
        }

        $navMenu->update($data);

        return redirect()->route('admin.nav-submenus')
            ->with('success', 'Submenu berhasil diperbarui.');
    }

    public function destroy(NavMenu $navMenu): RedirectResponse
    {
        $label = $navMenu->label;
        $isMain = $navMenu->parent_id === null;

        $navMenu->delete();

        return back()->with(
            'success',
            $isMain
                ? "Menu utama \"{$label}\" dan submenu di bawahnya berhasil dihapus."
                : "Submenu \"{$label}\" berhasil dihapus."
        );
    }

    public function toggleStatus(Request $request): RedirectResponse
    {
        $menu = NavMenu::findOrFail($request->input('nav_menu_id'));
        $menu->update(['is_active' => ! $menu->is_active]);

        return back()->with(
            'success',
            "\"{$menu->label}\" berhasil " . ($menu->is_active ? 'ditampilkan.' : 'disembunyikan.')
        );
    }

    /**
     * Urutan hanya dibandingkan dengan saudara pada level yang sama.
     * Menu utama tidak boleh bertukar posisi dengan submenu milik menu lain.
     */
    public function move(Request $request, NavMenu $navMenu): RedirectResponse
    {
        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        $query = NavMenu::query()->where('parent_id', $navMenu->parent_id);

        $neighbor = $direction === 'up'
            ? $query->where(function ($q) use ($navMenu) {
                $q->where('sort_order', '<', $navMenu->sort_order)
                    ->orWhere(function ($q2) use ($navMenu) {
                        $q2->where('sort_order', $navMenu->sort_order)
                            ->where('id', '<', $navMenu->id);
                    });
            })->orderByDesc('sort_order')->orderByDesc('id')->first()
            : $query->where(function ($q) use ($navMenu) {
                $q->where('sort_order', '>', $navMenu->sort_order)
                    ->orWhere(function ($q2) use ($navMenu) {
                        $q2->where('sort_order', $navMenu->sort_order)
                            ->where('id', '>', $navMenu->id);
                    });
            })->orderBy('sort_order')->orderBy('id')->first();

        if (! $neighbor) {
            return back();
        }

        DB::transaction(function () use ($navMenu, $neighbor) {
            $a = $navMenu->sort_order;
            $b = $neighbor->sort_order;

            // Hindari collision jika dua record memiliki sort_order sama.
            if ($a === $b) {
                $temporary = (int) NavMenu::max('sort_order') + 1000;
                $navMenu->update(['sort_order' => $temporary]);
            }

            $navMenu->update(['sort_order' => $b]);
            $neighbor->update(['sort_order' => $a]);
        });

        return back();
    }

    private function formData(NavMenu $menu): array
    {
        return [
            'menu' => $menu,
            'pages' => $this->publishedPages(),
            // Hanya Menu Utama yang sudah tersimpan yang bisa punya
            // Subnav untuk dipilih sebagai tujuan langsung -- menu baru
            // belum bisa punya Subnav sama sekali.
            'children' => $menu->exists ? $menu->allChildren : collect(),
        ];
    }

    private function publishedPages()
    {
        return CmsPage::published()->orderBy('title')->get();
    }

    private function parentOptions()
    {
        return NavMenu::whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function nextSortOrder(?int $parentId): int
    {
        return ((int) NavMenu::where('parent_id', $parentId)->max('sort_order')) + 1;
    }

    private function requestedType(): string
    {
        return in_array(request('type'), ['route', 'page', 'url'], true)
            ? request('type')
            : 'page';
    }

    private function validated(Request $request, bool $submenu, ?NavMenu $navMenu = null): array
    {
        // Menu Utama yang sudah punya Subnav boleh diset supaya klik-nya
        // langsung menuju satu Subnav tertentu, bukan dropdown. Saat mode
        // ini dipilih, field "Tautan Menuju" (route/page/url) di bawahnya
        // tidak dipakai -- tujuannya diambil dari Subnav yang dipilih.
        $isDirectChildMode = ! $submenu && $request->input('link_mode') === 'child';

        $rules = [
            'label' => ['required', 'string', 'max:50'],
            'type' => [$isDirectChildMode ? 'nullable' : 'required', 'in:route,page,url'],
            'route_name' => [$isDirectChildMode ? 'nullable' : 'required_if:type,route', 'nullable', 'string', 'in:' . implode(',', array_keys(NavMenu::BUILTIN_ROUTES))],
            'page_id' => [$isDirectChildMode ? 'nullable' : 'required_if:type,page', 'nullable', 'exists:cms_pages,id'],
            'url' => [$isDirectChildMode ? 'nullable' : 'required_if:type,url', 'nullable', 'url', 'max:255'],
            'open_in_new_tab' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($submenu) {
            $rules['parent_id'] = ['required', 'exists:nav_menus,id'];
        } elseif ($isDirectChildMode) {
            $rules['default_child_id'] = ['required', 'integer'];
        }

        $data = $request->validate($rules, [
            'parent_id.required' => 'Pilih menu utama untuk submenu ini.',
            'type.required' => 'Pilih tujuan tautan menu ini.',
            'route_name.required_if' => 'Pilih salah satu halaman bawaan.',
            'page_id.required_if' => 'Pilih salah satu halaman.',
            'url.required_if' => 'Isi alamat tautannya.',
            'url.url' => 'Format URL tidak valid — awali dengan https://',
            'default_child_id.required' => 'Pilih Subnav tujuan.',
        ]);

        if ($submenu) {
            $parent = NavMenu::find($data['parent_id']);

            if (! $parent || $parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Submenu harus berada langsung di bawah menu utama.',
                ]);
            }
        }

        if ($isDirectChildMode) {
            $childId = (int) ($data['default_child_id'] ?? 0);
            $belongsToThisMenu = $navMenu
                ? NavMenu::where('id', $childId)->where('parent_id', $navMenu->id)->exists()
                : false;

            if (! $belongsToThisMenu) {
                throw ValidationException::withMessages([
                    'default_child_id' => 'Subnav yang dipilih tidak valid.',
                ]);
            }
        }

        if ($isDirectChildMode) {
            // Tujuannya diambil dari Subnav yang dipilih, field
            // route/page/url milik Menu Utama ini sendiri diabaikan.
            $data['type'] = 'url';
            $data['route_name'] = null;
            $data['page_id'] = null;
            $data['url'] = null;
            $data['default_child_id'] = (int) $data['default_child_id'];
        } else {
            // Hanya simpan field sesuai jenis tautan yang dipilih.
            $data['route_name'] = $data['type'] === 'route' ? $data['route_name'] : null;
            $data['page_id'] = $data['type'] === 'page' ? $data['page_id'] : null;
            $data['url'] = $data['type'] === 'url' ? $data['url'] : null;

            if (! $submenu) {
                $data['default_child_id'] = null;
            }
        }

        $data['open_in_new_tab'] = $request->boolean('open_in_new_tab');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
