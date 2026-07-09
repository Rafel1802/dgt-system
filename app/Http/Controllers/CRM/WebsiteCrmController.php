<?php

namespace App\Http\Controllers\CRM;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Enums\LeadTemperature;
use App\Http\Controllers\Controller;
use App\Models\CallReport;
use App\Models\CallRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Product;
use App\Models\User;
use App\Services\CrmCustomerMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteCrmController extends Controller
{
    public function __construct(private CrmCustomerMatchService $matcher)
    {
    }

    public function index(Request $request): View
    {
        $query = Lead::with(['handler', 'customer', 'product']);

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
        if ($temp = $request->get('temperature')) {
            // temperature filter kept for backward compat but not shown in UI
            $query->where('temperature', $temp);
        }       
        $leads   = $query->latest('received_at')->paginate(20)->withQueryString();
        $statuses = WebsiteLeadStatus::cases();
        $sources  = InquirySource::cases();

        $callRequests = CallRequest::with(['requestedBy', 'source'])->pending()->latest()->get();

        // Customers with a Website-channel source but no Lead of their own yet —
        // the same "Website" bucket shown by the All Customers page's source filter.
        // Shown read-only here (no pipeline/status/follow-up controls, since they
        // aren't Leads) unless the current user is search/status/source filtering,
        // in which case we keep the leads table the sole focus.
        $customerOnlyRows = collect();
        if (! $request->get('search') && ! $request->get('status') && ! $request->get('source')) {
            $customerOnlyRows = $this->matcher->buildUnifiedDirectory()->filter(fn ($c) => $c['source'] === 'Website');
        }

        return view('crm.website.index', compact('leads', 'statuses', 'sources', 'callRequests', 'customerOnlyRows'));
    }

    /** Standalone call log — a separate page under Website CRM */
    public function callReportsIndex(Request $request): View
    {
        $query = CallReport::with('answeredBy');

        if ($s = $request->get('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $callReports = $query->latest('occurred_at')->paginate(20)->withQueryString();
        $inquiryTypes = CallReport::INQUIRY_TYPES;
        $crmUsers = User::crmMembers()->orderBy('name')->get();

        return view('crm.website.call-reports', compact('callReports', 'inquiryTypes', 'crmUsers'));
    }

    public function create(): View
    {
        return view('crm.website.create', [
            'statuses'  => WebsiteLeadStatus::cases(),
            'sources'   => InquirySource::cases(),
            'products'  => \App\Models\Product::active()->orderBy('name')->get(),
            'crmUsers'  => \App\Models\User::crmMembers()->orderBy('name')->get(),
            'customers' => \App\Models\Customer::orderBy('name')->get(['id','name','email','phone','company','address']),
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
        $lead->load(['handler', 'assignee', 'customer', 'product', 'followUps.user', 'attachments']);

        return view('crm.website.show', [
            'lead'     => $lead,
            'statuses' => WebsiteLeadStatus::cases(),
            'temps'    => LeadTemperature::cases(),
        ]);
    }

    public function edit(Lead $lead): View
    {
        return view('crm.website.edit', [
            'lead'     => $lead->load('followUps'),
            'statuses' => WebsiteLeadStatus::cases(),
            'sources'  => InquirySource::cases(),
            'products' => \App\Models\Product::active()->orderBy('name')->get(),
            'crmUsers' => \App\Models\User::crmMembers()->orderBy('name')->get(),
            'temps'    => LeadTemperature::cases(),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
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
            'assigned_to'       => ['nullable', 'exists:users,id'],
            'lost_reason'       => ['nullable', 'string'],
        ]);

        $lead->update($validated);

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

    /** Quick status update (AJAX) */
    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        $request->validate(['status' => ['required', Rule::enum(WebsiteLeadStatus::class)]]);
        $newStatus = WebsiteLeadStatus::from($request->status);

        if ($lead->status !== $newStatus) {
            $lead->update(['status' => $newStatus]);

            // Every status transition is recorded here too, not just ones made
            // through the follow-up modal, so the history timeline is complete.
            LeadFollowUp::create([
                'lead_id'           => $lead->id,
                'user_id'           => auth()->id(),
                'notes'             => 'Status changed to ' . $newStatus->label() . '.',
                'status_changed_to' => $newStatus,
                'contacted_at'      => now(),
            ]);
        }

        return response()->json([
            'message' => 'Status updated.',
            'status'  => $newStatus,
        ]);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();
        return redirect()->route('crm.website.index')->with('success', 'Lead deleted.');
    }

    /** Log a standalone call (not tied to any existing lead) */
    public function storeCallReport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:255'],
            'inquiry_type'  => ['required', Rule::in(CallReport::INQUIRY_TYPES)],
            'answered_by'   => ['required', 'exists:users,id'],
            'occurred_at'   => ['required', 'date'],
        ]);

        CallReport::create([...$validated, 'created_by' => auth()->id()]);

        return redirect()->route('crm.website.call-reports.index')->with('success', 'Call report logged.');
    }

    /** Mark a pending call request (raised from Tech Support) as called */
    public function fulfillCallRequest(CallRequest $callRequest): RedirectResponse
    {
        $callRequest->update([
            'fulfilled'    => true,
            'fulfilled_at' => now(),
            'fulfilled_by' => auth()->id(),
        ]);

        return redirect()->route('crm.website.index')->with('success', 'Call request marked as called.');
    }
}
