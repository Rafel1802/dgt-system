<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\MachineReturn;
use App\Models\MachineReturnLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class LogisticReturnController extends Controller
{
    public function index(Request $request): View
    {
        $query = MachineReturn::with(['customer', 'techSupportCase', 'handler'])
            ->latest('updated_at');

        if ($s = $request->get('search')) {
            $query->whereHas('customer', function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $returns = $query->paginate(20)->withQueryString();
        $statuses = MachineReturn::statuses();

        return view('crm.logistics.returns.index', compact('returns', 'statuses'));
    }

    public function show(MachineReturn $return): View
    {
        $return->load(['customer', 'techSupportCase.logs', 'handler', 'logs.user']);
        $statuses = MachineReturn::statuses();

        return view('crm.logistics.returns.show', compact('return', 'statuses'));
    }

    public function updateStatus(Request $request, MachineReturn $return): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(MachineReturn::statuses()))],
            'note'   => ['required', 'string'],
        ]);

        if ($return->status !== $validated['status']) {
            $return->update([
                'status' => $validated['status'],
                'handled_by' => auth()->id(),
            ]);

            MachineReturnLog::create([
                'machine_return_id' => $return->id,
                'user_id' => auth()->id(),
                'status_changed_to' => $validated['status'],
                'note' => $validated['note'],
            ]);
        } else {
            // Just adding a note without changing status
            MachineReturnLog::create([
                'machine_return_id' => $return->id,
                'user_id' => auth()->id(),
                'status_changed_to' => $return->status,
                'note' => $validated['note'],
            ]);
        }

        // In case anyone is watching the tech support or customer record
        if ($return->customer_id) {
            broadcast(new \App\Events\CustomerDataUpdatedLive($return->customer_id, 'customer', 'Logistics return updated.', auth()->user()->name, 'Logistics'))->toOthers();
        }
        if ($return->tech_support_case_id) {
            broadcast(new \App\Events\TechSupportCaseDataUpdated($return->tech_support_case_id, auth()->user()->name, 'Logistics return updated.'));
            
            // Also notify the Tech Support team via Pusher so they can hot-swap if looking at it
            if ($return->status === MachineReturn::STATUS_RECEIVED) {
                // We'll update the tech support case if needed
                $return->techSupportCase->update(['status' => \App\Models\TechSupportCase::STATUS_RETURN_RECEIVED]);
                broadcast(new \App\Events\TechSupportCaseStatusUpdated($return->techSupportCase, auth()->id()));
                
                if ($return->techSupportCase->source instanceof \App\Models\Lead) {
                    $return->techSupportCase->source->update(['status' => \App\Enums\WebsiteLeadStatus::ReturnReceived->value]);
                } elseif ($return->techSupportCase->source instanceof \App\Models\EbayCustomerRecord) {
                    $return->techSupportCase->source->update(['tab_type' => \App\Models\EbayCustomerRecord::TAB_RETURN_RECEIVED]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Status updated successfully.']);
        }

        return redirect()->route('crm.logistics.returns.show', $return)
                         ->with('success', 'Status updated successfully.');
    }
}
