<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Models\DocumentRequirementTld;
use App\Models\Tld;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentRequirementController extends Controller
{
    /**
     * Daftar persyaratan berkas (KTP, NIB, Surat Permohonan, dst).
     */
    public function index(): View
    {
        $requirements = DocumentRequirement::withCount('tldLinks')
            ->with('tldLinks')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.settings.requirements.index', compact('requirements'));
    }

    public function create(): View
    {
        return view('admin.settings.requirements.form', [
            'requirement' => new DocumentRequirement(['is_required' => true, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentRequirement::create($this->validated($request));

        return redirect()->route('admin.settings.requirements.index')
            ->with('success', 'Persyaratan berhasil ditambahkan.');
    }

    public function edit(DocumentRequirement $requirement): View
    {
        return view('admin.settings.requirements.form', compact('requirement'));
    }

    public function update(Request $request, DocumentRequirement $requirement): RedirectResponse
    {
        $requirement->update($this->validated($request));

        return redirect()->route('admin.settings.requirements.index')
            ->with('success', 'Persyaratan berhasil diperbarui.');
    }

    public function destroy(DocumentRequirement $requirement): RedirectResponse
    {
        // Berkas yang sudah diunggah klien TIDAK ikut terhapus --
        // document_requirement_id di domain_documents di-set null
        // (nullOnDelete), jadi riwayatnya tetap ada. Yang hilang cuma
        // pengelompokannya.
        if ($requirement->documents()->exists()) {
            return back()->with('error', 'Persyaratan ini sudah dipakai berkas yang diunggah klien. Nonaktifkan saja alih-alih dihapus, supaya riwayat berkasnya tetap utuh.');
        }

        $requirement->delete();

        return redirect()->route('admin.settings.requirements.index')
            ->with('success', 'Persyaratan berhasil dihapus.');
    }

    /**
     * Halaman pemetaan: ekstensi domain mana butuh persyaratan apa.
     */
    public function domains(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        // Daftar ekstensi diambil dari TLD yang benar-benar dijual --
        // dibuat unik, karena satu ekstensi bisa punya beberapa baris
        // (satu per registrar) sedangkan syarat dokumen ditentukan
        // registry, bukan registrar.
        $extensions = Tld::query()
            ->when($search, fn ($q) => $q->where('extension', 'like', "%{$search}%"))
            ->orderBy('extension')
            ->pluck('extension')
            ->unique()
            ->values();

        // Yang sudah punya persyaratan selalu ikut tampil walau tidak
        // cocok pencarian -- supaya pemetaan yang sudah ada tidak
        // "hilang" dari pandangan hanya karena filter.
        $mapped = DocumentRequirementTld::pluck('extension')->unique();

        if ($search) {
            $extensions = $extensions->merge(
                $mapped->filter(fn ($e) => str_contains($e, $search))
            )->unique()->sort()->values();
        }

        $requirements = DocumentRequirement::active()->orderBy('sort_order')->orderBy('name')->get();

        // [ekstensi => [id persyaratan, ...]]
        $current = DocumentRequirementTld::all()
            ->groupBy('extension')
            ->map(fn ($rows) => $rows->pluck('document_requirement_id')->all());

        return view('admin.settings.requirements.domains', compact('extensions', 'requirements', 'current', 'search'));
    }

    /**
     * Simpan pemetaan untuk SATU ekstensi.
     *
     * Sengaja per-ekstensi, bukan sekaligus seluruh tabel: halaman ini
     * bisa memuat ratusan ekstensi, dan submit borongan berisiko
     * menghapus pemetaan ekstensi yang kebetulan sedang tidak tampil
     * karena filter pencarian.
     */
    public function updateDomain(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'extension' => ['required', 'string', 'max:30'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['integer', 'exists:document_requirements,id'],
        ]);

        $ext = '.' . ltrim(strtolower(trim($data['extension'])), '.');
        $ids = array_map('intval', $data['requirements'] ?? []);

        DocumentRequirementTld::where('extension', $ext)->delete();

        foreach ($ids as $id) {
            DocumentRequirementTld::create([
                'document_requirement_id' => $id,
                'extension' => $ext,
            ]);
        }

        $jumlah = count($ids);

        return back()->with(
            'success',
            $jumlah > 0
                ? "Persyaratan untuk {$ext} disimpan ({$jumlah} berkas)."
                : "Semua persyaratan untuk {$ext} dihapus — domain ini tidak lagi butuh berkas."
        );
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
