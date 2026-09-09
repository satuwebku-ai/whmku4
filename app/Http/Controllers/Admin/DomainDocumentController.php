<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman terpusat untuk meninjau berkas persyaratan domain.
 *
 * Sebelumnya verifikasi cuma bisa lewat halaman detail satu domain --
 * admin harus tahu domain mana yang sedang menunggu. Halaman ini
 * mengumpulkan semuanya jadi satu antrian kerja.
 */
class DomainDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'waiting');

        $domains = Domain::with(['client', 'tld', 'documents.requirement'])
            ->whereHas('tld', function ($q) {
                // Cuma domain yang ekstensinya memang punya persyaratan.
                $q->whereIn('extension', function ($sub) {
                    $sub->select('extension')->from('document_requirement_tld');
                });
            })
            ->when($request->search, function ($q) use ($request) {
                // Dibungkus where(fn) -- tanpa pengelompokan, orWhereHas
                // di sini akan "membocorkan" filter whereHas di atasnya,
                // sehingga pencarian ikut memunculkan domain yang
                // ekstensinya sama sekali tidak butuh berkas.
                $q->where(function ($sub) use ($request) {
                    $sub->where('domain_name', 'like', "%{$request->search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$request->search}%")
                                                            ->orWhere('email', 'like', "%{$request->search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Kelengkapan dihitung lewat sumber yang SAMA dengan halaman
        // klien & gerbang pembayaran (DomainDocument::progressFor),
        // supaya tidak mungkin beda pendapat soal "sudah lengkap belum".
        $progress = [];

        foreach ($domains as $domain) {
            $progress[$domain->id] = DomainDocument::progressFor($domain);
        }

        // Penyaringan status dilakukan SETELAH progress dihitung, karena
        // status "menunggu" bukan kolom di database melainkan hasil
        // perbandingan berkas yang ada dengan persyaratan yang berlaku.
        $filtered = $domains->getCollection()->filter(function ($domain) use ($progress, $status) {
            $p = $progress[$domain->id];

            return match ($status) {
                'waiting'  => ! $p['complete'],
                'complete' => $p['complete'],
                'rejected' => $p['rejected'] > 0,
                default    => true,
            };
        });

        $domains->setCollection($filtered);

        return view('admin.domain-documents.index', compact('domains', 'progress', 'status'));
    }
}
