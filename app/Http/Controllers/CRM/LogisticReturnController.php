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

    public function updateStatus(Request $request, MachineReturn $return): RedirectResponse
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

        return redirect()->route('crm.logistics.returns.show', $return)
                         ->with('success', 'Status updated successfully.');
    }
}
