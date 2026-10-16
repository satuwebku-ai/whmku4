<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServerGroupController extends Controller
{
    public function index(): View
    {
        $groups = ServerGroup::withCount(['servers', 'products'])
            ->with(['servers' => fn ($q) => $q->withCount(['hostingAccounts as active_accounts_count' => fn ($a) => $a->whereNotIn('status', Server::INACTIVE_ACCOUNT_STATUSES)])])
            ->orderBy('name')
            ->paginate(15);

        return view('admin.server-groups.index', compact('groups'));
    }

    public function create(): View
    {
        return view('admin.server-groups.form', [
            'group'   => new ServerGroup(['selection_mode' => 'least_accounts', 'is_active' => true]),
            'servers' => $this->memberCandidates(),
            'members' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        $group = ServerGroup::create($data);
        $group->servers()->sync($this->memberPayload($request));

        return redirect()->route('admin.server-groups.index')->with('success', 'Grup server berhasil dibuat.');
    }

    public function edit(ServerGroup $serverGroup): View
    {
        return view('admin.server-groups.form', [
            'group'   => $serverGroup,
            'servers' => $this->memberCandidates($serverGroup),
            // server_id => ['priority' => n, 'is_active' => bool]
            'members' => $serverGroup->servers()->get()->mapWithKeys(fn ($s) => [$s->id => ['priority' => $s->pivot->priority, 'is_active' => (bool) $s->pivot->is_active]])->all(),
        ]);
    }

    public function update(Request $request, ServerGroup $serverGroup): RedirectResponse
    {
        $data = $this->validated($request, $serverGroup->id);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name'], $serverGroup->id);
        $data['is_active'] = $request->boolean('is_active');

        $serverGroup->update($data);
        $serverGroup->servers()->sync($this->memberPayload($request, $serverGroup));

        return redirect()->route('admin.server-groups.index')->with('success', 'Grup server berhasil diperbarui.');
    }

    public function destroy(ServerGroup $serverGroup): RedirectResponse
    {
        if ($serverGroup->servers()->exists()) {
            return back()->with('error', 'Grup tidak bisa dihapus karena masih punya server anggota. Buka Edit Grup, hilangkan centang semua server, lalu simpan.');
        }

        if ($serverGroup->products()->exists()) {
            return back()->with('error', 'Grup tidak bisa dihapus karena masih dipakai produk. Ganti Grup Server di produk terkait dulu.');
        }

        $serverGroup->delete();

        return redirect()->route('admin.server-groups.index')->with('success', 'Grup server berhasil dihapus.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'slug'           => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('server_groups', 'slug')->ignore($ignoreId)],
            'description'    => ['nullable', 'string', 'max:1000'],
            'selection_mode' => ['required', Rule::in(array_keys(ServerGroup::MODES))],
            'is_active'      => ['nullable', 'boolean'],
            'servers'        => ['nullable', 'array'],
            'servers.*'      => ['integer'],
            'priority'       => ['nullable', 'array'],
            'priority.*'     => ['nullable', 'integer', 'min:1', 'max:999'],
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
        ]);
    }

    /**
     * Server yang boleh jadi anggota grup: hanya server hosting (cPanel dst).
     * Server VM/VPS dikecualikan -- harga & tagihannya terikat ke server
     * tetap milik produk VPS, jadi tidak dipilih otomatis lewat grup.
     */
    private function memberCandidates(?ServerGroup $group = null)
    {
        return Server::query()
            ->where(function ($query) use ($group) {
                $query->where(fn ($eligible) => $eligible->where('panel', 'cpanel')->whereNull('vps_provider'));

                // Existing ineligible members stay visible so an admin can
                // remove them deliberately instead of losing group data.
                if ($group) {
                    $query->orWhereHas('groups', fn ($groups) => $groups->whereKey($group->id));
                }
            })
            ->withCount(['hostingAccounts as active_accounts_count' => fn ($q) => $q->whereNotIn('status', Server::INACTIVE_ACCOUNT_STATUSES)])
            ->orderBy('name')
            ->get();
    }

    /** Hasil centang + prioritas dari form -> payload sync() pivot. */
    private function memberPayload(Request $request, ?ServerGroup $group = null): array
    {
        $ids = Server::query()
            ->whereIn('id', (array) $request->input('servers', []))
            ->where(function ($query) use ($group) {
                $query->where(fn ($eligible) => $eligible->where('panel', 'cpanel')->whereNull('vps_provider'));

                // Preserve existing unsupported memberships unless the admin
                // explicitly unchecks them in the edit form.
                if ($group) {
                    $query->orWhereHas('groups', fn ($groups) => $groups->whereKey($group->id));
                }
            })
            ->pluck('id');

        $priorities = (array) $request->input('priority', []);

        return $ids->mapWithKeys(fn ($id) => [
            $id => [
                'priority'  => (int) ($priorities[$id] ?? 10) ?: 10,
                'is_active' => true,
            ],
        ])->all();
    }

    /** Slug dari nama kalau kosong; ditambah angka kalau sudah dipakai grup lain. */
    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = filled($slug) ? $slug : (Str::slug($name) ?: 'grup');
        $candidate = $base;
        $i = 2;

        while (ServerGroup::where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base . '-' . $i++;
        }

        return $candidate;
    }
}
