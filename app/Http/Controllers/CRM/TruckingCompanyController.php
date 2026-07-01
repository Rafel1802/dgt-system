<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\TruckingCompany;
use App\Models\User;
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
        $crmUsers  = User::crmMembers()->orderBy('name')->get();

        return view('crm.logistics.trucking.index', compact('companies', 'crmUsers'));
    }

    public function create(): View
    {
        return view('crm.logistics.trucking.create', [
            'crmUsers' => User::crmMembers()->orderBy('name')->get(),
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
        $truckingCompany->load(['handler', 'logistics.customer', 'shipments']);

        return view('crm.logistics.trucking.show', [
            'company' => $truckingCompany,
        ]);
    }

    public function edit(TruckingCompany $truckingCompany): View
    {
        return view('crm.logistics.trucking.edit', [
            'company'  => $truckingCompany,
            'crmUsers' => User::crmMembers()->orderBy('name')->get(),
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
        $truckingCompany->delete();
        return redirect()->route('crm.logistics.trucking.index')
            ->with('success', 'Trucking company deleted.');
    }
}
