<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\EbayStore;
use App\Models\EbayOffer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EbayStoreController extends Controller
{
    public function index(Request $request): View
    {
        $query = EbayStore::with(['handler']);

        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($storeId = $request->get('store_id')) {
            $query->where('id', $storeId);
        }
        
        if ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        } elseif ($request->get('status') !== 'all') {
            $query->where('is_active', true);
        }

        $stores   = $query->latest()->paginate(20)->withQueryString();
        $crmUsers = User::crmMembers()->orderBy('name')->get();
        $allStores = EbayStore::orderBy('store_name')->get(['id', 'store_name']);
        $totalStoresCount = EbayStore::count();

        return view('crm.ebay.stores.index', compact('stores', 'crmUsers', 'allStores', 'totalStoresCount'));
    }

    public function create(): View
    {
        return view('crm.ebay.stores.create', [
            'crmUsers' => User::crmMembers()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_name'   => ['required', 'string', 'max:255'],
            'logo_url'     => ['nullable', 'url', 'max:1000'],
            'store_url'    => ['nullable', 'url', 'max:500'],
            'ebay_username'=> ['nullable', 'string', 'max:255'],
            'handled_by'   => ['nullable', 'exists:users,id'],
            'notes'        => ['nullable', 'string'],
        ]);

        $store = EbayStore::create([...$validated, 'is_active' => true]);

        return redirect()->route('crm.ebay.stores.show', $store)
            ->with('success', 'eBay Store "' . $store->store_name . '" created.');
    }

    public function show(EbayStore $store, Request $request): View
    {
        $store->load(['handler']);

        $offersQuery = EbayOffer::with(['customer', 'handler', 'order'])
            ->where('store_id', $store->id);

        if ($s = $request->get('search')) {
            $offersQuery->search($s);
        }

        $offers = $offersQuery->latest()->paginate(20)->withQueryString();

        return view('crm.ebay.stores.show', compact('store', 'offers'));
    }

    public function edit(EbayStore $store): View
    {
        return view('crm.ebay.stores.edit', [
            'store'    => $store,
            'crmUsers' => User::crmMembers()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, EbayStore $store): RedirectResponse
    {
        $validated = $request->validate([
            'store_name'   => ['required', 'string', 'max:255'],
            'logo_url'     => ['nullable', 'url', 'max:1000'],
            'store_url'    => ['nullable', 'url', 'max:500'],
            'ebay_username'=> ['nullable', 'string', 'max:255'],
            'handled_by'   => ['nullable', 'exists:users,id'],
            'notes'        => ['nullable', 'string'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $store->update($validated);

        return redirect()->route('crm.ebay.stores.show', $store)
            ->with('success', 'Store updated.');
    }

    public function destroy(EbayStore $store): RedirectResponse
    {
        $store->delete();
        return redirect()->route('crm.ebay.stores.index')
            ->with('success', 'Store deleted.');
    }
}
