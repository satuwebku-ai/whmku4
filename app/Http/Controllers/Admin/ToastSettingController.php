<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ToastStyle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToastSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.toast', [
            'values' => ToastStyle::current(),
            'defaults' => ToastStyle::defaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'toast_position' => ['required', 'in:' . implode(',', array_keys(ToastStyle::POSITIONS))],
            'toast_duration' => ['required', 'integer', 'min:1', 'max:60'],
            'toast_duration_error' => ['required', 'integer', 'min:1', 'max:60'],
            'toast_width' => ['required', 'integer', 'min:260', 'max:640'],
            'toast_radius' => ['required', 'integer', 'min:0', 'max:32'],
        ];

        foreach (array_keys(ToastStyle::TYPES) as $type) {
            foreach (array_keys(ToastStyle::COLOR_PARTS) as $part) {
                $rules["toast_{$type}_{$part}"] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
            }
        }

        $data = $request->validate($rules, [
            'regex' => 'Format warna harus heksadesimal, contoh #10b981.',
        ]);

        // Checkbox yang tidak dicentang tidak ikut terkirim.
        foreach (['toast_show_icon', 'toast_show_progress', 'toast_shadow'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }

        Setting::putMany(array_map('strval', $data), 'toast');

        return back()->with('success', 'Tampilan notifikasi berhasil disimpan.');
    }

    /**
     * Kembalikan semua ke bawaan dengan menghapus nilai tersimpan.
     */
    public function reset(): RedirectResponse
    {
        Setting::where('group', 'toast')->delete();
        Setting::flushCache();

        return back()->with('success', 'Tampilan notifikasi dikembalikan ke bawaan.');
    }
}
