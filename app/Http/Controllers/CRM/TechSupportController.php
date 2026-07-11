<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\TechSupportCase;
use App\Models\TechSupportCaseLog;
use App\Models\User;
use App\Services\TechSupportCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $query = TechSupportCase::with(['customer', 'assignee', 'source', 'callRequests']);

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

        \Spatie\Permission\Models\Role::findOrCreate('tech-support', 'web');
        $technicians = User::role('tech-support')->where('is_active', true)->orderBy('name')->get();
        $statuses = TechSupportCase::statuses();

        // Which of these cases have a call-completed outcome this user hasn't
        // opened yet — drives the "new" (unviewed) styling on the row badge,
        // separate from a call that's completed but already been looked at.
        $unreadCallCompletedCaseIds = auth()->user()->unreadNotifications()
            ->where('data->type', 'tech_case_call_completed')
            ->get()
            ->pluck('data.case_id')
            ->unique()
            ->all();

        return view('crm.tech-support.index', compact('cases', 'stats', 'technicians', 'statuses', 'unreadCallCompletedCaseIds'));
    }

    /** Case detail: customer/order info, previous sales/eBay notes, activity timeline, follow-up logs, attachments */
    public function show(TechSupportCase $case): View
    {
        $case->load([
            'customer', 'assignee', 'creator', 'source',
            'logs' => fn ($q) => $q->with(['user', 'attachments'])->latest(),
            'callRequests' => fn ($q) => $q->latest(),
        ]);

        // Opening the case is "viewing the result" — clears this user's
        // unread call-completed notification for it so the list's "new"
        // badge doesn't keep flagging an outcome they've already read.
        $this->service->markCallCompletedNotificationsRead([$case->id]);

        \Spatie\Permission\Models\Role::findOrCreate('tech-support', 'web');
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

    /**
     * A technician can delete their own entries in the Follow-Up Logs panel
     * — plain follow-up notes as well as the note text they typed on a
     * "Call Completed" outcome or a "New Issue Reported" reopen (both are
     * still their own words, just auto-labeled by type). Safe to remove:
     * occurrence_count and the case's status/resolution live on the case
     * itself, not on this log row, so deleting the entry only removes its
     * text from the timeline. Never someone else's entries.
     */
    public function destroyFollowUp(TechSupportCase $case, TechSupportCaseLog $log): RedirectResponse
    {
        abort_unless($log->tech_support_case_id === $case->id, 404);
        abort_unless($log->user_id === auth()->id(), 403, 'You can only delete your own log entries.');

        $log->delete();

        return redirect()->route('crm.tech-support.show', $case)->with('success', 'Log entry deleted.');
    }

    public function requestCall(Request $request, TechSupportCase $case): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $callRequest = $this->service->requestCall($case, auth()->user(), $validated['note']);

        return response()->json([
            'message'      => 'Call requested.',
            'call_request' => $callRequest,
        ]);
    }
}
