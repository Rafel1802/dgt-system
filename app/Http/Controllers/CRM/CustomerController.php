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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CrmService $crmService,
        private readonly CrmCustomerMatchService $matcher,
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
            'eBay'    => $customers->filter(fn ($c) => $c['source'] === 'eBay'),
            'Website' => $customers->filter(fn ($c) => $c['source'] !== 'eBay'),
            default   => $customers,
        };

        return view('crm.index', compact('stats', 'customers', 'statusFilter', 'sourceFilter', 'totalUnique'));
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
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['nullable', 'email', 'unique:customers,email', 'max:255'],
            'phone'             => ['nullable', 'string', 'max:30'],
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
        ]);

        // Convert comma-separated tags to array
        if (! empty($validated['tags'])) {
            $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
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
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', Rule::enum(CustomerSource::class)],
        ]);

        $source = $validated['source'] ?? CustomerSource::Website->value;
        unset($validated['source']);

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
            'deals.assignee:id,name',
            'attachments.uploader:id,name',
        ]);

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
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['nullable', 'email', 'max:255', "unique:customers,email,{$customer->id}"],
            'phone'             => ['nullable', 'string', 'max:30'],
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
        ]);

        if (! empty($validated['tags'])) {
            $validated['tags'] = array_map('trim', explode(',', $validated['tags']));
        }

        $this->crmService->updateCustomer($customer, $validated, auth()->user());

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    /** Soft-delete customer */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);
        $customer->delete();
        return redirect()->route('crm.customers.index')
            ->with('success', "\"{$customer->name}\" has been removed.");
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

        return response()->json([
            'success'       => true,
            'message'       => 'Purchase recorded!',
            'lifetime_value' => $customer->formatted_value,
            'total_orders'  => $customer->total_orders,
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
