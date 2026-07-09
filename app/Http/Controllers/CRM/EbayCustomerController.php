<?php

namespace App\Http\Controllers\CRM;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EbayCustomerFollowUp;
use App\Models\EbayCustomerHandlerHistory;
use App\Models\EbayCustomerOrder;
use App\Models\EbayCustomerRecord;
use App\Models\EbayCustomerStatusHistory;
use App\Models\EbayStore;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EbayCustomerController extends Controller
{
    public function __construct(private readonly CrmCustomerMatchService $matcher)
    {
    }

    /** One combined list across all 6 statuses; status is a filter, not a URL segment */
    public function index(Request $request): View
    {
        $query = EbayCustomerRecord::with(['store', 'creator']);

        $tabType = $request->get('tab_type');
        if ($tabType && array_key_exists($tabType, EbayCustomerRecord::tabs())) {
            $query->forTab($tabType);
        }
        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($storeId = $request->get('store_id')) {
            $query->where('ebay_store_id', $storeId);
        }

        // Sort by most-recently-updated (not created) so a record whose status
        // you just changed floats to the top of its new filtered view instead
        // of staying buried wherever it was chronologically created.
        $records = $query->latest('updated_at')->paginate(30)->withQueryString();
        $stores  = EbayStore::active()->orderBy('store_name')->get();
        $tabs    = EbayCustomerRecord::tabs();

        return view('crm.ebay.customers.index', compact('records', 'stores', 'tabs', 'tabType'));
    }

    public function create(): View
    {
        return view('crm.ebay.customers.create', [
            'tabs'           => EbayCustomerRecord::tabs(),
            'stores'         => EbayStore::active()->orderBy('store_name')->get(),
            'customers'      => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone', 'company', 'address']),
            'negativeCauses' => EbayCustomerRecord::NEGATIVE_FEEDBACK_CAUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedRecord($request);

        $existing = $this->matcher->findEbayRecordByUsernameOrContact(
            $validated['username'] ?? null,
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
        );

        if ($existing) {
            return redirect()->route('crm.ebay.customers.edit', $existing)
                ->with('error', "A record for \"{$existing->username}\" already exists — update its status below instead of creating a duplicate.");
        }

        $orderData = $this->validatedNewOrder($request);

        $validated['customer_id'] = $this->resolveOrCreateCustomer($validated);
        $validated['created_by'] = auth()->id();

        if ($orderData) {
            $validated['order_id'] = $orderData['order_id'];
            $validated['ebay_store_id'] = $orderData['order_store_id'] ?? $validated['ebay_store_id'] ?? null;
        }

        if ($validated['tab_type'] === EbayCustomerRecord::TAB_URGENT) {
            $validated['n'] = EbayCustomerRecord::forTab(EbayCustomerRecord::TAB_URGENT)->max('n') + 1;
        }

        $record = EbayCustomerRecord::create($validated);

        EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id'                 => auth()->id(),
            'started_at'              => now(),
        ]);

        EbayCustomerStatusHistory::create([
            'ebay_customer_record_id' => $record->id,
            'status'                  => $record->tab_type,
            'changed_by'              => auth()->id(),
            'changed_at'              => now(),
        ]);

        if ($orderData) {
            $this->createOrder($record, $orderData);
        }

        return redirect()->route('crm.ebay.customers.index', ['tab_type' => $record->tab_type])
            ->with('success', 'Record added.');
    }

    public function show(EbayCustomerRecord $record): View
    {
        $record->load([
            'handlerHistory.user',
            'statusHistory.changedBy',
            'followUps.user',
            'orders' => fn ($q) => $q->with(['items', 'store']),
            'store', 'customer',
        ]);

        return view('crm.ebay.customers.show', [
            'record'   => $record,
            'tabs'     => EbayCustomerRecord::tabs(),
            'stores'   => EbayStore::active()->orderBy('store_name')->get(),
            'crmUsers' => User::crmMembers()->orderBy('name')->get(),
        ]);
    }

    public function edit(EbayCustomerRecord $record): View
    {
        return view('crm.ebay.customers.edit', [
            'record'         => $record,
            'tabs'           => EbayCustomerRecord::tabs(),
            'stores'         => EbayStore::active()->orderBy('store_name')->get(),
            'customers'      => Customer::orderBy('name')->get(['id', 'name', 'email', 'phone', 'company', 'address']),
            'negativeCauses' => EbayCustomerRecord::NEGATIVE_FEEDBACK_CAUSES,
        ]);
    }

    public function update(Request $request, EbayCustomerRecord $record): RedirectResponse
    {
        $validated = $this->validatedRecord($request);
        $validated['customer_id'] = $this->resolveOrCreateCustomer($validated, $record);
        $validated['updated_by'] = auth()->id();

        if (array_key_exists('negative_feedback_resolved', $validated)) {
            $becameResolved = $validated['negative_feedback_resolved'] && ! $record->negative_feedback_resolved;
            $validated['negative_feedback_resolved_at'] = $becameResolved ? now()->toDateString() : $record->negative_feedback_resolved_at;
        }

        $statusChanged = $validated['tab_type'] !== $record->tab_type;
        $becomingNewOrder = $statusChanged && $validated['tab_type'] === EbayCustomerRecord::TAB_NEW_ORDER;

        // Switching status to New Order requires the same order/product details as creating one
        $orderData = $becomingNewOrder ? $request->validate($this->orderFieldRules()) : null;

        $record->update($validated);

        if ($statusChanged) {
            EbayCustomerStatusHistory::create([
                'ebay_customer_record_id' => $record->id,
                'status'                  => $validated['tab_type'],
                'changed_by'              => auth()->id(),
                'changed_at'              => now(),
            ]);
        }

        if ($orderData) {
            $this->createOrder($record, $orderData);
        }

        return redirect()->route('crm.ebay.customers.index', ['tab_type' => $record->tab_type])
            ->with('success', 'Record updated.');
    }

    public function destroy(EbayCustomerRecord $record): RedirectResponse
    {
        $tabType = $record->tab_type;
        $record->delete();

        return redirect()->route('crm.ebay.customers.index', ['tab_type' => $tabType])
            ->with('success', 'Record deleted.');
    }

    /** Close the current handler-history entry and open a new one for the selected staff member */
    public function switchHandler(Request $request, EbayCustomerRecord $record): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'exists:users,id']]);

        $now = now();
        $record->handlerHistory()->whereNull('ended_at')->update(['ended_at' => $now]);

        $entry = EbayCustomerHandlerHistory::create([
            'ebay_customer_record_id' => $record->id,
            'user_id'                 => $validated['user_id'],
            'started_at'              => $now,
        ]);

        return response()->json([
            'message' => 'Handler updated.',
            'handler' => $entry->load('user'),
        ]);
    }

    /** Log a follow-up note against this customer record */
    public function logFollowUp(Request $request, EbayCustomerRecord $record): JsonResponse
    {
        $validated = $request->validate(['notes' => ['required', 'string']]);

        $followUp = EbayCustomerFollowUp::create([
            'ebay_customer_record_id' => $record->id,
            'user_id'                 => auth()->id(),
            'notes'                   => $validated['notes'],
            'contacted_at'            => now(),
        ]);

        return response()->json([
            'message'   => 'Follow-up logged.',
            'follow_up' => $followUp->load('user'),
        ]);
    }

    /** Log another purchased order for an existing customer, from the record's Purchase History */
    public function storeOrder(Request $request, EbayCustomerRecord $record): JsonResponse
    {
        $orderData = $request->validate($this->orderFieldRules());

        $order = $this->createOrder($record, $orderData);

        return response()->json([
            'message' => 'Order added.',
            'order'   => $order->load('items', 'store'),
        ]);
    }

    /** Create an order (+ its manually-entered product line items) and sync the record's quick-glance order fields */
    private function createOrder(EbayCustomerRecord $record, array $orderData): EbayCustomerOrder
    {
        $order = EbayCustomerOrder::create([
            'ebay_customer_record_id' => $record->id,
            'order_id'                => $orderData['order_id'],
            'ebay_store_id'           => $orderData['order_store_id'] ?? null,
            'ordered_at'              => $orderData['order_date'] ?? now(),
            'created_by'              => auth()->id(),
        ]);

        foreach ($orderData['products'] ?? [] as $product) {
            if (filled($product['name'] ?? null)) {
                $order->items()->create([
                    'product_name' => $product['name'],
                    'price'        => $product['price'] ?? null,
                ]);
            }
        }

        $record->update([
            'order_id'      => $order->order_id,
            'ebay_store_id' => $order->ebay_store_id ?? $record->ebay_store_id,
        ]);

        return $order;
    }

    /**
     * The simplified, single-form field set: Full Name, Username (required),
     * Status/Category, Email, Phone, Store, Reason (Status = Cancelation
     * Client, stored in `summary`), Note (stored in `informations`), and
     * negative-feedback causes/resolved when Status is one of the two
     * negative-feedback categories. Order/product fields are handled
     * separately by validatedNewOrder() — orders live in their own table.
     */
    private function validatedRecord(Request $request): array
    {
        $rules = [
            'customer_id'   => ['nullable', 'exists:customers,id'],
            'tab_type'      => ['required', Rule::in(array_keys(EbayCustomerRecord::tabs()))],
            'buyer_name'    => ['nullable', 'string', 'max:255'],
            'username'      => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'ebay_store_id' => ['nullable', 'exists:ebay_stores,id'],
            'summary'       => ['nullable', 'string'],
            'informations'  => ['nullable', 'string'],
        ];

        if (in_array($request->input('tab_type'), [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES])) {
            $rules['negative_feedback_causes']   = ['nullable', 'array'];
            $rules['negative_feedback_causes.*'] = [Rule::in(EbayCustomerRecord::NEGATIVE_FEEDBACK_CAUSES)];
            $rules['negative_feedback_resolved'] = ['nullable', 'boolean'];
        }

        return $request->validate($rules);
    }

    /**
     * When creating a record as "New Order" — or switching an existing
     * record's status to New Order — the order/product fields are a
     * repeatable, manually-entered product list (no DB product lookup, since
     * eBay pricing varies per sale) plus which store the order was placed
     * through. Returns null when the record isn't being created as a new order.
     */
    private function validatedNewOrder(Request $request): ?array
    {
        if ($request->input('tab_type') !== EbayCustomerRecord::TAB_NEW_ORDER) {
            return null;
        }

        return $request->validate($this->orderFieldRules());
    }

    /** Shared order/product validation: Order ID + at least one product with a name and price. */
    private function orderFieldRules(): array
    {
        return [
            'order_id'          => ['required', 'string', 'max:100'],
            'order_date'        => ['nullable', 'date'],
            'order_store_id'    => ['nullable', 'exists:ebay_stores,id'],
            'products'          => ['required', 'array', 'min:1'],
            'products.*.name'   => ['required', 'string', 'max:255'],
            'products.*.price'  => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * If an existing customer was searched/selected, use it as-is. Otherwise
     * look for an existing Customer matching the submitted email/phone (so a
     * typed-but-not-selected match doesn't spawn a duplicate) and reuse it;
     * only create a new Customer record when truly nothing matches — unless
     * the record already has one (edit, unchanged).
     */
    private function resolveOrCreateCustomer(array $validated, ?EbayCustomerRecord $record = null): int
    {
        if (! empty($validated['customer_id'])) {
            return (int) $validated['customer_id'];
        }

        if ($record?->customer_id) {
            return $record->customer_id;
        }

        $existing = $this->matcher->findCustomerByContact($validated['email'] ?? null, $validated['phone'] ?? null);
        if ($existing) {
            return $existing->id;
        }

        $customer = Customer::create([
            'name'       => ($validated['buyer_name'] ?? null) ?: $validated['username'],
            'email'      => $validated['email'] ?? null,
            'phone'      => $validated['phone'] ?? null,
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Ebay->value,
            'created_by' => auth()->id(),
        ]);

        return $customer->id;
    }
}
