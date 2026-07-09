<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\CallRequest;
use App\Models\TechSupportCase;
use App\Models\User;
use App\Services\TechSupportCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TechSupportController extends Controller
{
    public function __construct(private readonly TechSupportCaseService $service)
    {
    }

    /** Technical Support dashboard: KPI tiles + search/filter + case list */
    public function index(Request $request): View
    {
        $query = TechSupportCase::with(['customer', 'assignee', 'source']);

        if ($s = $request->get('search')) {
            $query->search($s);
        }
        if ($status = $request->get('status')) {
            $query->status($status);
        }
        if ($assignedTo = $request->get('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($date = $request->get('date')) {
            $query->whereDate('created_at', $date);
        }

        $cases = $query->latest('updated_at')->paginate(20)->withQueryString();

        $stats = [
            'total'       => TechSupportCase::count(),
            'new'         => TechSupportCase::status(TechSupportCase::STATUS_NEW)->count(),
            'in_progress' => TechSupportCase::status(TechSupportCase::STATUS_IN_PROGRESS)->count(),
            'red'         => TechSupportCase::status(TechSupportCase::STATUS_RED)->count(),
            'resolved'    => TechSupportCase::status(TechSupportCase::STATUS_RESOLVED)->count(),
        ];

        $technicians = User::role('tech-support')->where('is_active', true)->orderBy('name')->get();
        $statuses = TechSupportCase::statuses();

        return view('crm.tech-support.index', compact('cases', 'stats', 'technicians', 'statuses'));
    }

    /** Case detail: customer/order info, previous sales/eBay notes, activity timeline, follow-up logs, attachments */
    public function show(TechSupportCase $case): View
    {
        $case->load([
            'customer', 'assignee', 'creator', 'source',
            'logs' => fn ($q) => $q->with(['user', 'attachments'])->latest(),
            'callRequests' => fn ($q) => $q->latest(),
        ]);

        $technicians = User::role('tech-support')->where('is_active', true)->orderBy('name')->get();
        $statuses = TechSupportCase::statuses();

        return view('crm.tech-support.show', compact('case', 'technicians', 'statuses'));
    }

    public function updateStatus(Request $request, TechSupportCase $case): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(TechSupportCase::statuses()))],
        ]);

        $this->service->changeStatus($case, $validated['status'], auth()->user());

        return response()->json([
            'message' => 'Status updated.',
            'status'  => $case->fresh()->status,
        ]);
    }

    public function assign(Request $request, TechSupportCase $case): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'exists:users,id']]);

        $case->update(['assigned_to' => $validated['user_id']]);

        return response()->json([
            'message'  => 'Case assigned.',
            'assignee' => $case->assignee?->only(['id', 'name']),
        ]);
    }

    public function storeFollowUp(Request $request, TechSupportCase $case): JsonResponse
    {
        $validated = $request->validate([
            'note'       => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,gif', 'max:51200'],
        ]);

        $log = $this->service->addFollowUp($case, auth()->user(), $validated['note'], $request->file('attachment'));

        return response()->json([
            'message' => 'Follow-up added.',
            'log'     => $log->load('user', 'attachments'),
        ]);
    }

    public function requestCall(TechSupportCase $case): JsonResponse
    {
        $callRequest = $this->service->requestCall($case, auth()->user());

        return response()->json([
            'message'      => 'Call requested.',
            'call_request' => $callRequest,
        ]);
    }

    public function completeCall(Request $request, TechSupportCase $case, CallRequest $callRequest): JsonResponse
    {
        $validated = $request->validate([
            'summary' => ['required', 'string'],
            'status'  => ['nullable', Rule::in(array_keys(TechSupportCase::statuses()))],
        ]);

        $this->service->completeCall($case, $callRequest, auth()->user(), $validated['summary'], $validated['status'] ?? null);

        return response()->json(['message' => 'Call completed.']);
    }
}
