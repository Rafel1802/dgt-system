<?php

namespace App\Http\Controllers\CRM;

use App\Enums\InquirySource;
use App\Enums\WebsiteLeadStatus;
use App\Enums\LeadTemperature;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteCrmController extends Controller
{
    public function index(Request $request): View
    {
        $query = Lead::with(['handler', 'customer', 'product']);

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

        return view('crm.website.index', compact('leads', 'statuses', 'sources'));
    }

    public function create(): View
    {
        return view('crm.website.create', [
            'statuses'  => WebsiteLeadStatus::cases(),
            'sources'   => InquirySource::cases(),
            'products'  => \App\Models\Product::active()->orderBy('name')->get(),
            'crmUsers'  => \App\Models\User::crmMembers()->orderBy('name')->get(),
            'customers' => \App\Models\Customer::orderBy('name')->get(['id','name','phone','company']),
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
        $lead->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Status updated.',
            'status'  => WebsiteLeadStatus::from($request->status),
        ]);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();
        return redirect()->route('crm.website.index')->with('success', 'Lead deleted.');
    }
}
