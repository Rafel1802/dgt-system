<?php

namespace App\Http\Controllers\CRM;

use App\Enums\CustomerQueue;
use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCustomerRequest;
use App\Http\Requests\Crm\UpdateCustomerRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerWorkflowLog;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\CrmService;
use App\Services\CrmCustomerMatchService;
use App\Services\TechSupportCaseService;
use App\Support\CrmLookupCache;
use App\Support\CrmTeamNotifier;
use App\Support\InstantNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    // Accepts common US/Canada (NANP) formats: (207) 213-9077, 207-213-9077,
    // 207.213.9077, 2072139077, +1 207-213-9077 — matches what
    // PhoneNumberFormatter normalizes on save.
    private const US_PHONE_REGEX = '/^\+?1?[-.\s]?\(?[2-9][0-9]{2}\)?[-.\s]?[0-9]{3}[-.\s]?[0-9]{4}$/';

    // Letters (any language) and spaces only — no digits or symbols.
    private const NAME_REGEX = '/^[\p{L}\s]+$/u';

    public function __construct(
        private readonly CrmService $crmService,
        private readonly CrmCustomerMatchService $matcher,
        private readonly TechSupportCaseService $techSupportCases,
    ) {}

    /**
     * Notify the customer's assigned rep about a change someone else made —
     * mirrors WebsiteCrmController's lead-reassignment notification. No-op
     * when unassigned or when the actor is the assigned rep themselves (no
     * point notifying someone about their own action).
     */
    private function notifyAssignedRep(Customer $customer, string $type, string $message): void
    {
        if (! $customer->assigned_to || $customer->assigned_to === auth()->id()) {
            return;
        }

        $rep = User::find($customer->assigned_to);
        if (! $rep) {
            return;
        }

        InstantNotifier::send($rep, new GenericDatabaseNotification([
            'module'      => 'crm',
            'type'        => $type,
            'customer_id' => $customer->id,
            'message'     => $message,
            'link'        => route('crm.customers.show', $customer),
        ]));
    }

    /** Structured audit entry — reuses the existing activity_logs table/model (see ActivityLog). */
    private function logActivity(string $action, Customer $customer, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'user_id'      => auth()->id(),
            'action'       => $action,
            'module'       => 'crm',
            'description'  => $description,
            'subject_type' => Customer::class,
            'subject_id'   => $customer->id,
            'properties'   => $properties ?: null,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
            'created_at'   => now(),
        ]);
    }

    private const REMEMBERED_FILTER_KEYS = [
        'search', 'status_filter', 'source_filter', 'date_from', 'date_to',
        'created_from', 'created_to', 'sort_by', 'new_only',
        'assigned_to_filter', 'customer_status_filter',
    ];

    /**
     * Customer Database — a single unified list deduped across CRM Website
     * (Leads), eBay, and Logistics-problem records, filterable by the
     * cross-source status categories (Technical issues / Logistic issues /
     * Negative feedback) alongside a free-text search.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        if ($request->boolean('clear_filters')) {
            session()->forget('crm.customers.filters');
            return redirect()->route('crm.customers.index');
        }

        // Remember the last-applied filters in session so they persist while
        // browsing (e.g. navigating to a customer and back) — a request with
        // none of the recognized filter params present re-hydrates from
        // whatever was last saved instead of resetting to "All".
        if ($request->hasAny(self::REMEMBERED_FILTER_KEYS)) {
            session(['crm.customers.filters' => $request->only(self::REMEMBERED_FILTER_KEYS)]);
        } else {
            $request->merge(session('crm.customers.filters', []));
        }

        $stats = $this->crmService->getDashboardStats();

        $sortBy = in_array($request->get('sort_by'), ['created', 'purchase'], true) ? $request->get('sort_by') : 'created';
        $statusFilter = $request->get('status_filter', 'All');
        $sourceFilter = $request->get('source_filter', 'All');
        $customerStatusFilter = $request->get('customer_status_filter', 'All');
        $newOnly = $request->boolean('new_only');
        $assignedToFilter = $request->get('assigned_to_filter');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $createdFrom = $request->get('created_from');
        $createdTo = $request->get('created_to');

        // Raw (no Carbon) path: search only first so totalUnique matches prior semantics
        // (count after search, before status/source/date filters).
        $searched = $this->matcher->buildUnifiedDirectoryRaw([
            'search'  => $request->get('search'),
            'sort_by' => null, // sort after filters — fewer rows to order
        ]);
        $totalUnique = $searched->count();

        $category = match ($statusFilter) {
            'Technical issues'  => 'technical',
            'Logistic issues'   => 'shipment_delay',
            'Negative feedback' => 'negative_feedback',
            default => null,
        };

        // Precompute filter bounds once (unix timestamps) — same day boundaries as Carbon.
        $newSinceTs = $newOnly ? now()->subDays(7)->getTimestamp() : null;
        $purchaseFromTs = $dateFrom ? Carbon::parse($dateFrom)->startOfDay()->getTimestamp() : null;
        $purchaseToTs = $dateTo ? Carbon::parse($dateTo)->endOfDay()->getTimestamp() : null;
        $createdFromTs = $createdFrom ? Carbon::parse($createdFrom)->startOfDay()->getTimestamp() : null;
        $createdToTs = $createdTo ? Carbon::parse($createdTo)->endOfDay()->getTimestamp() : null;
        $assignedToId = $assignedToFilter ? (int) $assignedToFilter : null;

        // Single pass for all list filters (same semantics as the prior chain of filter()).
        $customers = $searched->filter(function (array $c) use (
            $category, $sourceFilter, $customerStatusFilter, $newSinceTs,
            $assignedToId, $purchaseFromTs, $purchaseToTs, $createdFromTs, $createdToTs
        ) {
            if ($category !== null && ($c['category'] ?? null) !== $category) {
                return false;
            }

            if ($sourceFilter === 'eBay' && ($c['source'] ?? null) !== 'eBay') {
                return false;
            }
            if ($sourceFilter === 'Logistics' && ($c['source'] ?? null) !== 'Logistics') {
                return false;
            }
            if ($sourceFilter === 'Website' && in_array($c['source'] ?? null, ['eBay', 'Logistics'], true)) {
                return false;
            }

            // Customer Status — lifecycle label on the row (Lead/Prospect/Active/…).
            if ($customerStatusFilter !== 'All' && ($c['status_label'] ?? null) !== $customerStatusFilter) {
                return false;
            }

            // New Customers — created within the last 7 days.
            if ($newSinceTs !== null) {
                $createdTs = $c['created_ts'] ?? null;
                if ($createdTs === null || (int) $createdTs < $newSinceTs) {
                    return false;
                }
            }

            if ($assignedToId !== null && (int) ($c['handler_id'] ?? 0) !== $assignedToId) {
                return false;
            }

            // Purchase Date and Created Date are deliberately separate filters —
            // a fresh lead with no order yet has no purchase date at all, so
            // purchase filters must never fall back to created_date.
            $purchaseTs = $c['purchase_ts'] ?? null;
            if ($purchaseFromTs !== null && ($purchaseTs === null || (int) $purchaseTs < $purchaseFromTs)) {
                return false;
            }
            if ($purchaseToTs !== null && ($purchaseTs === null || (int) $purchaseTs > $purchaseToTs)) {
                return false;
            }

            $createdTs = $c['created_ts'] ?? null;
            if ($createdFromTs !== null && ($createdTs === null || (int) $createdTs < $createdFromTs)) {
                return false;
            }
            if ($createdToTs !== null && ($createdTs === null || (int) $createdTs > $createdToTs)) {
                return false;
            }

            return true;
        })->values();

        // Sort filtered set only (cheaper than sorting the full directory first).
        $customers = match ($sortBy) {
            'purchase' => $customers->sortByDesc(fn (array $c) => $c['purchase_ts'] ?? -1)->values(),
            default    => $customers->sortByDesc(fn (array $c) => $c['created_ts'] ?? -1)->values(),
        };

        // Paginate, then hydrate Carbon only for the 50 rows in the HTML response.
        $perPage = 50;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $filteredTotal = $customers->count();
        $pageRows = $this->matcher->hydrateDirectoryDates(
            $customers->forPage($page, $perPage)->values()
        );

        $customers = new LengthAwarePaginator(
            $pageRows,
            $filteredTotal,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        $assignableStaff = CrmLookupCache::crmMembers();
        $customerStatuses = CustomerStatus::cases();

        return view('crm.index', compact(
            'stats', 'customers', 'statusFilter', 'sourceFilter', 'totalUnique',
            'dateFrom', 'dateTo', 'createdFrom', 'createdTo', 'sortBy', 'newOnly',
            'assignedToFilter', 'assignableStaff', 'customerStatusFilter', 'customerStatuses'
        ));
    }

    /** Create customer form */
    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('crm.create', [
            'statuses' => CustomerStatus::cases(),
            'sources'  => CustomerSource::cases(),
            'stages'   => DealStage::cases(),
            'users'    => CrmLookupCache::crmMembers(),
        ]);
    }

    /** Store new customer */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Convert comma-separated tags to array
        if (! empty($validated['tags'])) {
            $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
        }

        $duplicate = $this->matcher->findDuplicateCustomer($validated['name'], $validated['email'] ?? null);
        if ($duplicate) {
            return redirect()->route('crm.customers.show', $duplicate)
                ->with('error', "A customer named \"{$duplicate->name}\" with this exact email already exists — opened their profile instead of creating a duplicate.");
        }

        // customers.email is unique at the DB level regardless of name — a
        // same-email-different-name submission still can't become a second
        // row no matter how "different person" the intent above is, or
        // createCustomer() below throws an uncaught unique-constraint
        // QueryException (500) instead of a normal, recoverable redirect.
        if (! empty($validated['email'])) {
            $emailMatch = Customer::whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])->first();
            if ($emailMatch) {
                return redirect()->route('crm.customers.show', $emailMatch)
                    ->with('error', "A customer with this email already exists (\"{$emailMatch->name}\") — opened their profile instead of creating a duplicate.");
            }
        }

        // Phone-only match is a soft warning, not a block — a shared phone
        // number routinely belongs to genuinely different people (household,
        // shared work line), unlike email. Staff see who it matched and
        // explicitly confirm before a second customer is actually created;
        // fixing a typo'd phone and resubmitting re-runs this check fresh.
        if (! empty($validated['phone']) && ! $request->boolean('confirm_duplicate')) {
            $phoneMatch = $this->matcher->findCustomerByPhoneOnly($validated['phone']);
            if ($phoneMatch) {
                return back()->withInput()->with('phoneDuplicateWarning', [
                    'id'   => $phoneMatch->id,
                    'name' => $phoneMatch->name,
                    'link' => route('crm.customers.show', $phoneMatch),
                ]);
            }
        }

        $customer = $this->crmService->createCustomer($validated, auth()->user());

        $this->logActivity('customer.created', $customer, auth()->user()->name . " created customer \"{$customer->name}\".");

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', "Customer \"{$customer->name}\" created successfully.");
    }

    /** Quick-create a minimal customer from CRM forms — reuses an existing match by email/phone instead of duplicating */
    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:' . self::US_PHONE_REGEX],
            'source' => ['nullable', Rule::enum(CustomerSource::class)],
        ], [
            'name.regex'  => 'Name can only contain letters and spaces.',
            'phone.regex' => 'Enter a valid US phone number, e.g. (207) 213-9077.',
        ]);

        $source = $validated['source'] ?? CustomerSource::Website->value;
        unset($validated['source']);

        // Quick-create is a cross-entry-point reuse lookup (eBay New Order,
        // Website Lead, Logistics combobox, ...), not a "same person filled
        // the form twice" duplicate check — it must match by email-or-phone
        // alone regardless of what name was typed this time, or it both
        // spawns a second row for someone already on file AND crashes with
        // a 500 the moment the typed name differs but the email collides
        // with an existing customer (customers.email is unique).
        // findDuplicateCustomer() (name+email together) is for the manual
        // "Add Customer" form's true-duplicate check, not this.
        $customer = $this->matcher->findCustomerByContact($validated['email'] ?? null, $validated['phone'] ?? null);

        if (! $customer) {
            $customer = Customer::create([
                ...$validated,
                'status'         => CustomerStatus::Lead->value,
                'source'         => $source,
                'pipeline_stage' => DealStage::NewLead->value,
                'created_by'     => auth()->id(),
            ]);
            CrmService::forgetDashboardStats();
        }

        return response()->json([
            'id'      => $customer->id,
            'name'    => $customer->name,
            'email'   => $customer->email ?? '',
            'phone'   => $customer->phone ?? '',
            'company' => $customer->company ?? '',
            'address' => $customer->address ?? '',
            'label'   => $customer->name . ($customer->phone ? ' · ' . $customer->phone : ''),
            'text'    => $customer->name . ($customer->phone ? ' · ' . $customer->phone : ''),
        ], 201);
    }

    /** Customer profile view */
    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load([
            'assignee:id,name,avatar',
            'creator:id,name',
            'interactions.user:id,name,avatar',
            'attachments.uploader:id,name',
            'latestTechSupportCase',
            'workflowLogs.mover:id,name',
        ]);

        // Viewing the customer counts as viewing the outcome of any of their
        // technical support cases, same as opening the case itself.
        $this->techSupportCases->markCallCompletedNotificationsRead(
            $customer->techSupportCases()->pluck('id')->all()
        );

        $fullEdit = auth()->user()->hasFullCrmEdit();
        $queues = CustomerQueue::cases();

        return view('crm.show', compact('customer', 'fullEdit', 'queues'));
    }

    /** Edit customer form */
    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('crm.edit', [
            'customer' => $customer,
            'statuses' => CustomerStatus::cases(),
            'sources'  => CustomerSource::cases(),
            'stages'   => DealStage::cases(),
            'users'    => CrmLookupCache::crmMembers(),
            'fullEdit' => auth()->user()->hasFullCrmEdit(),
        ]);
    }

    // Fields a Normal Staff user (crm.status-update, no crm.edit) may change
    // on their own assigned customer — status changes and notes only. See
    // CustomerPolicy::update() for the "own assigned customer" gate.
    private const NORMAL_STAFF_EDITABLE_FIELDS = ['status', 'notes'];

    /** Update customer */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validated();
        $fullEdit = auth()->user()->hasFullCrmEdit();

        if (! $fullEdit) {
            $validated = array_intersect_key($validated, array_flip(self::NORMAL_STAFF_EDITABLE_FIELDS));
        }

        if (! empty($validated['tags'])) {
            $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
        }

        // Captured before update() mutates $customer in place, so the
        // reassignment/status-change checks below compare against the
        // actual prior values, not the just-saved ones.
        $previousAssignedTo = $customer->assigned_to;
        $previousStatus = $customer->status;

        // Field-level diff for the audit log + supervisor/admin notification
        // — computed only over the fields actually being changed (i.e.
        // already whitelisted above for Normal Staff).
        $changes = [];
        foreach ($validated as $field => $newValue) {
            $oldValue = $customer->getAttribute($field);
            $oldComparable = $oldValue instanceof \BackedEnum ? $oldValue->value : $oldValue;
            $newComparable = is_array($newValue) ? json_encode($newValue) : $newValue;
            $oldForCompare = is_array($oldComparable) ? json_encode($oldComparable) : $oldComparable;
            if ((string) $oldForCompare !== (string) $newComparable) {
                $changes[$field] = ['old' => $oldComparable, 'new' => $newValue];
            }
        }

        $this->crmService->updateCustomer($customer, $validated, auth()->user());

        if (! empty($changes)) {
            $this->logActivity(
                'customer.updated',
                $customer,
                auth()->user()->name . " updated customer \"{$customer->name}\" — changed: " . implode(', ', array_keys($changes)) . '.',
                $changes
            );
            CrmTeamNotifier::notifyCustomerUpdated($customer, auth()->user(), $changes);
        }

        if (
            array_key_exists('assigned_to', $validated)
            && $validated['assigned_to']
            && $validated['assigned_to'] !== $previousAssignedTo
            && $validated['assigned_to'] !== auth()->id()
        ) {
            $newRep = User::find($validated['assigned_to']);
            if ($newRep) {
                InstantNotifier::send($newRep, new GenericDatabaseNotification([
                    'module'      => 'crm',
                    'type'        => 'customer_reassigned',
                    'customer_id' => $customer->id,
                    'message'     => auth()->user()->name . " assigned you the customer \"{$customer->name}\".",
                    'link'        => route('crm.customers.show', $customer),
                ]));
            }
        } else {
            // Not a reassignment — a plain info edit. Call out becoming Lost
            // specifically since that's the one status change that actually
            // needs the assigned rep's attention, not just a routine save.
            $becameLost = $customer->status === CustomerStatus::Lost && $previousStatus !== CustomerStatus::Lost;
            $this->notifyAssignedRep(
                $customer,
                $becameLost ? 'customer_lost' : 'customer_updated',
                $becameLost
                    ? auth()->user()->name . " marked \"{$customer->name}\" as Lost."
                    : auth()->user()->name . " updated \"{$customer->name}\"'s details."
            );
        }

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Route a customer to a department queue based on feedback (Technical,
     * Logistics, Sales, Follow-up) — Admin/Supervisor tier only. Writes
     * Customer.current_queue and appends a CustomerWorkflowLog history row,
     * then notifies the target department + assigned rep + Admin/Supervisor.
     */
    public function routeToQueue(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('routeWorkflow', $customer);

        $validated = $request->validate([
            'feedback_category' => ['required', 'string', 'max:100'],
            'to_queue'          => ['required', Rule::enum(CustomerQueue::class)],
            'reason'            => ['nullable', 'string', 'max:1000'],
        ]);

        $queue = CustomerQueue::from($validated['to_queue']);
        $previousQueue = $customer->current_queue;

        $customer->update(['current_queue' => $queue->value]);

        $log = CustomerWorkflowLog::create([
            'customer_id'        => $customer->id,
            'moved_by'           => auth()->id(),
            'feedback_category'  => $validated['feedback_category'],
            'from_queue'         => $previousQueue?->value,
            'to_queue'           => $queue->value,
            'reason'             => $validated['reason'] ?? null,
        ]);

        $this->logActivity(
            'customer.workflow_changed',
            $customer,
            auth()->user()->name . " routed \"{$customer->name}\" to the " . $queue->label() . '.',
            ['old' => $previousQueue?->value, 'new' => $queue->value, 'feedback_category' => $validated['feedback_category'], 'reason' => $log->reason]
        );

        CrmTeamNotifier::notifyQueueRouted($customer, auth()->user(), $queue, $validated['reason'] ?? null);

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', "Customer routed to the {$queue->label()}.");
    }

    /** Permanently delete a customer and all data linked to them across every CRM domain. */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);
        $name = $customer->name;
        $this->crmService->deleteCascading($customer);
        return redirect()->route('crm.customers.index')
            ->with('success', "\"{$name}\" and all related data have been permanently removed.");
    }

    /** Log an interaction (AJAX) */
    public function logInteraction(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('addInteraction', $customer);

        $validated = $request->validate([
            'type'             => ['required', 'string', 'in:call,email,meeting,note,whatsapp,demo'],
            'subject'          => ['nullable', 'string', 'max:255'],
            'content'          => ['required', 'string', 'max:3000'],
            'outcome'          => ['nullable', 'string', 'in:positive,neutral,negative'],
            'interacted_at'    => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
        ]);

        $interaction = $this->crmService->logInteraction($customer, $validated, auth()->user());
        $interaction->load('user:id,name,avatar');

        return response()->json(['success' => true, 'interaction' => $interaction], 201);
    }

    /** Record a purchase against the customer (AJAX) */
    public function recordPurchase(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'value' => ['required', 'numeric', 'min:0.01'],
        ]);

        $customer = $this->crmService->recordPurchase($customer, $validated['value'], auth()->user());
        $interaction = $customer->interactions()->with('user:id,name,avatar')->latest()->first();

        $this->notifyAssignedRep(
            $customer,
            'customer_purchase',
            auth()->user()->name . " recorded a $" . number_format($validated['value'], 2) . " purchase for \"{$customer->name}\"."
        );

        return response()->json([
            'success'           => true,
            'message'           => 'Purchase recorded!',
            'lifetime_value'    => '$' . number_format($customer->lifetime_value, 2),
            'total_orders'      => $customer->total_orders,
            'has_purchased'     => $customer->has_purchased,
            'last_purchase_date' => $customer->last_purchase_date?->format('d M Y'),
            'status_label'      => $customer->status?->label(),
            'status_badge_class' => $customer->status?->badgeClass(),
            'interaction'       => $interaction,
        ]);
    }

    /**
     * Directly correct the purchase summary totals (AJAX) — for fixing a
     * mis-entered lifetime value/order count, not for logging a new
     * purchase (see recordPurchase() above). Supervisor-tier only, same as
     * editing a lead's purchase history on the Website CRM side.
     */
    public function updatePurchaseSummary(Request $request, Customer $customer): JsonResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('website'), 403, 'Only a CRM Supervisor or Boss can edit purchase history.');

        $validated = $request->validate([
            'lifetime_value' => ['required', 'numeric', 'min:0'],
            'total_orders'   => ['required', 'integer', 'min:0'],
        ]);

        $customer->interactions()->create([
            'user_id'       => auth()->id(),
            'type'          => 'note',
            'subject'       => 'Purchase history corrected',
            'content'       => sprintf(
                'Purchase summary corrected by %s: lifetime value %s → %s, total orders %d → %d.',
                auth()->user()->name,
                number_format($customer->lifetime_value, 2),
                number_format($validated['lifetime_value'], 2),
                $customer->total_orders,
                $validated['total_orders']
            ),
            'outcome'       => 'neutral',
            'interacted_at' => now(),
        ]);

        $customer->update([
            'lifetime_value' => $validated['lifetime_value'],
            'total_orders'   => $validated['total_orders'],
            'has_purchased'  => $validated['total_orders'] > 0,
        ]);

        $this->notifyAssignedRep(
            $customer,
            'customer_purchase_edited',
            auth()->user()->name . " corrected the purchase history for \"{$customer->name}\"."
        );

        return response()->json([
            'success'        => true,
            'message'        => 'Purchase history updated.',
            'lifetime_value' => '$' . number_format($customer->lifetime_value, 2),
            'total_orders'   => $customer->total_orders,
            'has_purchased'  => $customer->has_purchased,
        ]);
    }

    /** Pipeline stage update (AJAX drag-drop from pipeline view) */
    public function updateStage(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'pipeline_stage' => ['required', Rule::enum(DealStage::class)],
        ]);

        $customer->update(['pipeline_stage' => $validated['pipeline_stage']]);

        return response()->json(['success' => true, 'stage' => $customer->fresh()->pipeline_stage]);
    }

    /** Upload an attachment to a customer */
    public function uploadAttachment(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $request->validate([
            'attachment' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,gif',
                'max:51200', // 50MB in Kilobytes
            ],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('attachment');
        $originalName = $file->getClientOriginalName();
        $mime = $file->getClientMimeType();
        $size = $file->getSize();

        $path = $file->store('customer_attachments', 'public');

        $customer->attachments()->create([
            'uploaded_by'   => auth()->id(),
            'filename'      => basename($path),
            'original_name' => $originalName,
            'mime_type'     => $mime,
            'file_size'     => $size,
            'disk'          => 'public',
            'path'          => $path,
            'label'         => $request->get('label') ?: null,
        ]);

        return back()->with('success', 'File uploaded successfully.');
    }
}
