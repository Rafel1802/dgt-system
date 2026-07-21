<?php

namespace App\Http\Controllers\CRM;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DealStage;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\CrmService;
use App\Services\CrmCustomerMatchService;
use App\Services\TechSupportCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * Customer Database — a single unified list deduped across CRM Website
     * (Leads), eBay, and Logistics-problem records, filterable by the
     * cross-source status categories (Technical issues / Logistic issues /
     * Negative feedback) alongside a free-text search.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $stats = $this->crmService->getDashboardStats();

        $all = $this->matcher->buildUnifiedDirectory(['search' => $request->get('search')]);
        $totalUnique = $all->count();

        $statusFilter = $request->get('status_filter', 'All');
        $category = match ($statusFilter) {
            'Technical issues'  => 'technical',
            'Logistic issues'   => 'shipment_delay',
            'Negative feedback' => 'negative_feedback',
            default => null,
        };
        $customers = $category ? $all->filter(fn ($c) => $c['category'] === $category) : $all;

        $sourceFilter = $request->get('source_filter', 'All');
        $customers = match ($sourceFilter) {
            'eBay'      => $customers->filter(fn ($c) => $c['source'] === 'eBay'),
            'Logistics' => $customers->filter(fn ($c) => $c['source'] === 'Logistics'),
            'Website'   => $customers->filter(fn ($c) => ! in_array($c['source'], ['eBay', 'Logistics'], true)),
            default     => $customers,
        };

        // Purchase Date and Created Date are deliberately separate filters —
        // a fresh lead with no order yet has no purchase date at all (see
        // CrmCustomerMatchService::buildUnifiedDirectory()), so filtering by
        // purchase date must never fall back to matching on when they were
        // created instead.
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        if ($dateFrom) {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $customers = $customers->filter(fn ($c) => $c['purchase_date'] && $c['purchase_date']->gte($from));
        }
        if ($dateTo) {
            $to = Carbon::parse($dateTo)->endOfDay();
            $customers = $customers->filter(fn ($c) => $c['purchase_date'] && $c['purchase_date']->lte($to));
        }

        $createdFrom = $request->get('created_from');
        $createdTo = $request->get('created_to');
        if ($createdFrom) {
            $from = Carbon::parse($createdFrom)->startOfDay();
            $customers = $customers->filter(fn ($c) => $c['created_date'] && $c['created_date']->gte($from));
        }
        if ($createdTo) {
            $to = Carbon::parse($createdTo)->endOfDay();
            $customers = $customers->filter(fn ($c) => $c['created_date'] && $c['created_date']->lte($to));
        }

        return view('crm.index', compact('stats', 'customers', 'statusFilter', 'sourceFilter', 'totalUnique', 'dateFrom', 'dateTo', 'createdFrom', 'createdTo'));
    }

    /** Create customer form */
    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('crm.create', [
            'statuses' => CustomerStatus::cases(),
            'sources'  => CustomerSource::cases(),
            'stages'   => DealStage::cases(),
            'users'    => User::crmMembers()->get(['id', 'name']),
        ]);
    }

    /** Store new customer */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            // No blanket email uniqueness here — a duplicate is specifically a
            // name+email match together (see findDuplicateCustomer() below);
            // the same email under a different name is a different person.
            'email'             => ['nullable', 'email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30', 'regex:' . self::US_PHONE_REGEX],
            'company'           => ['nullable', 'string', 'max:255'],
            'job_title'         => ['nullable', 'string', 'max:100'],
            'website'           => ['nullable', 'url', 'max:255'],
            'country'           => ['nullable', 'string', 'max:10'],
            'state'             => ['nullable', 'string', 'max:100'],
            'city'              => ['nullable', 'string', 'max:100'],
            'address'           => ['nullable', 'string', 'max:500'],
            'postcode'          => ['nullable', 'string', 'max:20'],
            'status'            => ['required', Rule::enum(CustomerStatus::class)],
            'source'            => ['nullable', Rule::enum(CustomerSource::class)],
            'pipeline_stage'    => ['nullable', Rule::enum(DealStage::class)],
            'product_interests' => ['nullable', 'array'],
            'product_interests.*' => ['string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:5000'],
            'assigned_to'       => ['nullable', 'integer', 'exists:users,id'],
            'tags'              => ['nullable', 'string'],
        ], [
            'name.regex'  => 'Name can only contain letters and spaces.',
            'phone.regex' => 'Enter a valid US phone number, e.g. (207) 213-9077.',
        ]);

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

        $customer = $this->crmService->createCustomer($validated, auth()->user());

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
        ]);

        // Viewing the customer counts as viewing the outcome of any of their
        // technical support cases, same as opening the case itself.
        $this->techSupportCases->markCallCompletedNotificationsRead(
            $customer->techSupportCases()->pluck('id')->all()
        );

        return view('crm.show', compact('customer'));
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
            'users'    => User::crmMembers()->get(['id', 'name']),
        ]);
    }

    /** Update customer */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255', 'regex:' . self::NAME_REGEX],
            'email'             => ['nullable', 'email', 'max:255', "unique:customers,email,{$customer->id}"],
            'phone'             => ['nullable', 'string', 'max:30', 'regex:' . self::US_PHONE_REGEX],
            'company'           => ['nullable', 'string', 'max:255'],
            'job_title'         => ['nullable', 'string', 'max:100'],
            'website'           => ['nullable', 'url', 'max:255'],
            'country'           => ['nullable', 'string', 'max:10'],
            'state'             => ['nullable', 'string', 'max:100'],
            'city'              => ['nullable', 'string', 'max:100'],
            'address'           => ['nullable', 'string', 'max:500'],
            'status'            => ['required', Rule::enum(CustomerStatus::class)],
            'source'            => ['nullable', Rule::enum(CustomerSource::class)],
            'pipeline_stage'    => ['nullable', Rule::enum(DealStage::class)],
            'product_interests' => ['nullable', 'array'],
            'notes'             => ['nullable', 'string', 'max:5000'],
            'assigned_to'       => ['nullable', 'integer', 'exists:users,id'],
            'tags'              => ['nullable', 'string'],
        ], [
            'name.regex'  => 'Name can only contain letters and spaces.',
            'phone.regex' => 'Enter a valid US phone number, e.g. (207) 213-9077.',
        ]);

        if (! empty($validated['tags'])) {
            $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
        }

        $this->crmService->updateCustomer($customer, $validated, auth()->user());

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
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
