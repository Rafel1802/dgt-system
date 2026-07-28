<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentCustomer;
use App\Models\TruckingCompany;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use App\Services\SimpleXlsxReader;
use App\Support\CrmLookupCache;
use App\Support\CrmTeamNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentController extends Controller
{
    public function __construct(private CrmCustomerMatchService $matcher)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->get('status');
        $query = Shipment::with(['truckingCompany', 'assignee'])->withCount('shipmentCustomers');

        if ($s = $request->get('search')) {
            $query->search($s);
        }

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

    /** Process Trucking — every ShipmentCustomer still Pending, across all shipments (including unassigned). */
    public function processTrucking(Request $request): View
    {
        return $this->truckingQueue($request, [ShipmentCustomer::STATUS_PENDING], 'processing');
    }

    /** Loaded — every ShipmentCustomer already Loaded or In Delivery, across all shipments. */
    public function loaded(Request $request): View
    {
        return $this->truckingQueue($request, [ShipmentCustomer::STATUS_IN_TRANSIT, ShipmentCustomer::STATUS_IN_DELIVERY], 'loaded');
    }

    /** Delivered — every ShipmentCustomer marked Delivered, across all shipments. */
    public function delivered(Request $request): View
    {
        return $this->truckingQueue($request, [ShipmentCustomer::STATUS_DELIVERED], 'delivered');
    }

    /**
     * Shared customer-grain queue behind Process Trucking / Loaded / Delivered
     * — this is what powers bulk-selecting several customers (possibly from
     * different shipments, or none yet) and moving them all to the next
     * status, a shipment, or deleting them at once, none of which map onto a
     * single shipment's own status the way the shipment-grain list above does.
     */
    private function truckingQueue(Request $request, array $customerStatuses, string $mode): View
    {
        $customerQuery = ShipmentCustomer::with(['shipment', 'handler', 'products'])
            ->whereIn('status', $customerStatuses);

        // Delivered Customers page (spec: Delivered Customer page) also
        // surfaces the linked Customer record — Purchase Date and Follow-up
        // History (CustomerInteraction) — since neither lives on
        // ShipmentCustomer itself.
        if ($mode === 'delivered') {
            $customerQuery->with(['customer' => fn ($q) => $q->with(['interactions' => fn ($i) => $i->limit(5)])]);
        }

        if ($s = $request->get('search')) {
            $customerQuery->search($s);
        }

        if ($mode === 'delivered') {
            // ShipmentCustomer has no dedicated delivered_at column — updated_at
            // is the last status-change timestamp, which for a Delivered row is
            // effectively its delivery date.
            $sortBy = in_array($request->get('sort_by'), ['delivery', 'purchase'], true) ? $request->get('sort_by') : 'delivery';
            if ($sortBy === 'purchase') {
                $customerQuery->leftJoin('customers', 'customers.id', '=', 'shipment_customers.customer_id')
                    ->orderByDesc('customers.first_purchase_date')
                    ->select('shipment_customers.*');
            } else {
                $customerQuery->latest('updated_at');
            }
        } else {
            $sortBy = null;
            $customerQuery->latest();
        }

        $shipmentCustomers = $customerQuery->paginate(20)->withQueryString();

        return view('crm.logistics.trucking-queue', [
            'mode'              => $mode,
            'shipmentCustomers' => $shipmentCustomers,
            'sortBy'            => $sortBy,
            // For the bulk "Add to Shipment" picker — active shipments only,
            // since assigning into an already-Complete one isn't useful.
            'assignableShipments' => Shipment::where('status', '!=', Shipment::STATUS_COMPLETE)
                ->latest()->limit(100)->get(['id', 'shipment_code']),
        ]);
    }

    /** CSV export for the Delivered Customers page — Search/filter apply the same as the on-screen list. */
    public function exportDelivered(Request $request): StreamedResponse
    {
        $customerQuery = ShipmentCustomer::with(['shipment', 'handler', 'customer'])
            ->where('status', ShipmentCustomer::STATUS_DELIVERED);

        if ($s = $request->get('search')) {
            $customerQuery->search($s);
        }

        $rows = $customerQuery->latest('updated_at')->get();

        $headers = ['Recipient Name', 'Phone', 'Email', 'Address', 'Purchase Date', 'Delivery Date', 'Assigned Staff', 'Delivery Status', 'Notes'];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->recipient_name,
                    $row->recipient_phone,
                    $row->recipient_email,
                    $row->shipping_address,
                    $row->customer?->first_purchase_date?->format('Y-m-d'),
                    $row->updated_at?->format('Y-m-d'),
                    $row->handler?->name,
                    ShipmentCustomer::statuses()[$row->status] ?? $row->status,
                    $row->notes,
                ], ',', '"', '\\');
            }
            fclose($out);
        }, 'delivered-customers.csv', ['Content-Type' => 'text/csv']);
    }

    /** Every customer currently flagged with a logistics/shipment issue, across all sources. */
    public function issues(Request $request): View
    {
        // Raw directory (timestamps only) → filter → hydrate just this page.
        $all = $this->matcher->buildUnifiedDirectoryRaw(['search' => $request->get('search')])
            ->filter(fn (array $c) => ($c['category'] ?? null) === 'shipment_delay')
            ->values();

        // Paginate issue list so large fleets don't render thousands of rows.
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 50;
        $customers = new \Illuminate\Pagination\LengthAwarePaginator(
            $this->matcher->hydrateDirectoryDates($all->forPage($page, $perPage)->values()),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('crm.logistics.issues', compact('customers'));
    }

    public function create(): View
    {
        return view('crm.logistics.shipments.create', [
            'statuses'         => Shipment::statuses(),
            // Drivers still needed for the create form picker — keep live load.
            'truckingCompanies'=> TruckingCompany::active()->with('drivers')->orderBy('company_name')->get(),
            'crmUsers'         => CrmLookupCache::crmMembers(),
            'customers'        => CrmLookupCache::customersCombobox(),
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
        // Lean eager loads: only the latest order per channel (not full histories).
        // Cache PLAIN ARRAYS only — never Eloquent models (file cache on Hostinger
        // returns __PHP_Incomplete_Class and 500s shipment show/create flows).
        $builder = function (): array {
            return Customer::orderBy('name')
                ->with([
                    'latestLogistic.product:id,name',
                    'ebayCustomerRecords:id,customer_id',
                    // latestOfMany must not be column-restricted (ambiguous FK subqueries).
                    'ebayCustomerRecords.latestOrder.items:id,ebay_customer_order_id,product_name',
                    'leads' => fn ($q) => $q->orderByDesc('received_at')
                        ->select(['id', 'customer_id', 'product_id', 'product_interested', 'received_at']),
                    'leads.latestOrder.items:id,lead_order_id,product_name',
                    'leads.product:id,name',
                ])
                ->get(['id', 'name', 'email', 'phone', 'company', 'address'])
                ->map(function (Customer $customer) {
                    return [
                        'id'                   => $customer->id,
                        'name'                 => $customer->name,
                        'email'                => $customer->email,
                        'phone'                => $customer->phone,
                        'company'              => $customer->company,
                        'address'              => $customer->address,
                        'latest_order_product' => $this->latestOrderProductFor($customer),
                    ];
                })
                ->values()
                ->all();
        };

        $rows = app()->runningUnitTests()
            ? $builder()
            : Cache::remember('crm.shipment_picker_customers.v2', 45, $builder);

        return collect($rows)->map(fn (array $r) => (object) $r);
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
            ->map(fn ($record) => $record->latestOrder)
            ->filter()
            ->sortByDesc('ordered_at')
            ->first();
        $candidates->push([
            'date'    => $latestEbayOrder?->ordered_at,
            'product' => $latestEbayOrder ? $latestEbayOrder->items->pluck('product_name')->filter()->implode(', ') : null,
        ]);

        $latestLeadOrder = $customer->leads
            ->map(fn ($lead) => $lead->latestOrder)
            ->filter()
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
        // Plain objects for the combobox — same shape as customersForPicker.
        return Lead::whereNull('customer_id') // already-converted leads have a fuller Customer record — pick that instead
            ->orderBy('client_name')
            ->with(['latestOrder.items', 'product'])
            ->get(['id', 'customer_id', 'client_name', 'client_phone', 'client_email', 'product_interested', 'product_id'])
            ->map(function (Lead $lead) {
                $orderProduct = $lead->latestOrder?->items->pluck('product_name')->filter()->implode(', ');

                return (object) [
                    'id'                   => $lead->id,
                    'client_name'          => $lead->client_name,
                    'client_phone'         => $lead->client_phone,
                    'client_email'         => $lead->client_email,
                    'product_interested'   => $lead->product_interested,
                    'latest_order_product' => $orderProduct ?: ($lead->product->name ?? $lead->product_interested),
                ];
            })
            ->values();
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
            'shipment_code' => $validated['shipment_code'] ?? $this->generateShipmentCode(),
            'created_by'    => auth()->id(),
        ]);

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Shipment #' . ($shipment->shipment_code ?? $shipment->id) . ' created.');
    }

    /**
     * "DDMM-YYYY-N", N being how many shipments have already been created
     * today (so it resets daily rather than growing forever) — e.g.
     * "1507-2026-1", then "1507-2026-2" for the next one created the same day.
     */
    private function generateShipmentCode(): string
    {
        $createdToday = Shipment::whereDate('created_at', now()->toDateString())->count();

        return now()->format('dm') . '-' . now()->format('Y') . '-' . ($createdToday + 1);
    }

    public function show(Shipment $shipment, Request $request): View
    {
        $shipment->load(['truckingCompany', 'driver', 'creator', 'assignee', 'shipmentCustomers.customer', 'shipmentCustomers.handler', 'shipmentCustomers.products']);

        return view('crm.logistics.shipments.show', [
            'shipment'        => $shipment,
            'statuses'        => Shipment::statuses(),
            'custStatuses'    => ShipmentCustomer::statuses(),
            'catalogProducts' => CrmLookupCache::activeProducts(),
            'customers'       => $this->customersForPicker(),
            'leads'           => $this->leadsForPicker(),
            // For "Add from Process Trucking" — unassigned records, searchable
            // client-side; capped since this is a live-typed search, not a full list.
            'unassignedCustomers' => ShipmentCustomer::whereNull('shipment_id')
                ->with('products')
                ->latest()->limit(300)->get(),
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        return view('crm.logistics.shipments.edit', [
            'shipment'         => $shipment,
            'statuses'         => Shipment::statuses(),
            'truckingCompanies'=> TruckingCompany::active()->with('drivers')->orderBy('company_name')->get(),
            'crmUsers'         => CrmLookupCache::crmMembers(),
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
        abort_unless(auth()->user()->canDeleteCrmRecords('logistic'), 403, 'Only a Logistic Supervisor, eBay Supervisor, CRM Supervisor, or Boss can delete shipments.');

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

        // Captured before update() so the notification below only fires on
        // an actual transition into Problem, not on every re-save of a
        // shipment customer that was already flagged.
        $becameProblem = $validated['status'] === ShipmentCustomer::STATUS_PROBLEM
            && $customer->status !== ShipmentCustomer::STATUS_PROBLEM;

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

        // Keep the linked Customer record's own contact/address info in
        // sync with whatever staff just edited here.
        $this->matcher->syncEditedShipmentCustomer($customer);

        $this->syncShipmentCompletionStatus($shipment);

        // eBay and Website/Sales CRM staff both deal with this same
        // customer outside of Logistics — a shipment problem is worth them
        // knowing about immediately, same as Tech Support status changes
        // and eBay negative feedback.
        if ($becameProblem) {
            CrmTeamNotifier::notifyEbayAndSalesTeams(
                'logistic_problem',
                "Logistic issue · {$customer->recipient_name}",
                route('crm.logistics.issues.index'),
                auth()->id()
            );
        }

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Customer record updated.');
    }

    /** Remove a customer from a shipment */
    public function removeCustomer(Shipment $shipment, ShipmentCustomer $customer): RedirectResponse
    {
        $customerId = $customer->customer_id;

        $customer->delete();

        $this->matcher->maybeDeleteOrphanedCustomer($customerId);

        $this->syncShipmentCompletionStatus($shipment);

        return redirect()->route('crm.logistics.shipments.show', $shipment)
            ->with('success', 'Customer removed from shipment.');
    }

    /**
     * Delete a customer directly from the Process Trucking / Loaded tabs —
     * not nested under a {shipment} route since these records often aren't
     * assigned to one yet. Still syncs the parent shipment's own status if
     * this customer happened to already belong to one.
     */
    public function destroyCustomer(Request $request, ShipmentCustomer $customer): RedirectResponse
    {
        $shipment = $customer->shipment;
        $customerId = $customer->customer_id;

        $customer->delete();

        $this->matcher->maybeDeleteOrphanedCustomer($customerId);

        if ($shipment) {
            $this->syncShipmentCompletionStatus($shipment);
        }

        return $this->redirectAfterBulkAction(null, $request->get('redirect_status', 'processing'))
            ->with('success', 'Customer deleted.');
    }

    /**
     * Bulk status change across several ShipmentCustomer rows at once —
     * possibly spanning multiple shipments, which is why this isn't nested
     * under a single {shipment}. Powers the Process Trucking / Loaded tabs:
     * select several customers still Pending and mark them all Loaded (In
     * Transit) together, instead of opening each shipment individually.
     */
    public function bulkUpdateCustomerStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_ids'        => ['required', 'array', 'min:1'],
            'customer_ids.*'      => ['integer', 'exists:shipment_customers,id'],
            'status'              => ['required', 'string', 'in:' . implode(',', array_keys(ShipmentCustomer::statuses()))],
            'notes'               => ['nullable', 'string'],
            'redirect_status'     => ['nullable', 'string'],
            'redirect_shipment_id'=> ['nullable', 'exists:shipments,id'],
        ]);

        // Same rule as the single-customer update: Problem needs a note
        // explaining the issue, applied to every selected customer.
        if ($validated['status'] === ShipmentCustomer::STATUS_PROBLEM && empty($validated['notes'])) {
            return back()->withErrors(['notes' => 'A note is required for Logistic issues (Problem status).']);
        }

        $customers = ShipmentCustomer::whereIn('id', $validated['customer_ids'])->get();

        foreach ($customers as $customer) {
            $customer->update(array_filter([
                'status' => $validated['status'],
                'notes'  => $validated['notes'] ?? null,
            ], fn ($v) => $v !== null));

            $this->matcher->syncShipmentDelayFlags($customer);
            $this->matcher->syncDeliveryStatus($customer);
        }

        foreach ($customers->pluck('shipment_id')->filter()->unique() as $shipmentId) {
            $shipment = Shipment::find($shipmentId);
            if ($shipment) {
                $this->syncShipmentCompletionStatus($shipment);
            }
        }

        $label = ShipmentCustomer::statuses()[$validated['status']] ?? $validated['status'];

        return $this->redirectAfterBulkAction($validated['redirect_shipment_id'] ?? null, $validated['redirect_status'] ?? 'processing')
            ->with('success', $customers->count() . ' customer(s) marked as ' . $label . '.');
    }

    /**
     * Bulk-delete several ShipmentCustomer rows at once — from either the
     * Process Trucking / Loaded tabs (spanning shipments, possibly
     * unassigned) or a single shipment's own customer list.
     */
    public function bulkDeleteCustomers(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_ids'        => ['required', 'array', 'min:1'],
            'customer_ids.*'      => ['integer', 'exists:shipment_customers,id'],
            'redirect_status'     => ['nullable', 'string'],
            'redirect_shipment_id'=> ['nullable', 'exists:shipments,id'],
        ]);

        $customers = ShipmentCustomer::whereIn('id', $validated['customer_ids'])->get();
        $affectedShipmentIds = $customers->pluck('shipment_id')->filter()->unique();
        $affectedCustomerIds = $customers->pluck('customer_id')->filter()->unique();
        $count = $customers->count();

        ShipmentCustomer::whereIn('id', $validated['customer_ids'])->delete();

        foreach ($affectedCustomerIds as $customerId) {
            $this->matcher->maybeDeleteOrphanedCustomer($customerId);
        }

        foreach ($affectedShipmentIds as $shipmentId) {
            $shipment = Shipment::find($shipmentId);
            if ($shipment) {
                $this->syncShipmentCompletionStatus($shipment);
            }
        }

        return $this->redirectAfterBulkAction($validated['redirect_shipment_id'] ?? null, $validated['redirect_status'] ?? 'processing')
            ->with('success', $count . ' customer(s) deleted.');
    }

    /**
     * Assign several existing ShipmentCustomer rows (typically unassigned
     * Process Trucking records) to a shipment at once — used both by the
     * Process Trucking bulk bar ("Add to Shipment") and by the "Add from
     * Process Trucking" search on a shipment's own page.
     */
    /**
     * Assign selected customers to a shipment — either an existing active
     * one, or a brand new one created on the spot (shipment_id left blank).
     * Used both by the Process Trucking / Loaded bulk bar ("Add to
     * Shipment") and by the "Add from Process Trucking" search on a
     * shipment's own page.
     */
    public function assignCustomersToShipment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_ids'        => ['required', 'array', 'min:1'],
            'customer_ids.*'      => ['integer', 'exists:shipment_customers,id'],
            'shipment_id'         => ['nullable', 'exists:shipments,id'],
            'new_shipment_code'   => ['nullable', 'string', 'max:100'],
            'redirect_status'     => ['nullable', 'string'],
            'redirect_shipment_id'=> ['nullable', 'exists:shipments,id'],
        ]);

        $shipment = ! empty($validated['shipment_id'])
            ? Shipment::find($validated['shipment_id'])
            : Shipment::create([
                'shipment_code' => ($validated['new_shipment_code'] ?? null) ?: $this->generateShipmentCode(),
                'status'        => Shipment::STATUS_PENDING,
                'created_by'    => auth()->id(),
            ]);

        $count = ShipmentCustomer::whereIn('id', $validated['customer_ids'])->count();

        ShipmentCustomer::whereIn('id', $validated['customer_ids'])
            ->update(['shipment_id' => $shipment->id]);

        $this->syncShipmentCompletionStatus($shipment);

        return $this->redirectAfterBulkAction($validated['redirect_shipment_id'] ?? null, $validated['redirect_status'] ?? 'processing')
            ->with('success', $count . ' customer(s) assigned to shipment ' . ($shipment->shipment_code ?? "#{$shipment->id}") . '.');
    }

    /** Shared redirect target for the bulk customer actions above. */
    private function redirectAfterBulkAction(?int $redirectShipmentId, string $redirectStatus): RedirectResponse
    {
        if ($redirectShipmentId) {
            $shipment = Shipment::find($redirectShipmentId);
            if ($shipment) {
                return redirect()->route('crm.logistics.shipments.show', $shipment);
            }
        }

        return match ($redirectStatus) {
            'loaded'    => redirect()->route('crm.logistics.loaded'),
            'delivered' => redirect()->route('crm.logistics.delivered'),
            default     => redirect()->route('crm.logistics.processTrucking'),
        };
    }

    /** Column header aliases the importer recognizes, matched case-insensitively (used only for the multi-column template path). */
    private const IMPORT_COLUMN_ALIASES = [
        'recipient_name'   => ['recipient name', 'name'],
        'address_line'     => ['address line', 'address'],
        'city'              => ['city'],
        'state'             => ['state'],
        'zip'               => ['zip', 'zip code', 'postal code'],
        'country'           => ['country'],
        'phone'             => ['phone'],
        'email'             => ['email'],
        'product_name'      => ['product name', 'product'],
        'sku'               => ['sku'],
        'quantity'          => ['quantity', 'qty'],
        'tracking_number'   => ['tracking number', 'tracking', 'order reference', 'order id', 'reference'],
        'notes'             => ['notes', 'note'],
    ];

    /** Download the blank CSV template for the Process Trucking import (opens fine in Excel/Sheets). */
    public function downloadImportTemplate(): StreamedResponse
    {
        $headers = ['Recipient Name', 'Address Line', 'City', 'State', 'Zip', 'Country', 'Phone', 'Email', 'Product Name', 'SKU', 'Quantity', 'Tracking Number', 'Notes'];
        $example = ['David Mileski', '14 Rons Way', 'Pittston', 'ME', '04345-5996', 'United States', '+1 207-213-9077', '', 'Tool Box', 'TYPH-1901', 1, '2026071407TYPH', ''];

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ',', '"', '\\');
            fputcsv($out, $example, ',', '"', '\\');
            fclose($out);
        }, 'process-trucking-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Parse an uploaded .xlsx/.csv file into an editable preview held in the
     * session (not written to the DB yet) and send staff to the preview
     * page — nothing is imported until they review/edit and confirm there.
     * Accepts two shapes: a raw single-column export (one shipping-label
     * block per recipient, separated by blank rows — the same layout as the
     * files this team already works with) or the multi-column template with
     * a header row. Detected automatically by how many columns row 1 has.
     * Imported rows are NOT tied to any Shipment — they land as standalone
     * Process Trucking records; grouping them into an actual shipment is a
     * separate, later step, not something the import decides for you.
     */
    public function previewImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        $extension = strtolower($validated['file']->getClientOriginalExtension());

        try {
            $rows = $extension === 'xlsx'
                ? SimpleXlsxReader::read($validated['file']->getRealPath())
                : $this->readCsvFile($validated['file']->getRealPath());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Could not read the file: ' . $e->getMessage()]);
        }

        if (empty($rows)) {
            return back()->withErrors(['file' => 'The file appears to be empty.']);
        }

        // Try the labeled-header template first; only a real "Recipient Name"
        // column match counts as that format. Anything else — including a
        // raw export whose first row just happens to have multiple filled
        // cells (e.g. two shipping labels side by side) — falls back to the
        // block parser instead of failing outright.
        $previewRows = $this->parseColumnFormat($rows) ?? $this->parseBlockFormat($rows);

        if (empty($previewRows)) {
            return back()->withErrors(['file' => 'No data rows found in the file.']);
        }

        session(['shipment_import_preview' => ['rows' => $previewRows]]);

        return redirect()->route('crm.logistics.shipments.customers.import.preview');
    }

    /** Multi-column template path: a header row plus one row per shipment customer. Returns null if no Recipient Name column is found. */
    private function parseColumnFormat(array $rows): ?array
    {
        $columnMap = $this->mapImportColumns(array_shift($rows));

        if (! isset($columnMap['recipient_name'])) {
            return null;
        }

        $previewRows = [];
        foreach ($rows as $row) {
            if (empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                continue; // fully blank row (e.g. a spacer) — skip silently
            }

            $data = [];
            foreach (self::IMPORT_COLUMN_ALIASES as $field => $labels) {
                $data[$field] = isset($columnMap[$field], $row[$columnMap[$field]]) ? trim((string) $row[$columnMap[$field]]) : '';
            }
            $data['quantity'] = $data['quantity'] !== '' ? $data['quantity'] : '1';
            $data['country'] = $data['country'] !== '' ? $data['country'] : 'United States';

            $previewRows[] = $data;
        }

        return $previewRows;
    }

    /**
     * Raw shipping-label export: one recipient block per column, blocks
     * within a column separated by that column's own blank cells. Usually
     * there's just one data-bearing column (a plain single-column export),
     * but some exports lay two labels side by side per printed page —
     * recipient A in column 0, recipient B in column 7, for example — so
     * every column that has data anywhere in the file is walked as its own
     * independent recipient stream. Each column's blank/non-blank pattern is
     * used on its own, NOT a shared "this row is blank across every column"
     * check — side-by-side labels are commonly offset by a row or more from
     * their neighbor (one label a line shorter than the one beside it), so
     * there's often no row that's blank in every column at once between two
     * blocks in different columns.
     *
     * Within a block, line 1 is always the name and line 2 the street
     * address; every other line is classified by pattern (phone, email,
     * "City, ST ZIP", country, tracking number, SKU) since block shape
     * varies — e.g. Walmart-relay blocks skip the country line and use a
     * relay email instead of a separate phone+country pair; some blocks
     * omit the product name line entirely. Whatever doesn't match a known
     * pattern becomes notes. This is a best-effort heuristic — staff
     * review/correct it on the preview page before anything is saved, so an
     * occasional misparse isn't destructive.
     */
    private function parseBlockFormat(array $rows): array
    {
        $dataColumns = [];
        foreach ($rows as $row) {
            foreach ($row as $idx => $value) {
                if (trim((string) $value) !== '') {
                    $dataColumns[$idx] = true;
                }
            }
        }
        $dataColumns = array_keys($dataColumns);
        sort($dataColumns);

        $previewRows = [];
        foreach ($dataColumns as $col) {
            $lines = array_map(function ($row) use ($col) {
                $value = $row[$col] ?? null;
                return $value === null ? '' : trim((string) $value);
            }, $rows);

            $blocks = [];
            $current = [];
            foreach ($lines as $line) {
                if ($line === '') {
                    if (! empty($current)) {
                        $blocks[] = $current;
                        $current = [];
                    }
                    continue;
                }
                $current[] = $line;
            }
            if (! empty($current)) {
                $blocks[] = $current;
            }

            foreach ($blocks as $block) {
                if (count($block) < 2) {
                    continue; // not enough data to be a real recipient (e.g. a stray note cell)
                }
                $previewRows[] = $this->classifyBlockLines($block);
            }
        }

        return $previewRows;
    }

    /** @return array<string,string> one parsed shipment-customer row */
    private function classifyBlockLines(array $lines): array
    {
        $data = [
            'recipient_name' => '', 'address_line' => '', 'city' => '', 'state' => '', 'zip' => '',
            'country' => '', 'phone' => '', 'email' => '', 'product_name' => '', 'sku' => '',
            'quantity' => '1', 'tracking_number' => '', 'notes' => '',
        ];

        if (empty($lines)) {
            return $data;
        }

        $data['recipient_name'] = array_shift($lines);

        $notesParts = [];

        foreach ($lines as $i => $rawLine) {
            // Strip non-breaking spaces (\xc2\xa0), which show up in some
            // "Phone: <nbsp><number>" exports and would otherwise defeat
            // the phone-pattern check below.
            $clean = trim(str_replace("\xc2\xa0", ' ', $rawLine));

            if ($i === 0) {
                $data['address_line'] = $clean;
                continue;
            }

            if (str_contains($clean, '@')) {
                $data['email'] = $clean;
                continue;
            }

            if (preg_match('/^united states$/i', $clean)) {
                $data['country'] = 'United States';
                continue;
            }

            // "City, State Zip" — has a comma and ends with zip-like digits.
            if (empty($data['city']) && str_contains($clean, ',') && preg_match('/\d{4,}/', $clean)) {
                $parts = array_map('trim', explode(',', $clean));
                $data['city'] = $parts[0] ?? '';
                $stateZip = trim($parts[1] ?? '');
                if (preg_match('/^([A-Za-z\.]+)\s+([\d\-]{4,})$/', $stateZip, $m)) {
                    $data['state'] = $m[1];
                    $data['zip'] = $m[2];
                } elseif (isset($parts[2])) {
                    $data['state'] = $stateZip;
                    $data['zip'] = trim($parts[2]);
                } else {
                    $data['state'] = $stateZip;
                }
                continue;
            }

            $phoneCandidate = rtrim(preg_replace('/^phone:\s*/i', '', $clean), '*');
            $digitCount = strlen(preg_replace('/\D/', '', $phoneCandidate));
            if (empty($data['phone']) && $digitCount >= 7 && preg_match('/^[\d\s\+\-\(\)\.]+$/', $phoneCandidate)) {
                $data['phone'] = trim($phoneCandidate);
                continue;
            }

            // Tracking number: long, mostly-digit, optional short letter suffix (e.g. "2026071407TYPH").
            if (empty($data['tracking_number']) && preg_match('/^\d{8,}[A-Za-z]{0,8}$/', $clean)) {
                $data['tracking_number'] = $clean;
                continue;
            }

            // SKU: hyphenated code, letters+digits, reasonably short (e.g. "TYPH-1901 PRO").
            // The SKU is what identifies the actual product ordered — a
            // generic accessory line like "Tool Box" that ships alongside
            // it is NOT the product, so it isn't auto-assigned as one; it
            // falls through to notes below, where staff can see it as-is
            // without it mislabeling what was actually ordered.
            if (empty($data['sku']) && strlen($clean) <= 30 && preg_match('/^[A-Za-z0-9]+-[A-Za-z0-9 ]+$/', $clean)) {
                $data['sku'] = $clean;
                continue;
            }

            $notesParts[] = $clean;
        }

        // Country is essentially always "United States" for this team's
        // orders, and several export formats (e.g. Walmart relay) never
        // include an explicit country line at all — default it instead of
        // leaving it blank for staff to fill in on every single row.
        if ($data['country'] === '') {
            $data['country'] = 'United States';
        }

        $data['notes'] = implode(' — ', $notesParts);

        return $data;
    }

    /** Editable review page for a pending import — nothing in the DB yet. */
    public function showImportPreview(): View|RedirectResponse
    {
        $preview = session('shipment_import_preview');

        if (empty($preview['rows'])) {
            return redirect()->route('crm.logistics.processTrucking')
                ->withErrors(['file' => 'No import in progress — please upload a file first.']);
        }

        return view('crm.logistics.shipments.import-preview', [
            'rows' => $preview['rows'],
        ]);
    }

    /**
     * Actually create the Process Trucking customer records from the
     * (possibly staff-edited) preview data. Rows the staff removed from the
     * preview page simply never arrive here, since their form fields left
     * the DOM. Deliberately does NOT create or assign a Shipment — these
     * land as standalone Pending records; grouping them into an actual
     * shipment is a separate, later, manual step.
     */
    public function confirmImport(Request $request): RedirectResponse
    {
        if (empty(session('shipment_import_preview.rows'))) {
            return redirect()->route('crm.logistics.processTrucking')
                ->withErrors(['file' => 'No import in progress — please upload a file first.']);
        }

        // Only the outer shape is validated here — per-row field validity is
        // checked row-by-row inside the loop below (via Validator::make())
        // so one malformed row (e.g. a non-numeric quantity) is skipped
        // individually instead of 422-ing the entire submission.
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
        ]);

        $rowRules = [
            'recipient_name'  => ['nullable', 'string', 'max:255'],
            'address_line'    => ['nullable', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:255'],
            'state'           => ['nullable', 'string', 'max:255'],
            'zip'             => ['nullable', 'string', 'max:50'],
            'country'         => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'product_name'    => ['nullable', 'string', 'max:255'],
            'sku'             => ['nullable', 'string', 'max:100'],
            'quantity'        => ['nullable', 'integer', 'min:1'],
            'tracking_number' => ['nullable', 'string', 'max:150'],
            'notes'           => ['nullable', 'string'],
        ];

        $imported = 0;
        $skipped = [];
        $failedRows = [];
        $duplicateCount = 0;
        $seenTrackingNumbers = [];

        DB::transaction(function () use ($request, $rowRules, &$imported, &$skipped, &$failedRows, &$duplicateCount, &$seenTrackingNumbers) {
            foreach ($request->input('rows', []) as $i => $data) {
                $rowNumber = $i + 1;

                $validator = \Illuminate\Support\Facades\Validator::make($data, $rowRules);
                if ($validator->fails()) {
                    $reason = implode(' ', $validator->errors()->all());
                    $skipped[] = "Row {$rowNumber}: {$reason}";
                    $failedRows[] = $data + ['row' => $rowNumber, 'error' => $reason];
                    continue;
                }
                $data = $validator->validated();

                if (empty($data['recipient_name'])) {
                    $reason = 'missing Recipient Name.';
                    $skipped[] = "Row {$rowNumber}: {$reason}";
                    $failedRows[] = $data + ['row' => $rowNumber, 'error' => $reason];
                    continue;
                }

                // A tracking number is the one field that uniquely identifies a
                // real-world shipping label — re-uploading the same export (or a
                // file with an accidentally duplicated block) must not create a
                // second record for the same label. Checked both within this
                // batch and against everything ever imported before, since a
                // duplicate could span two separate import sessions just as
                // easily as one file. Rows with no detected tracking number
                // skip this check entirely — there's no reliable key to dedupe
                // on, and blocking on name/phone alone would wrongly reject a
                // legitimate repeat order from a returning customer.
                $trackingNumber = $data['tracking_number'] ?? null;
                if (! empty($trackingNumber)) {
                    if (isset($seenTrackingNumbers[$trackingNumber]) || ShipmentCustomer::where('tracking_number', $trackingNumber)->exists()) {
                        $reason = "tracking number {$trackingNumber} already imported — skipped as duplicate.";
                        $skipped[] = "Row {$rowNumber}: {$reason}";
                        $failedRows[] = $data + ['row' => $rowNumber, 'error' => $reason];
                        $duplicateCount++;
                        continue;
                    }
                    $seenTrackingNumbers[$trackingNumber] = true;
                }

                $address = implode(', ', array_filter([
                    $data['address_line'] ?? null,
                    $data['city'] ?? null,
                    $data['state'] ?? null,
                    $data['zip'] ?? null,
                    $data['country'] ?? null,
                ]));

                $shipmentCustomer = ShipmentCustomer::create([
                    'shipment_id'      => null,
                    'recipient_name'   => $data['recipient_name'],
                    'recipient_phone'  => $data['phone'] ?? null,
                    'recipient_email'  => $data['email'] ?? null,
                    'shipping_address' => $address,
                    'status'           => ShipmentCustomer::STATUS_PENDING,
                    'handled_by'       => auth()->id(),
                    'tracking_number'  => $data['tracking_number'] ?? null,
                    'notes'            => ! empty($data['notes']) ? $data['notes'] : null,
                ]);

                if (! empty($data['product_name']) || ! empty($data['sku'])) {
                    $product = ! empty($data['sku']) ? Product::where('sku', $data['sku'])->first() : null;

                    $shipmentCustomer->products()->create([
                        'product_id'   => $product?->id,
                        'product_name' => $product->name ?? ($data['product_name'] ?: $data['sku']),
                        'sku'          => $data['sku'] ?? $product?->sku,
                        'quantity'     => $data['quantity'] ?: 1,
                    ]);
                }

                // Every imported recipient lands in the Customer database: if
                // their phone or email matches an existing Customer, link the
                // two and refresh their info/status; otherwise create a new
                // Customer record for them (source: Logistic) so Process
                // Trucking imports aren't a data dead-end for people who've
                // never come through the website or eBay.
                $this->matcher->syncImportedCustomer($shipmentCustomer);

                $imported++;
            }
        });

        session()->forget('shipment_import_preview');
        session(['shipment_import_failed_rows' => $failedRows]);

        ActivityLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'logistics.import',
            'module'       => 'crm',
            'description'  => auth()->user()->name . " ran a logistics import — {$imported} imported, " . count($skipped) . ' skipped.',
            'subject_type' => null,
            'subject_id'   => null,
            'properties'   => [
                'imported'  => $imported,
                'skipped'   => count($skipped),
                'duplicates' => $duplicateCount,
            ],
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'created_at'   => now(),
        ]);

        // Import summary — success count, duplicate count, and the first few
        // failure reasons up front; the full failed-row list stays available
        // to download as CSV via downloadFailedImportRows() below.
        $message = "{$imported} customer(s) imported into Process Trucking.";
        if ($duplicateCount > 0) {
            $message .= " {$duplicateCount} duplicate(s) skipped.";
        }
        $otherSkipped = count($skipped) - $duplicateCount;
        if ($otherSkipped > 0) {
            $message .= " {$otherSkipped} invalid row(s) skipped.";
        }
        if (! empty($skipped)) {
            $shown = array_slice($skipped, 0, 5);
            $message .= ' Details: ' . implode(' ', $shown) . (count($skipped) > 5 ? ' …' : '')
                . ' — download the failed rows to review all of them.';
        }

        return redirect()->route('crm.logistics.processTrucking')
            ->with($imported > 0 ? 'success' : 'error', $message);
    }

    /** Download the rows skipped/failed in the most recent import as CSV, for review/re-upload. */
    public function downloadFailedImportRows(): StreamedResponse|RedirectResponse
    {
        $failedRows = session('shipment_import_failed_rows', []);

        if (empty($failedRows)) {
            return redirect()->route('crm.logistics.processTrucking')
                ->withErrors(['file' => 'No failed rows to download — run an import first.']);
        }

        $headers = ['Row', 'Recipient Name', 'Address Line', 'City', 'State', 'Zip', 'Country', 'Phone', 'Email', 'Product Name', 'SKU', 'Quantity', 'Tracking Number', 'Notes', 'Error'];

        return response()->streamDownload(function () use ($failedRows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ',', '"', '\\');
            foreach ($failedRows as $row) {
                fputcsv($out, [
                    $row['row'] ?? '',
                    $row['recipient_name'] ?? '',
                    $row['address_line'] ?? '',
                    $row['city'] ?? '',
                    $row['state'] ?? '',
                    $row['zip'] ?? '',
                    $row['country'] ?? '',
                    $row['phone'] ?? '',
                    $row['email'] ?? '',
                    $row['product_name'] ?? '',
                    $row['sku'] ?? '',
                    $row['quantity'] ?? '',
                    $row['tracking_number'] ?? '',
                    $row['notes'] ?? '',
                    $row['error'] ?? '',
                ], ',', '"', '\\');
            }
            fclose($out);
        }, 'import-failed-rows.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string,int> import field key => source column index, matched against IMPORT_COLUMN_ALIASES */
    private function mapImportColumns(array $header): array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $map = [];

        foreach (self::IMPORT_COLUMN_ALIASES as $field => $labels) {
            foreach ($normalized as $index => $label) {
                if (in_array($label, $labels, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /** @return array<int, array<int, string|null>> */
    private function readCsvFile(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }

        return $rows;
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
            ShipmentCustomer::STATUS_PENDING     => Shipment::STATUS_PENDING,
            ShipmentCustomer::STATUS_IN_TRANSIT,
            ShipmentCustomer::STATUS_IN_DELIVERY => Shipment::STATUS_IN_PROGRESS,
            ShipmentCustomer::STATUS_DELIVERED   => Shipment::STATUS_COMPLETE,
            ShipmentCustomer::STATUS_PROBLEM     => Shipment::STATUS_PROBLEM,
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
