<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DomainInterest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DomainInterestController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->input('type');

        $interests = DomainInterest::query()
            ->with(['client', 'tld', 'tldPremium'])
            ->when(in_array($type, [
                DomainInterest::SEARCH,
                DomainInterest::CART,
                DomainInterest::CHECKOUT,
            ], true), fn ($query) => $query->where('event_type', $type))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.domain-interests.index', [
            'interests' => $interests,
            'type' => $type,
        ]);
    }
}