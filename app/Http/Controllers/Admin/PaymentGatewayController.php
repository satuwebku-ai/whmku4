<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function gateways(): View
    {
        $gateways = PaymentGateway::withCount('payments')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.gateways.index', compact('gateways'));
    }

    public function gatewaysBootstrap(): View
    {
        $gateways = PaymentGateway::withCount('payments')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.gateways.index', compact('gateways'));
    }

    public function create(): View
    {
        return view('admin.gateways.form', [
            'gateway' => new PaymentGateway(),
            'drivers' => PaymentGatewayFactory::DRIVERS,
        ]);
    }

    public function createBootstrap(): View
    {
        return view('admin.gateways.form', [
            'gateway' => new PaymentGateway(),
            'drivers' => PaymentGatewayFactory::DRIVERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        PaymentGateway::create($data);

        return redirect()->route('admin.gateways')->with('success', 'Payment gateway berhasil ditambahkan.');
    }

    public function edit(PaymentGateway $gateway): View
    {
        return view('admin.gateways.form', [
            'gateway' => $gateway,
            'drivers' => PaymentGatewayFactory::DRIVERS,
        ]);
    }

    public function editBootstrap(PaymentGateway $gateway): View
    {
        return view('admin.gateways.form', [
            'gateway' => $gateway,
            'drivers' => PaymentGatewayFactory::DRIVERS,
        ]);
    }

    public function update(Request $request, PaymentGateway $gateway): RedirectResponse
    {
        $data = $this->validated($request, updating: true);
        $data['is_active'] = $request->boolean('is_active');

        // Kredensial kosong = tidak diganti, jangan timpa yang tersimpan.
        foreach (['server_key', 'client_key', 'callback_token'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $gateway->update($data);

        return redirect()->route('admin.gateways')->with('success', 'Payment gateway berhasil diperbarui.');
    }

    public function destroy(PaymentGateway $gateway): RedirectResponse
    {
        if ($gateway->payments()->exists()) {
            return back()->with('error', 'Gateway tidak bisa dihapus karena sudah punya riwayat pembayaran. Nonaktifkan saja.');
        }

        $gateway->delete();

        return redirect()->route('admin.gateways')->with('success', 'Payment gateway berhasil dihapus.');
    }

    /**
     * Aktif/nonaktifkan gateway.
     */
    public function status(Request $request): RedirectResponse
    {
        $gateway = PaymentGateway::findOrFail($request->input('gateway_id'));
        $gateway->update(['is_active' => ! $gateway->is_active]);

        return back()->with('success', "Gateway {$gateway->name} berhasil " . ($gateway->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'driver'         => ['required', 'in:' . implode(',', array_keys(PaymentGatewayFactory::DRIVERS))],
            'mode'           => ['required', 'in:sandbox,production'],
            'server_key'     => [$updating ? 'nullable' : 'required_unless:driver,manual', 'nullable', 'string'],
            'client_key'     => ['nullable', 'string'],
            'callback_token' => ['nullable', 'string'],
            'qris_method_code' => ['nullable', 'string', 'max:20'],
            'instructions'   => ['nullable', 'string'],
            'fee_flat'       => ['nullable', 'numeric', 'min:0'],
            'fee_percent'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency'       => ['required', 'string', 'size:3'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ], [
            'server_key.required_unless' => 'Server Key / Secret Key wajib diisi untuk gateway otomatis.',
        ]);
    }
}
