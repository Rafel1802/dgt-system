<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\TruckingCompany;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function __construct(private CrmCustomerMatchService $matcher)
    {
    }

    public function index(Request $request): View
    {
        $query = Shipment::with(['truckingCompany', 'assignee'])->withCount('shipmentCustomers');

        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $shipments  = $query->latest()->paginate(20)->withQueryString();
        $statuses   = Shipment::statuses();

        return view('crm.logistics.shipments.index', compact('shipments', 'statuses'));
    }

    public function create(): View
    {
        return view('crm.logistics.shipments.create', [
            'statuses'         => Shipment::statuses(),
            'truckingCompanies'=> TruckingCompany::active()->orderBy('company_name')->get(),
            'crmUsers'         => User::crmMembers()->orderBy('name')->get(),
            'customers'        => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone', 'company', 'address']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shipment_code'        => ['nullable', 'string', 'max:100', 'unique:shipments,shipment_code'],
            'status'               => ['required', 'string', 'in:' . implode(',', array_keys(Shipment::statuses()))],
            'trucking_company_id'  => ['nullable', 'exists:trucking_companies,id'],
            'assigned_to'          => ['nullable', 'exists:users,id'],
            'estimated_arrival'    => ['nullable', 'date'],
            'notes'                => ['nullable', 'string'],
        ]);

        $shipment = Shipment::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Shipment #' . ($shipment->shipment_code ?? $shipment->id) . ' created.');
    }

    public function show(Shipment $shipment, Request $request): View
    {
        $shipment->load(['truckingCompany', 'creator', 'assignee', 'shipmentCustomers.customer', 'shipmentCustomers.handler']);

        return view('crm.logistics.shipments.show', [
            'shipment'     => $shipment,
            'statuses'     => Shipment::statuses(),
            'custStatuses' => ShipmentCustomer::statuses(),
            'machineSkus'    => ShipmentCustomer::whereNotNull('machine_sku')->distinct()->pluck('machine_sku'),
            'attachmentSkus' => ShipmentCustomer::whereNotNull('attachment_sku')->distinct()->pluck('attachment_sku'),
            'customers'    => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone', 'company', 'address']),
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        return view('crm.logistics.shipments.edit', [
            'shipment'         => $shipment,
            'statuses'         => Shipment::statuses(),
            'truckingCompanies'=> TruckingCompany::active()->orderBy('company_name')->get(),
            'crmUsers'         => User::crmMembers()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        $validated = $request->validate([
            'shipment_code'        => ['nullable', 'string', 'max:100', 'unique:shipments,shipment_code,' . $shipment->id],
            'status'               => ['required', 'string', 'in:' . implode(',', array_keys(Shipment::statuses()))],
            'trucking_company_id'  => ['nullable', 'exists:trucking_companies,id'],
            'assigned_to'          => ['nullable', 'exists:users,id'],
            'estimated_arrival'    => ['nullable', 'date'],
            'actual_arrival'       => ['nullable', 'date'],
            'notes'                => ['nullable', 'string'],
        ]);

        $shipment->update($validated);

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Shipment updated.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        $shipment->delete();
        return redirect()->route('crm.logistics.shipments.index')
            ->with('success', 'Shipment deleted.');
    }

    // ── Shipment Customer sub-routes ─────────────────────────────────────────

    /** Add a customer to a shipment */
    public function addCustomer(Request $request, Shipment $shipment): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'       => ['nullable', 'exists:customers,id'],
            'recipient_name'    => ['nullable', 'string', 'max:255'],
            'recipient_phone'   => ['nullable', 'string', 'max:50'],
            'recipient_email'   => ['nullable', 'email', 'max:255'],
            'shipping_address'  => ['nullable', 'string'],
            'product_description'=> ['nullable', 'string', 'max:255'],
            'machine_sku'       => ['nullable', 'string', 'max:100'],
            'attachment_sku'    => ['nullable', 'string', 'max:100'],
            'handled_by'        => ['nullable', 'exists:users,id'],
            'notes'             => ['nullable', 'string'],
        ]);

        if (empty($validated['recipient_name']) && !empty($validated['customer_id'])) {
            $cust = Customer::find($validated['customer_id']);
            if ($cust) {
                $validated['recipient_name'] = $cust->name;
                if (empty($validated['shipping_address'])) {
                    $validated['shipping_address'] = $cust->address ?? '';
                }
                if (empty($validated['recipient_phone'])) {
                    $validated['recipient_phone'] = $cust->phone ?? '';
                }
                if (empty($validated['recipient_email'])) {
                    $validated['recipient_email'] = $cust->email ?? '';
                }
            }
        }

        if (empty($validated['recipient_name'])) {
            return back()->withErrors(['recipient_name' => 'Recipient name is required.'])->withInput();
        }

        $validated['shipping_address'] = $validated['shipping_address'] ?? '';

        $shipment->shipmentCustomers()->create([
            ...$validated,
            'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Customer added to shipment.');
    }

    /** Update a customer on a shipment (edited via the inline modal on the shipment show page) */
    public function updateCustomer(Request $request, Shipment $shipment, ShipmentCustomer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'       => ['nullable', 'exists:customers,id'],
            'recipient_name'    => ['nullable', 'string', 'max:255'],
            'recipient_phone'   => ['nullable', 'string', 'max:50'],
            'recipient_email'   => ['nullable', 'email', 'max:255'],
            'shipping_address'  => ['nullable', 'string'],
            'product_description'=> ['nullable', 'string', 'max:255'],
            'machine_sku'       => ['nullable', 'string', 'max:100'],
            'attachment_sku'    => ['nullable', 'string', 'max:100'],
            'status'            => ['required', 'string', 'in:' . implode(',', array_keys(ShipmentCustomer::statuses()))],
            'handled_by'        => ['nullable', 'exists:users,id'],
            'notes'             => ['nullable', 'string'],
        ]);

        if (empty($validated['recipient_name']) && !empty($validated['customer_id'])) {
            $cust = Customer::find($validated['customer_id']);
            if ($cust) {
                $validated['recipient_name'] = $cust->name;
                if (empty($validated['recipient_phone'])) {
                    $validated['recipient_phone'] = $cust->phone ?? '';
                }
                if (empty($validated['recipient_email'])) {
                    $validated['recipient_email'] = $cust->email ?? '';
                }
                if (empty($validated['shipping_address'])) {
                    $validated['shipping_address'] = $cust->address ?? '';
                }
            }
        }

        if (empty($validated['recipient_name'])) {
            return back()->withErrors(['recipient_name' => 'Recipient name is required.'])->withInput();
        }

        $validated['shipping_address'] = $validated['shipping_address'] ?? '';

        $becameProblem = $validated['status'] === ShipmentCustomer::STATUS_PROBLEM && $customer->status !== ShipmentCustomer::STATUS_PROBLEM;

        $customer->update($validated);

        if ($becameProblem) {
            $this->matcher->propagateShipmentProblem($customer);
        }

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Customer record updated.');
    }

    /** Remove a customer from a shipment */
    public function removeCustomer(Shipment $shipment, ShipmentCustomer $customer): RedirectResponse
    {
        $customer->delete();
        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Customer removed from shipment.');
    }
}
