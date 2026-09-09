<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavMenu;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NavMenuController extends Controller
{
    public function index(): View
    {
        return view('admin.nav-menus.index', $this->indexData());
    }

    public function indexBootstrap(): View
    {
        return view('admin.nav-menus.index', $this->indexData());
    }

    private function indexData(): array
    {
        // Cuma menu tingkat atas (parent_id kosong) yang diambil di sini —
        // submenu-nya ikut lewat relasi children(), supaya tampilan admin
        // menunjukkan hierarkinya jelas, bukan daftar datar tercampur.
        $menus = NavMenu::with(['page', 'children.page'])
            ->whereNull('parent_id')
            ->orderBy('sort_order')->orderBy('id')->get();

        $topLevelForParentSelect = NavMenu::whereNull('parent_id')->orderBy('sort_order')->get();

        return compact('menus', 'topLevelForParentSelect');
    }

    public function create(): View
    {
        return view('admin.nav-menus.form', [
            'menu' => new NavMenu(['type' => 'page', 'parent_id' => request('parent_id')]),
            'pages' => Page::published()->orderBy('title')->get(),
            'parentOptions' => NavMenu::whereNull('parent_id')->orderBy('sort_order')->get(),
        ]);
    }

    public function createBootstrap(): View
    {
        return view('admin.nav-menus.form', [
            'menu' => new NavMenu(['type' => 'page', 'parent_id' => request('parent_id')]),
            'pages' => Page::published()->orderBy('title')->get(),
            'parentOptions' => NavMenu::whereNull('parent_id')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['sort_order'] = (int) NavMenu::max('sort_order') + 1;

        NavMenu::create($data);

        return redirect()->route('admin.nav-menus')->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit(NavMenu $navMenu): View
    {
        return view('admin.nav-menus.form', [
            'menu' => $navMenu,
            'pages' => Page::published()->orderBy('title')->get(),
            // Menu tidak boleh jadi anak dari dirinya sendiri.
            'parentOptions' => NavMenu::whereNull('parent_id')->where('id', '!=', $navMenu->id)->orderBy('sort_order')->get(),
        ]);
    }

    public function editBootstrap(NavMenu $navMenu): View
    {
        return view('admin.nav-menus.form', [
            'menu' => $navMenu,
            'pages' => Page::published()->orderBy('title')->get(),
            'parentOptions' => NavMenu::whereNull('parent_id')->where('id', '!=', $navMenu->id)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, NavMenu $navMenu): RedirectResponse
    {
        $navMenu->update($this->validated($request));

        return redirect()->route('admin.nav-menus')->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(NavMenu $navMenu): RedirectResponse
    {
        $navMenu->delete();

        return back()->with('success', 'Menu berhasil dihapus.');
    }

    public function toggleStatus(Request $request): RedirectResponse
    {
        $menu = NavMenu::findOrFail($request->input('nav_menu_id'));
        $menu->update(['is_active' => ! $menu->is_active]);

        return back()->with('success', "Menu \"{$menu->label}\" berhasil " . ($menu->is_active ? 'ditampilkan.' : 'disembunyikan.'));
    }

    /**
     * Geser urutan menu satu posisi ke atas/bawah dengan menukar sort_order
     * dengan tetangganya. Dipilih daripada drag-and-drop supaya tidak
     * menambah dependensi JavaScript baru untuk kebutuhan yang sederhana.
     */
    public function move(Request $request, NavMenu $navMenu): RedirectResponse
    {
        $direction = $request->input('direction');

        $neighbor = $direction === 'up'
            ? NavMenu::where('sort_order', '<', $navMenu->sort_order)->orderByDesc('sort_order')->first()
            : NavMenu::where('sort_order', '>', $navMenu->sort_order)->orderBy('sort_order')->first();

        if (! $neighbor) {
            return back();
        }

        $a = $navMenu->sort_order;
        $b = $neighbor->sort_order;
        $navMenu->update(['sort_order' => $b]);
        $neighbor->update(['sort_order' => $a]);

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:nav_menus,id'],
            'label' => ['required', 'string', 'max:50'],
            'type' => ['required', 'in:route,page,url'],
            'route_name' => ['required_if:type,route', 'nullable', 'string', 'in:' . implode(',', array_keys(NavMenu::BUILTIN_ROUTES))],
            'page_id' => ['required_if:type,page', 'nullable', 'exists:pages,id'],
            'url' => ['required_if:type,url', 'nullable', 'url', 'max:255'],
            'open_in_new_tab' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'route_name.required_if' => 'Pilih salah satu halaman bawaan.',
            'page_id.required_if' => 'Pilih salah satu halaman.',
            'url.required_if' => 'Isi alamat tautannya.',
            'url.url' => 'Format URL tidak valid — awali dengan https://',
        ]);

        // Submenu cuma boleh SATU tingkat (tidak ada sub-dari-submenu) —
        // kalau parent yang dipilih ternyata sendiri punya parent, tolak.
        if (! empty($data['parent_id'])) {
            $parent = NavMenu::find($data['parent_id']);

            if ($parent && $parent->parent_id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'parent_id' => 'Submenu cuma boleh satu tingkat — pilih menu utama, bukan submenu lain.',
                ]);
            }
        }

        // Hanya simpan kolom yang relevan dengan tipe terpilih, supaya
        // tidak ada sisa data dari tipe sebelumnya yang membingungkan.
        $data['route_name'] = $data['type'] === 'route' ? $data['route_name'] : null;
        $data['page_id'] = $data['type'] === 'page' ? $data['page_id'] : null;
        $data['url'] = $data['type'] === 'url' ? $data['url'] : null;

        $data['open_in_new_tab'] = $request->boolean('open_in_new_tab');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
