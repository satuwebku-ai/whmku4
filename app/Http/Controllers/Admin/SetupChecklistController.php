<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SetupChecklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetupChecklistController extends Controller
{
    /**
     * Tandai item checklist sebagai "tidak dipakai" (atau batalkan).
     * Modal dibuka lagi setelahnya supaya admin bisa lanjut merapikan
     * item lain tanpa harus login ulang.
     */
    public function skip(Request $request, SetupChecklistService $setup): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50'],
            'restore' => ['nullable', 'boolean'],
            'stay' => ['nullable', 'boolean'],
        ]);

        $ok = $request->boolean('restore')
            ? $setup->restore($data['key'])
            : $setup->skip($data['key']);

        // 'stay' = dikirim dari tabel di Konsol Web: jangan buka modal lagi.
        $redirect = $request->boolean('stay') ? back() : back()->with('setup_reopen', true);

        return $ok ? $redirect : $redirect->with('error', 'Item ini tidak bisa dilewati.');
    }
}