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
use App\Models\Product;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use App\Services\CrmService;
use App\Services\TechSupportCaseService;
use App\Support\CrmLookupCache;
use App\Support\CrmTeamNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EbayCustomerController extends Controller
{
    public function __construct(
        private readonly CrmCustomerMatchService $matcher,
        private readonly CrmService $crmService,
        private readonly TechSupportCaseService $techSupportCases,
    ) {}

    /** One combined list across all 6 statuses; status is a filter, not a URL segment */
    public function index(Request $request): View
    {
        $query = EbayCustomerRecord::with([
            'store:id,store_name',
            'creator:id,name',
            'customer:id,name',
            // Do not column-restrict morph techSupportCase (ambiguous source_* on SQLite/MySQL).
            'techSupportCase',
        ]);

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
        $stores  = CrmLookupCache::activeEbayStores();
        $tabs    = EbayCustomerRecord::tabs();

        return view('crm.ebay.customers.index', compact('records', 'stores', 'tabs', 'tabType'));
    }

    public function create(): View
    {
        return view('crm.ebay.customers.create', [
            'tabs'           => EbayCustomerRecord::tabs(),
            'stores'         => CrmLookupCache::activeEbayStores(),
            'customers'      => CrmLookupCache::customersCombobox(),
            'negativeCauses' => EbayCustomerRecord::NEGATIVE_FEEDBACK_CAUSES,
            'catalogProducts'=> CrmLookupCache::activeProducts(false),
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
        Cache::forget('crm.pending_handler_confirms.' . auth()->id());

        EbayCustomerStatusHistory::create([
            'ebay_customer_record_id' => $record->id,
            'status'                  => $record->tab_type,
            'changed_by'              => auth()->id(),
            'changed_at'              => now(),
        ]);

        if ($orderData) {
            $this->createOrder($record, $orderData);
        }

        return $this->redirectForNegativeFeedbackCause($record)
            ?? redirect()->route('crm.ebay.customers.index', ['tab_type' => $record->tab_type])
                ->with('success', 'Record added.');
    }

    public function show(EbayCustomerRecord $record): View
    {
        $record->load([
            'handlerHistory.user',
            'statusHistory.changedBy',
            'followUps.user',
            'orders' => fn ($q) => $q->with(['items', 'store']),
            'store', 'customer', 'techSupportCase',
        ]);

        // Viewing this customer's eBay record counts as viewing the outcome
        // of any of their technical support cases (not just the one sourced
        // from this record — a customer can also have a case via a Website
        // lead), same as opening the customer's own profile.
        $this->techSupportCases->markCallCompletedNotificationsRead(
            $record->customer_id
                ? TechSupportCase::where('customer_id', $record->customer_id)->pluck('id')->all()
                : []
        );

        return view('crm.ebay.customers.show', [
            'record'         => $record,
            'tabs'           => EbayCustomerRecord::tabs(),
            'stores'         => CrmLookupCache::activeEbayStores(),
            'crmUsers'       => CrmLookupCache::crmMembers(),
            'catalogProducts'=> CrmLookupCache::activeProducts(false),
        ]);
    }

    public function edit(EbayCustomerRecord $record): View
    {
        return view('crm.ebay.customers.edit', [
            'record'         => $record,
            'tabs'           => EbayCustomerRecord::tabs(),
            'stores'         => CrmLookupCache::activeEbayStores(),
            'customers'      => CrmLookupCache::customersCombobox(),
            'negativeCauses' => EbayCustomerRecord::NEGATIVE_FEEDBACK_CAUSES,
            'catalogProducts'=> CrmLookupCache::activeProducts(false),
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

        // Marking the whole record Resolved should also resolve any negative
        // feedback still tracked on it — negative_feedback_resolved is a
        // separate flag that's only ever included above while the record
        // sits in one of the negative-feedback categories, so moving it
        // straight to Resolved from there left that flag stuck on
        // "Unresolved" forever, contradicting the record's own status badge.
        if ($statusChanged && $validated['tab_type'] === EbayCustomerRecord::TAB_RESOLVED
            && $record->negative_feedback_causes && ! $record->negative_feedback_resolved) {
            $validated['negative_feedback_resolved'] = true;
            $validated['negative_feedback_resolved_at'] = now()->toDateString();
        }

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

        // Sales/Website CRM staff and the rest of the eBay team both deal
        // with this same customer outside this one record — a negative
        // feedback flag is worth them knowing about immediately, same as
        // Tech Support status changes and Logistic problems.
        if ($statusChanged && in_array($validated['tab_type'], [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES], true)) {
            $customerName = $record->buyer_name ?: $record->username;
            $priority = $validated['tab_type'] === EbayCustomerRecord::TAB_NEGATIVES ? 'Negative feedback' : 'Potential negative';
            CrmTeamNotifier::notifyEbayAndSalesTeams(
                'ebay_negative_feedback',
                "{$customerName}: {$priority}",
                route('crm.ebay.customers.show', $record),
                auth()->id()
            );
        }

        if ($orderData) {
            $this->createOrder($record, $orderData);
        }

        return $this->redirectForNegativeFeedbackCause($record)
            ?? redirect()->route('crm.ebay.customers.index', ['tab_type' => $record->tab_type])
                ->with('success', 'Record updated.');
    }

    /**
     * When a record is saved into one of the negative-feedback categories,
     * route staff straight to whichever department the cause(s) actually
     * point at — Technical/Logistic issues are someone else's queue to
     * work from, not something staff would otherwise think to go check.
     * "Customer service" has no dedicated queue of its own, so that (and no
     * cause selected at all) falls through to the normal list redirect.
     * When more than one cause is checked, Technical wins over Logistic
     * issues over Customer service — most specific/urgent first, same
     * ordering validatedRecord()'s note-required rule already treats these
     * categories by.
     */
    private function redirectForNegativeFeedbackCause(EbayCustomerRecord $record): ?RedirectResponse
    {
        if (! in_array($record->tab_type, [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES], true)) {
            return null;
        }

        $causes = $record->negative_feedback_causes ?? [];

        return match (true) {
            in_array('Technical', $causes, true) => redirect()->route('crm.tech-support.index')
                ->with('success', 'Record saved — routed to Technical Support (negative feedback cause).'),
            in_array('Logistic issues', $causes, true) => redirect()->route('crm.logistics.issues.index')
                ->with('success', 'Record saved — routed to Logistic Issues (negative feedback cause).'),
            default => null,
        };
    }

    /**
     * Deleting an eBay customer record that's linked to a Customer
     * permanently deletes that customer too, cascading everything tied to
     * them across every CRM domain (leads, other eBay records, shipments,
     * tech support cases) — same as deleting from the Customer Database
     * page directly, and the same rule Lead deletion already follows.
     * Otherwise the customer row (and their data) would be left behind and
     * keep showing up on the Customer Database page.
     */
    public function destroy(EbayCustomerRecord $record): RedirectResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('ebay'), 403, 'Only an eBay Supervisor, Logistic Supervisor, CRM Supervisor, or Boss can delete eBay records.');

        $tabType = $record->tab_type;

        if ($record->customer) {
            $customerName = $record->customer->name;
            $this->crmService->deleteCascading($record->customer);

            return redirect()->route('crm.ebay.customers.index', ['tab_type' => $tabType])
                ->with('success', "Record deleted — \"{$customerName}\" and all their related data have been permanently removed too.");
        }

        $record->delete();

        return redirect()->route('crm.ebay.customers.index', ['tab_type' => $tabType])
            ->with('success', 'Record deleted.');
    }

    /**
     * Close the current handler-history entry and open a new one for the
     * selected staff member. The new assignment starts unconfirmed —
     * surfaced in the assignee's profile dropdown (not the notification
     * bell) with a Confirm action, see confirmHandler() below.
     */
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
        Cache::forget('crm.pending_handler_confirms.' . $validated['user_id']);

        return response()->json([
            'message' => 'Handler updated.',
            'handler' => $entry->load('user'),
        ]);
    }

    /** The assigned handler confirms they've seen/accepted this assignment. */
    public function confirmHandler(EbayCustomerHandlerHistory $entry): RedirectResponse
    {
        abort_unless($entry->user_id === auth()->id(), 403, 'Only the assigned handler can confirm this assignment.');

        $entry->update(['confirmed_at' => now()]);
        Cache::forget('crm.pending_handler_confirms.' . $entry->user_id);

        return redirect()->route('crm.ebay.customers.show', $entry->ebay_customer_record_id)
            ->with('success', 'Assignment confirmed.');
    }

    /**
     * A staff member's full handler-assignment history — every switch-handler
     * event that ever put a customer record in their hands, not just the
     * still-pending confirmations shown in the profile dropdown. Own history
     * only; there's no cross-staff view here.
     */
    public function handlerHistory(): View
    {
        $entries = EbayCustomerHandlerHistory::where('user_id', auth()->id())
            ->with('record')
            ->latest('started_at')
            ->paginate(30);

        return view('crm.ebay.customers.handler-history', compact('entries'));
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

    /** A staff member can delete their own follow-up entries — not anyone else's, so the notes log stays an honest record of who said what. */
    public function destroyFollowUp(EbayCustomerRecord $record, EbayCustomerFollowUp $followUp): RedirectResponse
    {
        abort_unless($followUp->ebay_customer_record_id === $record->id, 404);
        abort_unless($followUp->user_id === auth()->id(), 403, 'You can only delete your own follow-up entries.');

        $followUp->delete();

        return redirect()->route('crm.ebay.customers.show', $record)->with('success', 'Follow-up entry deleted.');
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
            'ordered_at'              => $orderData['order_date'],
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
        $isIssueCategory = in_array($request->input('tab_type'), [
            EbayCustomerRecord::TAB_TECHNICAL,
            EbayCustomerRecord::TAB_POT_NEGATIVES,
            EbayCustomerRecord::TAB_NEGATIVES,
        ]);

        $rules = [
            'customer_id'   => ['nullable', 'exists:customers,id'],
            'tab_type'      => ['required', Rule::in(array_keys(EbayCustomerRecord::tabs()))],
            'buyer_name'    => ['nullable', 'string', 'max:255'],
            'username'      => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'ebay_store_id' => ['nullable', 'exists:ebay_stores,id'],
            'summary'       => ['nullable', 'string'],
            // Technical Issues / Negative Feedback categories require a note
            // explaining the issue — every other category leaves it optional.
            'informations'  => $isIssueCategory ? ['required', 'string'] : ['nullable', 'string'],
        ];

        if (in_array($request->input('tab_type'), [EbayCustomerRecord::TAB_POT_NEGATIVES, EbayCustomerRecord::TAB_NEGATIVES])) {
            $rules['negative_feedback_causes']   = ['nullable', 'array'];
            $rules['negative_feedback_causes.*'] = [Rule::in(EbayCustomerRecord::NEGATIVE_FEEDBACK_CAUSES)];
            $rules['negative_feedback_resolved'] = ['nullable', 'boolean'];
        }

        return $request->validate($rules, [
            'informations.required' => 'A note is required for Technical Issues and Negative Feedback records.',
        ]);
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
            'order_date'        => ['required', 'date'],
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

        $name = ($validated['buyer_name'] ?? null) ?: $validated['username'];

        $existing = $this->matcher->findDuplicateCustomer($name, $validated['email'] ?? null);
        if ($existing) {
            return $existing->id;
        }

        $customer = Customer::create([
            'name'       => $name,
            'email'      => $validated['email'] ?? null,
            'phone'      => $validated['phone'] ?? null,
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Ebay->value,
            'created_by' => auth()->id(),
        ]);

        return $customer->id;
    }
}
