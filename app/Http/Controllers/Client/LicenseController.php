<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(): View
    {
        $licenses = Order::query()
            ->where('client_id', Auth::guard('client')->id())
            ->where('order_type', 'addon')
            ->with('invoiceItem.invoice')
            ->latest()
            ->paginate(15);

        return view('client.licenses.index', compact('licenses'));
    }
}