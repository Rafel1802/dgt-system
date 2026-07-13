<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\TruckingCompany;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $status = $request->get('status');
        if ($status === 'all') {
            // no status filter — show every shipment regardless of status
        } elseif ($status) {
            $query->where('status', $status);
        } else {
            // default "Active" tab: anything not yet complete
            $query->where('status', '!=', Shipment::STATUS_COMPLETE);
        }

        $shipments  = $query->latest()->paginate(20)->withQueryString();
        $statuses   = Shipment::statuses();

        return view('crm.logistics.shipments.index', compact('shipments', 'statuses'));
    }

    /** Every customer currently flagged with a logistics/shipment issue, across all sources. */
    public function issues(Request $request): View
    {
        $customers = $this->matcher->buildUnifiedDirectory(['search' => $request->get('search')])
            ->filter(fn ($c) => $c['category'] === 'shipment_delay')
            ->values();

        return view('crm.logistics.issues', compact('customers'));
    }

    public function create(): View
    {
        return view('crm.logistics.shipments.create', [
            'statuses'         => Shipment::statuses(),
            'truckingCompanies'=> TruckingCompany::active()->with('drivers')->orderBy('company_name')->get(),
            'crmUsers'         => User::crmMembers()->orderBy('name')->get(),
            'customers'        => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone', 'company', 'address']),
        ]);
    }

    /**
     * Customers for the shipment "Add Customer" picker, each carrying the
     * product from their most recent order (name only — no price) so
     * selecting a customer can auto-fill the product line without staff
     * re-typing what they already ordered. "Most recent order" checks this
     * customer's order history across all three CRM channels — Logistic
     * (shipping), eBay, and Website (leads) — since a given customer's real
     * order might live in any one of them, and uses whichever actually
     * happened most recently.
     */
    private function customersForPicker(): Collection
    {
        return Customer::orderBy('name')
            ->with([
                'latestLogistic.product',
                'ebayCustomerRecords.orders' => fn ($q) => $q->with('items'),
                'leads' => fn ($q) => $q->orderByDesc('received_at'),
                'leads.orders.items',
                'leads.product',
            ])
            ->get(['id', 'name', 'email', 'phone', 'company', 'address'])
            ->map(function (Customer $customer) {
                $customer->latest_order_product = $this->latestOrderProductFor($customer);

                return $customer;
            });
    }

    /** Compares a customer's latest order across Logistic, eBay, and Website (lead) channels and returns whichever is most recent, product name(s) only — falling back to an unstamped signal only if none of the three have a dated order on file. */
    private function latestOrderProductFor(Customer $customer): ?string
    {
        $logistic = $customer->latestLogistic;
        $candidates = collect([
            [
                'date'    => $logistic?->created_at,
                'product' => $logistic ? ($logistic->product->name ?? $logistic->product_description) : null,
            ],
        ]);

        $latestEbayOrder = $customer->ebayCustomerRecords
            ->flatMap(fn ($record) => $record->orders)
            ->sortByDesc('ordered_at')
            ->first();
        $candidates->push([
            'date'    => $latestEbayOrder?->ordered_at,
            'product' => $latestEbayOrder ? $latestEbayOrder->items->pluck('product_name')->filter()->implode(', ') : null,
        ]);

        $latestLeadOrder = $customer->leads
            ->flatMap(fn ($lead) => $lead->orders)
            ->sortByDesc('order_date')
            ->first();
        $candidates->push([
            'date'    => $latestLeadOrder?->order_date,
            'product' => $latestLeadOrder ? $latestLeadOrder->items->pluck('product_name')->filter()->implode(', ') : null,
        ]);

        $best = $candidates
            ->filter(fn ($c) => $c['date'] && filled($c['product']))
            ->sortByDesc('date')
            ->first();

        if ($best) {
            return $best['product'];
        }

        // No dated order anywhere — last resort: the single-product-interest field on
        // this customer's most recently received lead (same fallback used for leads
        // who haven't been converted to a customer yet), if there's nothing better.
        $mostRecentLead = $customer->leads->first();

        return $mostRecentLead ? ($mostRecentLead->product->name ?? $mostRecentLead->product_interested) : null;
    }

    /**
     * Leads for the shipment "Add Customer" picker (for orders placed before
     * a formal Customer record exists). Product comes from the lead's most
     * recent LeadOrder, falling back to the single product_interested field
     * recorded on the inquiry itself when no formal order is on file.
     */
    private function leadsForPicker(): Collection
    {
        return Lead::whereNull('customer_id') // already-converted leads have a fuller Customer record — pick that instead
            ->orderBy('client_name')
            ->with(['latestOrder.items', 'product'])
            ->get(['id', 'customer_id', 'client_name', 'client_phone', 'client_email', 'product_interested', 'product_id'])
            ->map(function (Lead $lead) {
                $orderProduct = $lead->latestOrder?->items->pluck('product_name')->filter()->implode(', ');
                $lead->latest_order_product = $orderProduct ?: ($lead->product->name ?? $lead->product_interested);

                return $lead;
            });
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shipment_code'        => ['nullable', 'string', 'max:100', 'unique:shipments,shipment_code'],
            'status'               => ['required', 'string', 'in:' . implode(',', array_keys(Shipment::statuses()))],
            'trucking_company_id'  => ['nullable', 'exists:trucking_companies,id'],
            'driver_id'            => ['nullable', Rule::exists('trucking_company_drivers', 'id')->where('trucking_company_id', $request->input('trucking_company_id'))],
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
        $shipment->load(['truckingCompany', 'driver', 'creator', 'assignee', 'shipmentCustomers.customer', 'shipmentCustomers.handler', 'shipmentCustomers.products']);

        return view('crm.logistics.shipments.show', [
            'shipment'        => $shipment,
            'statuses'        => Shipment::statuses(),
            'custStatuses'    => ShipmentCustomer::statuses(),
            'catalogProducts' => Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'price']),
            'customers'       => $this->customersForPicker(),
            'leads'           => $this->leadsForPicker(),
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        return view('crm.logistics.shipments.edit', [
            'shipment'         => $shipment,
            'statuses'         => Shipment::statuses(),
            'truckingCompanies'=> TruckingCompany::active()->with('drivers')->orderBy('company_name')->get(),
            'crmUsers'         => User::crmMembers()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        $validated = $request->validate([
            'shipment_code'        => ['nullable', 'string', 'max:100', 'unique:shipments,shipment_code,' . $shipment->id],
            'status'               => ['required', 'string', 'in:' . implode(',', array_keys(Shipment::statuses()))],
            'trucking_company_id'  => ['nullable', 'exists:trucking_companies,id'],
            'driver_id'            => ['nullable', Rule::exists('trucking_company_drivers', 'id')->where('trucking_company_id', $request->input('trucking_company_id'))],
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
        abort_unless(auth()->user()->canDeleteCrmRecords('logistic'), 403, 'Only a Logistic Supervisor, CRM Supervisor, or Boss can delete shipments.');

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
            'handled_by'        => ['nullable', 'exists:users,id'],
            'notes'             => ['nullable', 'string'],
            'products'                 => ['nullable', 'array'],
            'products.*.product_id'    => ['nullable', 'exists:products,id'],
            'products.*.product_name'  => ['nullable', 'string', 'max:255'],
            'products.*.price'         => ['nullable', 'numeric', 'min:0'],
            'products.*.quantity'      => ['nullable', 'integer', 'min:1'],
        ]);

        $productRows = $validated['products'] ?? [];
        unset($validated['products']);

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
            return redirect()->route('crm.logistics.shipments.show', $shipment)
                ->withErrors(['recipient_name' => 'Recipient name is required.'])->withInput();
        }

        $validated['shipping_address'] = $validated['shipping_address'] ?? '';

        $shipmentCustomer = $shipment->shipmentCustomers()->create([
            ...$validated,
            'status' => ShipmentCustomer::STATUS_PENDING,
        ]);

        $this->syncShipmentCustomerProducts($shipmentCustomer, $productRows);
        $this->syncShipmentCompletionStatus($shipment);

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
            'status'            => ['required', 'string', 'in:' . implode(',', array_keys(ShipmentCustomer::statuses()))],
            'handled_by'        => ['nullable', 'exists:users,id'],
            'notes'             => ['nullable', 'string'],
            'tracking_number'   => ['nullable', 'string', 'max:150'],
            'products'                 => ['nullable', 'array'],
            'products.*.product_id'    => ['nullable', 'exists:products,id'],
            'products.*.product_name'  => ['nullable', 'string', 'max:255'],
            'products.*.price'         => ['nullable', 'numeric', 'min:0'],
            'products.*.quantity'      => ['nullable', 'integer', 'min:1'],
        ]);

        $productRows = $validated['products'] ?? [];
        unset($validated['products']);

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

        // Only the Problem status (Logistic issues) requires a note — routine
        // transitions like Pending → In Transit → Delivered don't need one.
        if ($validated['status'] === ShipmentCustomer::STATUS_PROBLEM && empty($validated['notes'])) {
            return redirect()->route('crm.logistics.shipments.show', $shipment)
                ->withErrors(['notes' => 'A note is required for Logistic issues (Problem status).'])
                ->withInput();
        }

        $validated['shipping_address'] = $validated['shipping_address'] ?? '';

        $customer->update($validated);

        $this->syncShipmentCustomerProducts($customer, $productRows);

        // Re-run on every save regardless of the new status (not just when
        // it's Problem) — the sync is internally idempotent and re-evaluates
        // ALL of this customer's shipment-customer records, so resolving the
        // last remaining Problem (e.g. moving this one to Delivered) clears
        // the Logistic Issues flag everywhere, while a customer with another
        // still-active Problem shipment correctly keeps the flag set.
        $this->matcher->syncShipmentDelayFlags($customer);
        $this->matcher->syncDeliveryStatus($customer);

        $this->syncShipmentCompletionStatus($shipment);

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Customer record updated.');
    }

    /** Remove a customer from a shipment */
    public function removeCustomer(Shipment $shipment, ShipmentCustomer $customer): RedirectResponse
    {
        $customer->delete();

        $this->syncShipmentCompletionStatus($shipment);

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Customer removed from shipment.');
    }

    /**
     * Replace a shipment customer's recorded product line items with the
     * given rows — each row needs either a catalog product_id (matched via
     * the search datalist on the form) or a manually-typed product_name.
     */
    private function syncShipmentCustomerProducts(ShipmentCustomer $shipmentCustomer, array $rows): void
    {
        $rows = collect($rows)
            ->filter(fn ($row) => ! empty($row['product_id']) || trim($row['product_name'] ?? '') !== '')
            ->values();

        $shipmentCustomer->products()->delete();

        foreach ($rows as $row) {
            $product = ! empty($row['product_id']) ? Product::find($row['product_id']) : null;
            $name = $product?->name ?? trim($row['product_name'] ?? '');

            if ($name === '') {
                continue;
            }

            $shipmentCustomer->products()->create([
                'product_id'   => $product?->id,
                'product_name' => $name,
                'sku'          => $product?->sku,
                'price'        => $row['price'] ?? $product?->price,
                'quantity'     => $row['quantity'] ?? 1,
            ]);
        }
    }

    /**
     * The shipment's own status only ever auto-changes when every one of its
     * customers shares the exact same status — a single customer going to
     * Problem (or Delivered) while others are still pending doesn't collapse
     * the whole shipment into that state. When customers are mixed, the
     * shipment's status is left as-is; the UI shows a per-status count
     * breakdown instead (see Shipment::customerStatusCounts()).
     */
    private function syncShipmentCompletionStatus(Shipment $shipment): void
    {
        $customers = $shipment->shipmentCustomers()->get();

        if ($customers->isEmpty()) {
            return;
        }

        $uniqueStatuses = $customers->pluck('status')->unique();

        if ($uniqueStatuses->count() !== 1) {
            return;
        }

        $shipmentStatus = match ($uniqueStatuses->first()) {
            ShipmentCustomer::STATUS_PENDING    => Shipment::STATUS_PENDING,
            ShipmentCustomer::STATUS_IN_TRANSIT => Shipment::STATUS_IN_PROGRESS,
            ShipmentCustomer::STATUS_DELIVERED  => Shipment::STATUS_COMPLETE,
            ShipmentCustomer::STATUS_PROBLEM    => Shipment::STATUS_PROBLEM,
            default => null,
        };

        if ($shipmentStatus === null || $shipment->status === $shipmentStatus) {
            return;
        }

        $update = ['status' => $shipmentStatus];
        if ($shipmentStatus === Shipment::STATUS_COMPLETE) {
            $update['actual_arrival'] = $shipment->actual_arrival ?? now();
        }
        $shipment->update($update);
    }
}
