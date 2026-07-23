<?php

namespace App\Http\Controllers\CRM;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Enums\LeadTemperature;
use App\Exceptions\GoogleSheetsNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Models\CallReport;
use App\Models\CallReportShare;
use App\Models\CallRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadOrder;
use App\Models\Product;
use App\Models\User;
use App\Models\TechSupportCase;
use App\Services\CrmCustomerMatchService;
use App\Services\CrmService;
use App\Services\GoogleSheetsExportService;
use App\Services\TechSupportCaseService;
use App\Notifications\GenericDatabaseNotification;
use App\Support\CrmLookupCache;
use App\Support\InstantNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteCrmController extends Controller
{
    public function __construct(
        private CrmCustomerMatchService $matcher,
        private GoogleSheetsExportService $sheetsExporter,
        private TechSupportCaseService $techSupportCases,
        private CrmService $crmService,
    ) {
    }

    public function index(Request $request): View
    {
        // List-only relations — avoid hydrating full customer/product models.
        $query = Lead::with([
            'handler:id,name',
            'product:id,name',
            // Do not column-restrict morph techSupportCase (ambiguous source_* columns).
            'techSupportCase',
        ]);

        // Role-based visibility: sales-crm only sees leads they handle themselves
        // (matches the same convention used in CrmService for Customers/Deals).
        $user = auth()->user();
        if ($user->hasRole('sales-crm') && ! $user->hasAnyRole(['admin', 'supervisor', 'super-admin'])) {
            $query->where('handled_by', $user->id);
        }

        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }
        if ($handler = $request->get('handled_by')) {
            $query->where('handled_by', $handler);
        }
        if ($temp = $request->get('temperature')) {
            // temperature filter kept for backward compat but not shown in UI
            $query->where('temperature', $temp);
        }
        // Lean columns on list — detail page loads full relations.
        $leads   = $query->select([
            'id', 'customer_id', 'handled_by', 'product_id', 'client_name', 'client_phone',
            'client_email', 'source', 'status', 'temperature', 'received_at', 'created_at',
            'follow_up_date', 'product_interested',
        ])->latest('received_at')->paginate(20)->withQueryString();
        $statuses = WebsiteLeadStatus::cases();
        $sources  = InquirySource::cases();
        $crmUsers = CrmLookupCache::crmMembers();

        $pendingCallRequestsCount = CrmLookupCache::pendingCallRequestsCount();

        // Customers with a Website-channel source but no Lead of their own yet —
        // the same "Website" bucket shown by the All Customers page's source filter.
        // Uses the cached unified directory so this is free after first CRM hit.
        // Cap HTML size: list pages already paginate leads (20); unlimited
        // customer-only rows made Website CRM feel like a multi-second hang.
        $customerOnlyRows = collect();
        if (! $request->get('search') && ! $request->get('status') && ! $request->get('source') && ! $request->get('handled_by')) {
            $customerOnlyRows = $this->matcher->hydrateDirectoryDates(
                $this->matcher->buildUnifiedDirectoryRaw()
                    ->filter(fn (array $c) => ($c['source'] ?? null) === 'Website')
                    ->take(50)
                    ->values()
            );
        }

        return view('crm.website.index', compact('leads', 'statuses', 'sources', 'crmUsers', 'pendingCallRequestsCount', 'customerOnlyRows'));
    }

    /** Standalone call log — a separate page under Website CRM */
    public function callReportsIndex(Request $request): View
    {
        $callReports = $this->filteredCallReportsQuery($request)
            ->latest('occurred_at')->paginate(20)->withQueryString();
        $inquiryTypes = CallReport::INQUIRY_TYPES;
        $crmUsers = CrmLookupCache::crmMembers();
        $filteredTotal = $callReports->total();

        return view('crm.website.call-reports', compact('callReports', 'inquiryTypes', 'crmUsers', 'filteredTotal'));
    }

    /** Create a shareable public link (unauthenticated) snapshotting the current filters. */
    public function shareCallReports(Request $request): RedirectResponse
    {
        $filters = $request->only(['search', 'date_from', 'date_to', 'answered_by']);
        $share = CallReportShare::createForFilters($filters, auth()->id());

        return redirect()->route('crm.website.call-reports.index', $filters)
            ->with('share_url', route('public.call-reports.show', $share->token));
    }

    /** Shared search + date-range + answered-by filtering for the Call Reports index and export. */
    private function filteredCallReportsQuery(Request $request)
    {
        return CallReport::with('answeredBy')
            ->filtered($request->only(['search', 'date_from', 'date_to', 'answered_by']));
    }

    public function create(): View
    {
        return view('crm.website.create', [
            'statuses'  => WebsiteLeadStatus::cases(),
            'sources'   => InquirySource::cases(),
            'products'  => CrmLookupCache::activeProducts(),
            'customers' => CrmLookupCache::customersCombobox(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'       => ['required', 'exists:customers,id'],
            'client_phone'      => ['nullable', 'string', 'max:30'],
            'client_email'      => ['nullable', 'email', 'max:255'],
            'client_whatsapp'   => ['nullable', 'string', 'max:30'],
            'source'            => ['required', Rule::enum(InquirySource::class)],
            'product_interested'=> ['nullable', 'string', 'max:255'],
            'product_id'        => ['nullable', 'exists:products,id'],
            'inquiry_details'   => ['nullable', 'string'],
            'follow_up_date'    => ['nullable', 'date'],
            'next_action'       => ['nullable', 'string', 'max:255'],
            'assigned_to'       => ['nullable', 'exists:users,id'],
            'received_at'       => ['required', 'date'],
        ]);

        $customer = Customer::find($validated['customer_id']);
        $validated['client_phone'] = ($validated['client_phone'] ?? null) ?: ($customer->phone ?? null);
        $validated['client_email'] = ($validated['client_email'] ?? null) ?: ($customer->email ?? null);
        $validated['client_whatsapp'] = ($validated['client_whatsapp'] ?? null) ?: ($customer->whatsapp ?? null);
        
        $lead = Lead::create([
            ...$validated,
            'client_name' => $customer->name,
            'handled_by'  => auth()->id(),
            'status'      => WebsiteLeadStatus::NewLead->value,
            'temperature' => 'warm', // default, not shown in UI
        ]);

        return redirect()->route('crm.website.show', $lead)
            ->with('success', 'Lead "' . $lead->client_name . '" created.');
    }

    public function show(Lead $lead): View
    {
        $lead->load(['handler', 'assignee', 'customer', 'product', 'products', 'orders.items', 'followUps.user', 'attachments', 'techSupportCase']);

        // Viewing this customer's lead counts as viewing the outcome of any
        // of their technical support cases (not just the one sourced from
        // this lead — a customer can also have a case via an eBay record).
        $this->techSupportCases->markCallCompletedNotificationsRead(
            $lead->customer_id
                ? TechSupportCase::where('customer_id', $lead->customer_id)->pluck('id')->all()
                : []
        );

        return view('crm.website.show', [
            'lead'            => $lead,
            'statuses'        => WebsiteLeadStatus::cases(),
            'temps'           => LeadTemperature::cases(),
            'catalogProducts' => CrmLookupCache::activeProducts(),
        ]);
    }

    public function edit(Lead $lead): View
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('website'), 403, 'Only a CRM Supervisor or Boss can edit lead details.');

        return view('crm.website.edit', [
            'lead'      => $lead->load(['followUps', 'products']),
            'statuses'  => WebsiteLeadStatus::cases(),
            'sources'   => InquirySource::cases(),
            'products'  => CrmLookupCache::activeProducts(),
            'crmUsers'  => CrmLookupCache::crmMembers(),
            'temps'     => LeadTemperature::cases(),
        ]);
    }

    /**
     * Updates the lead's own fields only. Orders are logged separately, via
     * "Mark Successful" or "+ Add New Order" on the Lead Profile page (see
     * updateStatus()/storeOrder()) — this form no longer touches products,
     * since re-saving unrelated fields here would otherwise silently create
     * a duplicate order every time under the additive order-history model.
     */
    public function update(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('website'), 403, 'Only a CRM Supervisor or Boss can edit lead details.');

        $validated = $request->validate([
            'client_name'       => ['required', 'string', 'max:255'],
            'client_phone'      => ['nullable', 'string'],
            'client_email'      => ['nullable', 'email'],
            'client_whatsapp'   => ['nullable', 'string'],
            'source'            => ['required', Rule::enum(InquirySource::class)],
            'product_interested'=> ['nullable', 'string'],
            'product_id'        => ['nullable', 'exists:products,id'],
            'inquiry_details'   => ['nullable', 'string'],
            'status'            => ['required', Rule::enum(WebsiteLeadStatus::class)],
            'follow_up_notes'   => ['nullable', 'string'],
            'follow_up_date'    => ['nullable', 'date'],
            'next_action'       => ['nullable', 'string'],
            'handled_by'        => ['nullable', 'exists:users,id'],
            'lost_reason'       => ['nullable', 'string'],
        ]);

        $previousHandlerId = $lead->handled_by;

        $lead->update($validated);

        // Was completely silent before — a lead handed to someone else had
        // no way to find out short of noticing it on the leads list.
        if (
            array_key_exists('handled_by', $validated)
            && $validated['handled_by']
            && $validated['handled_by'] !== $previousHandlerId
            && $validated['handled_by'] !== auth()->id()
        ) {
            $newHandler = User::find($validated['handled_by']);
            if ($newHandler) {
                InstantNotifier::send($newHandler, new GenericDatabaseNotification([
                    'module'  => 'crm',
                    'type'    => 'lead_reassigned',
                    'lead_id' => $lead->id,
                    'message' => auth()->user()->name . " assigned you the lead \"{$lead->client_name}\".",
                    'link'    => route('crm.website.show', $lead),
                ]));
            }
        }

        return redirect()->route('crm.website.show', $lead)->with('success', 'Lead updated.');
    }

    /** Log a follow-up call/contact */
    public function logFollowUp(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'notes'          => ['required', 'string'],
            'next_action'    => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'temperature'    => ['nullable', Rule::enum(LeadTemperature::class)],
            'status'         => ['nullable', Rule::enum(WebsiteLeadStatus::class)],
        ]);

        // Update lead temperature/status if changed
        $lead->update(array_filter([
            'temperature'    => $validated['temperature'] ?? null,
            'status'         => $validated['status'] ?? null,
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'follow_up_notes'=> $validated['notes'],
            'next_action'    => $validated['next_action'] ?? null,
        ]));

        $followUp = LeadFollowUp::create([
            'lead_id'          => $lead->id,
            'user_id'          => auth()->id(),
            'notes'            => $validated['notes'],
            'next_action'      => $validated['next_action'] ?? null,
            'follow_up_date'   => $validated['follow_up_date'] ?? null,
            'temperature'      => $validated['temperature'] ?? null,
            'status_changed_to'=> $validated['status'] ?? null,
            'contacted_at'     => now(),
        ]);

        return response()->json([
            'message'   => 'Follow-up logged.',
            'follow_up' => $followUp->load('user'),
        ]);
    }

    /** A staff member can delete their own follow-up entries — not anyone else's, so the activity timeline stays an honest record of who said what. */
    public function destroyFollowUp(Lead $lead, LeadFollowUp $followUp): RedirectResponse
    {
        abort_unless($followUp->lead_id === $lead->id, 404);
        abort_unless($followUp->user_id === auth()->id(), 403, 'You can only delete your own follow-up entries.');

        $followUp->delete();

        return redirect()->route('crm.website.show', $lead)->with('success', 'Follow-up entry deleted.');
    }

    /** Quick status update (AJAX) */
    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'status'                  => ['required', Rule::enum(WebsiteLeadStatus::class)],
            'note'                    => ['nullable', 'string'],
            'order_date'              => ['required_if:status,successful', 'nullable', 'date'],
            'products'                => ['nullable', 'array'],
            'products.*.product_id'   => ['nullable', 'exists:products,id'],
            'products.*.product_name' => ['nullable', 'string', 'max:255'],
            'products.*.price'        => ['nullable', 'numeric', 'min:0'],
            'products.*.quantity'     => ['nullable', 'integer', 'min:1'],
        ]);
        $newStatus = WebsiteLeadStatus::from($validated['status']);

        $productRows = $this->filledProductRows($validated['products'] ?? []);

        if ($newStatus === WebsiteLeadStatus::Successful && empty($productRows)) {
            return response()->json([
                'message' => 'At least one product is required to mark this lead as Successful.',
            ], 422);
        }

        if ($newStatus === WebsiteLeadStatus::TechnicalSupport && empty(trim($validated['note'] ?? ''))) {
            return response()->json([
                'message' => 'A note explaining the technical issue is required to mark this lead as Technical Support.',
            ], 422);
        }

        if ($lead->status !== $newStatus) {
            $noteText = trim($validated['note'] ?? '');

            // Carried through to TechSupportCaseService::createCaseFor() by
            // Lead's booted() hook, so the note staff typed here becomes the
            // case's own timeline entry instead of generic auto-text —
            // whether this is the first Technical Support case or a reopen.
            if ($newStatus === WebsiteLeadStatus::TechnicalSupport && $noteText !== '') {
                $lead->pendingTechNote = $noteText;
            }

            $lead->update(['status' => $newStatus]);

            // Every status transition is recorded here too, not just ones made
            // through the follow-up modal, so the history timeline is complete.
            // A user-supplied note replaces the generic message when one is
            // required (currently: Technical Support) or was optionally given.
            LeadFollowUp::create([
                'lead_id'           => $lead->id,
                'user_id'           => auth()->id(),
                'notes'             => $noteText !== '' ? $noteText : 'Status changed to ' . $newStatus->label() . '.',
                'status_changed_to' => $newStatus,
                'contacted_at'      => now(),
            ]);
        }

        // Additive — a new order is logged, previous orders are untouched.
        // A repeat purchase on a lead that's already Successful used to wipe
        // and replace the last order's products; now it's simply another
        // entry in that lead's order history, same as "+ Add New Order".
        if ($newStatus === WebsiteLeadStatus::Successful && ! empty($productRows)) {
            $this->createLeadOrder($lead, $productRows, $validated['order_date']);
        }

        return response()->json([
            'message' => 'Status updated.',
            'status'  => $newStatus,
        ]);
    }

    /**
     * Log a new order on an existing lead — independent of any status
     * change, so a repeat customer's purchases keep accumulating as
     * history instead of overwriting the last one. Same endpoint powers
     * the "+ Add New Order" button and the product step of "Mark Successful".
     */
    public function storeOrder(Request $request, Lead $lead): JsonResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('website'), 403, 'Only a CRM Supervisor or Boss can log purchase history.');

        $validated = $request->validate([
            'order_date'               => ['required', 'date'],
            'products'                 => ['required', 'array', 'min:1'],
            'products.*.product_id'    => ['nullable', 'exists:products,id'],
            'products.*.product_name'  => ['nullable', 'string', 'max:255'],
            'products.*.price'         => ['nullable', 'numeric', 'min:0'],
            'products.*.quantity'      => ['nullable', 'integer', 'min:1'],
        ]);

        $productRows = $this->filledProductRows($validated['products']);

        if (empty($productRows)) {
            return response()->json(['message' => 'At least one product is required.'], 422);
        }

        $order = $this->createLeadOrder($lead, $productRows, $validated['order_date']);

        return response()->json([
            'message' => 'Order logged.',
            'order'   => $order->load('items'),
        ]);
    }

    /** Correct an existing order (wrong price/product/date) — replaces its line items wholesale. */
    public function updateOrder(Request $request, Lead $lead, LeadOrder $order): JsonResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('website'), 403, 'Only a CRM Supervisor or Boss can edit purchase history.');
        abort_unless($order->lead_id === $lead->id, 404);

        $validated = $request->validate([
            'order_date'               => ['required', 'date'],
            'products'                 => ['required', 'array', 'min:1'],
            'products.*.product_id'    => ['nullable', 'exists:products,id'],
            'products.*.product_name'  => ['nullable', 'string', 'max:255'],
            'products.*.price'         => ['nullable', 'numeric', 'min:0'],
            'products.*.quantity'      => ['nullable', 'integer', 'min:1'],
        ]);

        $productRows = $this->filledProductRows($validated['products']);

        if (empty($productRows)) {
            return response()->json(['message' => 'At least one product is required.'], 422);
        }

        $order->update(['order_date' => $validated['order_date']]);
        $this->syncOrderItems($order, $lead, $productRows);

        return response()->json([
            'message' => 'Order updated.',
            'order'   => $order->load('items'),
        ]);
    }

    /** Remove a mistakenly-logged order — items cascade-delete with it. */
    public function destroyOrder(Lead $lead, LeadOrder $order): JsonResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('website'), 403, 'Only a CRM Supervisor or Boss can edit purchase history.');
        abort_unless($order->lead_id === $lead->id, 404);

        $order->delete();

        return response()->json(['message' => 'Order removed.']);
    }

    /** A product row counts once it has either a catalog product_id or a manually-typed product_name. */
    private function filledProductRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => ! empty($row['product_id']) || trim($row['product_name'] ?? '') !== '')
            ->values()->all();
    }

    /** Create a new LeadOrder + line items — never deletes previous orders. */
    private function createLeadOrder(Lead $lead, array $rows, string $orderDate): LeadOrder
    {
        $order = $lead->orders()->create([
            'order_date' => $orderDate,
            'created_by' => auth()->id(),
        ]);

        $this->syncOrderItems($order, $lead, $rows);

        return $order;
    }

    /** Replace an order's line items with $rows — used on create and on full edit-replace. */
    private function syncOrderItems(LeadOrder $order, Lead $lead, array $rows): void
    {
        $order->items()->delete();

        foreach ($rows as $row) {
            $product = ! empty($row['product_id']) ? Product::find($row['product_id']) : null;
            $name = $product?->name ?? trim($row['product_name'] ?? '');

            if ($name === '') {
                continue;
            }

            $order->items()->create([
                'lead_id'      => $lead->id,
                'product_id'   => $product?->id,
                'product_name' => $name,
                'sku'          => $product?->sku,
                'price'        => $row['price'] ?? $product?->price,
                'quantity'     => $row['quantity'] ?? 1,
            ]);
        }
    }

    /**
     * Deleting a Lead that's linked to a Customer permanently deletes that
     * customer too, cascading everything tied to them across every CRM
     * domain (other leads, eBay records, shipments, tech support cases) —
     * same as deleting from the Customer Database page directly. A
     * standalone lead with no linked customer is just deleted on its own.
     */
    public function destroy(Lead $lead): RedirectResponse
    {
        abort_unless(auth()->user()->canDeleteCrmRecords('website'), 403, 'Only a CRM Supervisor, eBay Supervisor, Logistic Supervisor, or Boss can delete leads.');

        if ($lead->customer) {
            $customerName = $lead->customer->name;
            $this->crmService->deleteCascading($lead->customer);

            return redirect()->route('crm.website.index')
                ->with('success', "Lead deleted — \"{$customerName}\" and all their related data have been permanently removed too.");
        }

        $lead->delete();
        return redirect()->route('crm.website.index')->with('success', 'Lead deleted.');
    }

    /** Log a standalone call (not tied to any existing lead) */
    public function storeCallReport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => ['nullable', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:255'],
            'inquiry_type'     => ['required', Rule::in(CallReport::INQUIRY_TYPES)],
            'answered_by'      => ['required', 'exists:users,id'],
            'occurred_at'      => ['required', 'date'],
            'occurred_at_time' => ['nullable', 'date_format:H:i'],
            'details'          => ['nullable', 'string'],
        ]);

        $occurredAtTime = $validated['occurred_at_time'] ?? now()->format('H:i');
        unset($validated['occurred_at_time']);
        $validated['occurred_at'] = $validated['occurred_at'] . ' ' . $occurredAtTime;

        CallReport::create([...$validated, 'created_by' => auth()->id()]);

        return redirect()->route('crm.website.call-reports.index')->with('success', 'Call report logged.');
    }

    /**
     * Mark a call request (raised from Tech Support) as called. Only Website
     * CRM — the team that actually makes the call — can complete this; a
     * note explaining the outcome is required so Tech Support knows the
     * status without having to ask, and gets logged back onto their case.
     */
    public function fulfillCallRequest(Request $request, CallRequest $callRequest): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        // Already fulfilled — idempotent (double-submit / back button).
        if ($callRequest->fulfilled) {
            return redirect()
                ->route('crm.website.call-requests.index', $request->only('status', 'search'))
                ->with('success', 'Call request already marked as called.');
        }

        $callRequest->loadMissing('source');

        $callRequest->update([
            'fulfilled'        => true,
            'fulfilled_at'     => now(),
            'fulfilled_by'     => auth()->id(),
            'fulfillment_note' => $validated['note'],
        ]);

        if ($callRequest->source instanceof TechSupportCase) {
            // Notifications fan out after the response (see logCallCompletedOnCase).
            $this->techSupportCases->logCallCompletedOnCase(
                $callRequest->source,
                auth()->user(),
                $validated['note'],
                $callRequest->note
            );
        }

        // Preserve current tab/search so the redirect feels like the same page.
        return redirect()
            ->route('crm.website.call-requests.index', array_filter([
                'status' => $request->input('return_status', $request->query('status', 'pending')),
                'search' => $request->input('return_search', $request->query('search')),
            ]))
            ->with('success', 'Call request marked as called.');
    }

    /** Dedicated Call Requests page — separate from the Tech Support case page, which keeps managing its own. */
    public function callRequestsIndex(Request $request): View
    {
        $query = CallRequest::with(['requestedBy', 'fulfilledBy', 'source']);

        $tab = $request->get('status', 'pending');
        if ($tab === 'pending') {
            $query->where('fulfilled', false);
        } elseif ($tab === 'fulfilled') {
            $query->where('fulfilled', true);
        }

        if ($s = $request->get('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        $callRequests = $query->latest()->paginate(20)->withQueryString();

        return view('crm.website.call-requests', compact('callRequests', 'tab'));
    }

    /** Export the currently filtered Call Reports as a PDF or a live, formatted Google Sheet. */
    public function exportCallReports(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'format'      => ['required', 'string', 'in:pdf,google_sheet'],
            'search'      => ['nullable', 'string'],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date'],
            'answered_by' => ['nullable', 'exists:users,id'],
        ]);

        $reports = $this->filteredCallReportsQuery($request)->latest('occurred_at')->get();

        $dateRangeStr = ($validated['date_from'] ?? null) && ($validated['date_to'] ?? null)
            ? $validated['date_from'] . ' to ' . $validated['date_to']
            : 'All Time';
        $title = 'Call Reports — ' . $dateRangeStr;

        if ($validated['format'] === 'pdf') {
            $filename = 'call_reports_' . now()->format('Ymd_His') . '.pdf';

            return Pdf::loadView('reports.call_reports_export', [
                'title' => $title,
                'reports' => $reports,
                'dateRangeStr' => $dateRangeStr,
            ])->download($filename);
        }

        try {
            $url = $this->sheetsExporter->exportCallReports($reports, $title . ' — exported ' . now()->format('Y-m-d H:i'));
        } catch (GoogleSheetsNotConfiguredException $e) {
            return redirect()->route('crm.website.call-reports.index')->withErrors(['google_sheet' => $e->getMessage()]);
        }

        return redirect()->away($url);
    }
}
