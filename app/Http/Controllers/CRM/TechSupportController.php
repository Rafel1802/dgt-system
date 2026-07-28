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
use Illuminate\Support\Facades\Cache;
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
        // List page only needs identity fields + latest call-request flag — not full histories.
        $query = TechSupportCase::with([
            'customer:id,name,phone,email',
            'assignee:id,name',
            'source',
            'callRequests' => fn ($q) => $q->latest('id')->limit(1),
        ]);

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

        $stats = Cache::remember('tech_support.index_stats', 30, function () {
            $row = TechSupportCase::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as new_count", [TechSupportCase::STATUS_NEW])
                ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress", [TechSupportCase::STATUS_IN_PROGRESS])
                ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as red", [TechSupportCase::STATUS_RED])
                ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved", [TechSupportCase::STATUS_RESOLVED])
                ->first();

            return [
                'total'       => (int) ($row->total ?? 0),
                'new'         => (int) ($row->new_count ?? 0),
                'in_progress' => (int) ($row->in_progress ?? 0),
                'red'         => (int) ($row->red ?? 0),
                'resolved'    => (int) ($row->resolved ?? 0),
            ];
        });

        // Role is seeded — never findOrCreate on a read path.
        $technicians = $this->technicians();
        $statuses = TechSupportCase::statuses();

        // Which of these cases have a call-completed outcome this user hasn't
        // opened yet — drives the "new" (unviewed) styling on the row badge,
        // separate from a call that's completed but already been looked at.
        $unreadCallCompletedCaseIds = auth()->user()->unreadNotifications()
            ->where('data', 'like', '%tech_case_call_completed%')
            ->orderByDesc('created_at')
            ->limit(100)
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

        $technicians = $this->technicians();
        $statuses = TechSupportCase::statuses();
        $statusColors = collect(array_keys($statuses))
            ->mapWithKeys(fn ($k) => [$k => TechSupportCase::statusColor($k)])
            ->all();

        return view('crm.tech-support.show', compact('case', 'technicians', 'statuses', 'statusColors'));
    }

    /**
     * Active tech-support users for assign dropdowns.
     * Returns a plain collection of {id, name} arrays — never cache raw Eloquent
     * models here (Hostinger file/database cache can corrupt them on unserialize
     * and the Blade view then dies with "Attempt to read property id on string").
     */
    private function technicians()
    {
        return Cache::remember('tech_support.technicians.v2', 120, function () {
            try {
                return User::role('tech-support')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                    ->values()
                    ->all();
            } catch (\Throwable) {
                // Role missing on a fresh DB — empty list is fine; seeder owns roles.
                return [];
            }
        });
    }

    public function updateStatus(Request $request, TechSupportCase $case): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(TechSupportCase::statuses()))],
        ]);

        $this->service->changeStatus($case, $validated['status'], auth()->user());
        Cache::forget('tech_support.index_stats');

        $case->refresh();
        $statuses = TechSupportCase::statuses();

        return response()->json([
            'message'      => 'Status updated.',
            'status'       => $case->status,
            'status_label' => $statuses[$case->status] ?? $case->status,
            'status_color' => TechSupportCase::statusColor($case->status),
        ]);
    }

    public function assign(Request $request, TechSupportCase $case): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'exists:users,id']]);

        $case->update(['assigned_to' => $validated['user_id']]);
        $case->load('assignee:id,name');

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
            'call_request' => [
                'id'         => $callRequest->id,
                'note'       => $callRequest->note,
                'created_at' => $callRequest->created_at?->format('d M Y, g:ia'),
                'fulfilled'  => (bool) $callRequest->fulfilled,
            ],
        ]);
    }
}
