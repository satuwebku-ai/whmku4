<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiChatUsage;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trafik pemakaian bot chat AI -- token dicatat apa adanya dari
 * respons Anthropic. Biaya dihitung dari TARIF YANG ADMIN ISI SENDIRI
 * (bukan angka tetap di kode), karena harga per model Anthropic bisa
 * berubah dan sumber-sumber berbeda sering tidak sepakat soal angka
 * terkini -- lebih aman admin cek claude.com/pricing lalu isi sendiri,
 * daripada aplikasi menampilkan estimasi yang mungkin sudah usang.
 */
class AiUsageController extends Controller
{
    public function index(Request $request): View
    {
        $mulai = $request->filled('from') ? \Carbon\Carbon::parse($request->from) : now()->startOfMonth();
        $sampai = $request->filled('to') ? \Carbon\Carbon::parse($request->to)->endOfDay() : now();

        $query = AiChatUsage::whereBetween('created_at', [$mulai, $sampai]);

        $totalPesan = (clone $query)->count();
        $totalInput = (clone $query)->sum('input_tokens');
        $totalOutput = (clone $query)->sum('output_tokens');

        $perModel = (clone $query)
            ->selectRaw('model, COUNT(*) as pesan, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->groupBy('model')
            ->get();

        $perHari = (clone $query)
            ->selectRaw('DATE(created_at) as tanggal, COUNT(*) as pesan, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->groupBy('tanggal')
            ->orderByDesc('tanggal')
            ->limit(30)
            ->get();

        // Tarif per model diisi admin sendiri (USD per 1 juta token) --
        // dipakai menghitung estimasi biaya di tampilan, bukan disimpan
        // permanen ke tiap baris supaya selalu bisa disesuaikan.
        // Mencakup KEDUA provider, karena tabel ai_chat_usages menyimpan
        // nama model apa adanya, dari provider manapun yang aktif saat itu.
        $tarifSimple = [
            'haiku'      => ['in' => (float) Setting::get('ai_price_haiku_in', 1), 'out' => (float) Setting::get('ai_price_haiku_out', 5)],
            'sonnet'     => ['in' => (float) Setting::get('ai_price_sonnet_in', 3), 'out' => (float) Setting::get('ai_price_sonnet_out', 15)],
            'opus'       => ['in' => (float) Setting::get('ai_price_opus_in', 5), 'out' => (float) Setting::get('ai_price_opus_out', 25)],
            'gpt4o_mini' => ['in' => (float) Setting::get('ai_price_gpt4o_mini_in', 0.15), 'out' => (float) Setting::get('ai_price_gpt4o_mini_out', 0.6)],
            'gpt4o'      => ['in' => (float) Setting::get('ai_price_gpt4o_in', 2.5), 'out' => (float) Setting::get('ai_price_gpt4o_out', 10)],
        ];

        // Versi berkunci model-id lengkap -- dipakai mencocokkan baris
        // tabel "Per Model" (yang menyimpan nama model apa adanya dari
        // provider) dengan tarifnya.
        $tarif = [
            'claude-haiku-4-5-20251001' => $tarifSimple['haiku'],
            'claude-sonnet-5' => $tarifSimple['sonnet'],
            'claude-opus-4-8' => $tarifSimple['opus'],
            'gpt-4o-mini' => $tarifSimple['gpt4o_mini'],
            'gpt-4o' => $tarifSimple['gpt4o'],
        ];

        $estimasiTotal = 0;
        foreach ($perModel as $row) {
            $t = $tarif[$row->model] ?? ['in' => 0, 'out' => 0];
            $estimasiTotal += ($row->input_tokens / 1_000_000 * $t['in']) + ($row->output_tokens / 1_000_000 * $t['out']);
        }

        return view('admin.ai-usage.index', compact(
            'mulai', 'sampai', 'totalPesan', 'totalInput', 'totalOutput',
            'perModel', 'perHari', 'tarif', 'tarifSimple', 'estimasiTotal'
        ));
    }

    public function updatePricing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ai_price_haiku_in' => ['required', 'numeric', 'min:0'],
            'ai_price_haiku_out' => ['required', 'numeric', 'min:0'],
            'ai_price_sonnet_in' => ['required', 'numeric', 'min:0'],
            'ai_price_sonnet_out' => ['required', 'numeric', 'min:0'],
            'ai_price_opus_in' => ['required', 'numeric', 'min:0'],
            'ai_price_opus_out' => ['required', 'numeric', 'min:0'],
            'ai_price_gpt4o_mini_in' => ['required', 'numeric', 'min:0'],
            'ai_price_gpt4o_mini_out' => ['required', 'numeric', 'min:0'],
            'ai_price_gpt4o_in' => ['required', 'numeric', 'min:0'],
            'ai_price_gpt4o_out' => ['required', 'numeric', 'min:0'],
        ]);

        Setting::putMany($data, 'ai_chat');

        return back()->with('success', 'Tarif berhasil disimpan.');
    }
}
