<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteInquiry;
use App\Models\WebsiteInquiryActivity;
use App\Services\WebsiteInquiryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class WebsiteInquiryController extends Controller
{
    public function __construct(
        protected WebsiteInquiryService $inquiryService
    ) {}

    /**
     * Display a listing of website inquiries with metrics, filters, and real-time support.
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403, 'Unauthorized access to website inquiries.');
        }

        // Auto-sync inquiries from integrated network sites (Happy Machinery, etc.)
        try {
            $this->inquiryService->syncAllWebsiteInquiries();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Inquiry auto-sync notice: " . $e->getMessage());
        }

        $websiteId = $request->filled('website_id') ? (int) $request->input('website_id') : null;
        $status    = $request->input('status');
        $type      = $request->input('type');
        $dateRange = $request->input('date_range', 'all');
        $search    = $request->input('search');

        // Build query
        $query = WebsiteInquiry::with(['website', 'assignee'])
            ->latest('submitted_at');

        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        if ($status && $status !== 'all') {
            if ($status === 'unread') {
                $query->where('is_read', false)->where('status', '!=', WebsiteInquiry::STATUS_SPAM);
            } else {
                $query->where('status', $status);
            }
        }

        if ($type && $type !== 'all') {
            $query->where('inquiry_type', $type);
        }

        if ($search) {
            $query->search($search);
        }

        $this->applyDateFilter($query, $dateRange);

        // Fetch metrics
        $stats = $this->inquiryService->getDashboardStats($websiteId, $dateRange);

        // Paginate results
        $inquiries = $query->paginate(20)->withQueryString();

        if ($request->wantsJson() && !$request->has('page_render')) {
            return response()->json([
                'success'   => true,
                'inquiries' => $inquiries,
                'stats'     => $stats,
            ]);
        }

        $websites = Website::active()->orderBy('name')->get(['id', 'name', 'url']);
        $teamMembers = User::orderBy('name')->get(['id', 'name', 'email']);
        $types = WebsiteInquiry::INQUIRY_TYPES;
        $statuses = WebsiteInquiry::STATUSES;

        return view('websites.inquiries.index', compact(
            'inquiries',
            'stats',
            'websites',
            'teamMembers',
            'types',
            'statuses',
            'websiteId',
            'status',
            'type',
            'dateRange',
            'search'
        ));
    }

    /**
     * Show detail for a specific inquiry (marks as read and includes activity timeline).
     */
    public function show(Request $request, WebsiteInquiry $inquiry): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403);
        }

        $this->inquiryService->markAsRead($inquiry, $user);

        $inquiry->load([
            'website:id,name,url',
            'assignee:id,name,email',
            'activities.user:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'inquiry' => $inquiry,
        ]);
    }

    /**
     * Update inquiry status.
     */
    public function updateStatus(Request $request, WebsiteInquiry $inquiry): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(WebsiteInquiry::STATUSES)),
            'note'   => 'nullable|string|max:1000',
        ]);

        $this->inquiryService->updateStatus($inquiry, $request->status, $user, $request->note);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully.',
                'inquiry' => $inquiry->fresh(['website', 'assignee', 'activities.user']),
            ]);
        }

        return back()->with('success', 'Inquiry status updated successfully.');
    }

    /**
     * Assign inquiry to a team member.
     */
    public function assign(Request $request, WebsiteInquiry $inquiry): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403);
        }

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $this->inquiryService->assignInquiry($inquiry, $request->user_id ? (int)$request->user_id : null, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Inquiry assignment updated.',
                'inquiry' => $inquiry->fresh(['website', 'assignee', 'activities.user']),
            ]);
        }

        return back()->with('success', 'Inquiry assigned successfully.');
    }

    /**
     * Add an internal activity note to an inquiry.
     */
    public function addNote(Request $request, WebsiteInquiry $inquiry): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403);
        }

        $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $activity = $this->inquiryService->addNote($inquiry, $user, $request->note);
        $activity->load('user:id,name,email');

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Note added.',
                'activity' => $activity,
            ]);
        }

        return back()->with('success', 'Note added.');
    }

    /**
     * Delete an inquiry.
     */
    public function destroy(Request $request, WebsiteInquiry $inquiry): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403);
        }

        $inquiry->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Inquiry moved to trash.',
            ]);
        }

        return back()->with('success', 'Inquiry deleted successfully.');
    }

    /**
     * Export inquiries to CSV based on current filters.
     */
    public function exportCsv(Request $request)
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403);
        }

        $query = WebsiteInquiry::with(['website', 'assignee'])->latest('submitted_at');

        if ($request->filled('website_id')) {
            $query->where('website_id', (int) $request->input('website_id'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            if ($request->input('status') === 'unread') {
                $query->where('is_read', false)->where('status', '!=', WebsiteInquiry::STATUS_SPAM);
            } else {
                $query->where('status', $request->input('status'));
            }
        }

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('inquiry_type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $this->applyDateFilter($query, $request->input('date_range', 'all'));

        $filename = 'website-inquiries-' . date('Y-m-d-His') . '.csv';

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID',
                'Website',
                'Inquiry Type',
                'Status',
                'Is Read',
                'Customer Name',
                'Customer Email',
                'Customer Phone',
                'Customer Company',
                'Subject',
                'Message',
                'Product / Service',
                'Form Name',
                'Source URL',
                'Assigned To',
                'Submitted At',
            ]);

            $query->chunk(200, function ($inquiries) use ($handle) {
                foreach ($inquiries as $inquiry) {
                    fputcsv($handle, [
                        $inquiry->id,
                        $inquiry->website?->name ?? 'N/A',
                        $inquiry->type_label,
                        $inquiry->status_label,
                        $inquiry->is_read ? 'Yes' : 'No',
                        $inquiry->customer_name,
                        $inquiry->customer_email,
                        $inquiry->customer_phone,
                        $inquiry->customer_company,
                        $inquiry->subject,
                        $inquiry->message,
                        $inquiry->product_service,
                        $inquiry->form_name,
                        $inquiry->source_url,
                        $inquiry->assignee?->name ?? 'Unassigned',
                        $inquiry->submitted_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        };

        return Response::stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Manually trigger full inquiry sync across all connected websites.
     */
    public function syncInquiries(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasWebsiteAccess()) {
            abort(403);
        }

        $res = $this->inquiryService->syncAllWebsiteInquiries(true);

        return response()->json([
            'success' => true,
            'message' => $res['synced'] > 0
                ? "Successfully synced {$res['synced']} new inquiries from connected websites."
                : "All connected websites are up to date. No new inquiries found.",
            'synced'  => $res['synced'],
            'sites'   => $res['sites'] ?? [],
        ]);
    }

    /**
     * Apply date range filter.
     */
    protected function applyDateFilter($query, string $range): void
    {
        match ($range) {
            'today'        => $query->whereDate('submitted_at', Carbon::today()),
            'yesterday'    => $query->whereDate('submitted_at', Carbon::yesterday()),
            'last_7_days'  => $query->where('submitted_at', '>=', Carbon::now()->subDays(7)),
            'last_30_days' => $query->where('submitted_at', '>=', Carbon::now()->subDays(30)),
            'this_month'   => $query->whereMonth('submitted_at', Carbon::now()->month)->whereYear('submitted_at', Carbon::now()->year),
            default        => null,
        };
    }
}
