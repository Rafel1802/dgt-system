<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\TruckingCompany;
use App\Models\TruckingCompanyDriver;
use App\Models\User;
use App\Support\CrmLookupCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TruckingCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = TruckingCompany::with(['handler']);

        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($request->get('status') === 'inactive') {
            $query->where('is_active', false);
        } elseif ($request->get('status') !== 'all') {
            $query->where('is_active', true);
        }

        $companies = $query->latest()->paginate(20)->withQueryString();
        $crmUsers  = CrmLookupCache::crmMembers();

        return view('crm.logistics.trucking.index', compact('companies', 'crmUsers'));
    }

    public function create(): View
    {
        return view('crm.logistics.trucking.create', [
            'crmUsers' => CrmLookupCache::crmMembers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'pic_name'     => ['nullable', 'string', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string'],
            'notes'        => ['nullable', 'string'],
            'handled_by'   => ['nullable', 'exists:users,id'],
        ]);

        $company = TruckingCompany::create([...$validated, 'is_active' => true]);

        return redirect()->route('crm.logistics.trucking.index')
            ->with('success', 'Trucking company "' . $company->company_name . '" created.');
    }

    public function show(TruckingCompany $truckingCompany): View
    {
        $truckingCompany->load(['handler', 'drivers']);
        $shipments = $truckingCompany->shipments()
            ->with('assignee')
            ->withCount('shipmentCustomers')
            ->latest()
            ->paginate(15);

        return view('crm.logistics.trucking.show', [
            'company' => $truckingCompany,
            'truckingCompany' => $truckingCompany,
            'shipments' => $shipments,
        ]);
    }

    public function edit(TruckingCompany $truckingCompany): View
    {
        return view('crm.logistics.trucking.edit', [
            'company'  => $truckingCompany,
            'crmUsers' => CrmLookupCache::crmMembers(),
        ]);
    }

    public function update(Request $request, TruckingCompany $truckingCompany): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'pic_name'     => ['nullable', 'string', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string'],
            'notes'        => ['nullable', 'string'],
            'handled_by'   => ['nullable', 'exists:users,id'],
            'is_active'    => ['sometimes', 'boolean'],
        ]);

        $truckingCompany->update($validated);

        return redirect()->route('crm.logistics.trucking.index')
            ->with('success', 'Trucking company updated.');
    }

    public function destroy(TruckingCompany $truckingCompany): RedirectResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('logistic'), 403, 'Only a Logistic Supervisor, eBay Supervisor, CRM Supervisor, or Boss can delete trucking companies.');

        $truckingCompany->delete();
        return redirect()->route('crm.logistics.trucking.index')
            ->with('success', 'Trucking company deleted.');
    }

    /** Add a single driver from the Trucking Profile page */
    public function storeDriver(Request $request, TruckingCompany $truckingCompany): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $truckingCompany->drivers()->create($validated);

        return redirect()->route('crm.logistics.trucking.show', $truckingCompany)
            ->with('success', 'Driver added.');
    }

    /** Remove a single driver from the Trucking Profile page */
    public function destroyDriver(TruckingCompany $truckingCompany, TruckingCompanyDriver $driver): RedirectResponse
    {
        abort_unless($driver->trucking_company_id === $truckingCompany->id, 404);
        abort_unless(auth()->user()->canDeleteCrmRecords('logistic'), 403, 'Only a Logistic Supervisor, eBay Supervisor, CRM Supervisor, or Boss can delete drivers.');

        $driver->delete();

        return redirect()->route('crm.logistics.trucking.show', $truckingCompany)
            ->with('success', 'Driver removed.');
    }
}
