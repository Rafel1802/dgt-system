<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteFollowUp;
use App\Models\WebsiteMaintenanceLog;
use App\Models\WebsiteProgressLog;
use App\Models\WebsiteQcCheck;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebsiteMember;
use App\Notifications\WebsiteActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class WebsiteController extends Controller
{
    // ── Allowed roles ─────────────────────────────────────────────────────────
    const ALLOWED_ROLES = ['super-admin', 'admin-digital', 'digital-team', 'boss'];
    const ADMIN_ROLES   = ['super-admin', 'admin-digital'];

    // ── INDEX (5 tabs) ────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $tab = $request->get('tab', 'build');

        // Fetch all non-archived websites — only eager-load the relationships
        // that the ACTIVE tab's Blade template actually uses.
        $tabRelations = match($tab) {
            'build'            => ['handler'],
            'build-progress'   => ['handler', 'latestProgressLog.user'],
            'live'             => ['handler', 'latestMaintenanceLog.user'],
            'maintenance'      => ['handler', 'latestMaintenanceLog.user'],
            'qc-checking'      => ['handler'],
            'supervisor-checking' => ['handler'],
            'qc-error'         => ['handler'],
            'supervisor-error' => ['handler'],
            default            => ['handler'],
        };

        // --- OPTIMIZATION: Fetch lightweight stats and counts via DB grouping ---
        $statusCounts = Website::where('is_archived', false)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();

        $followUpsCount = WebsiteFollowUp::count();

        $tabCounts = [
            'build'               => $statusCounts[Website::STATUS_BUILD_WEBSITE] ?? 0,
            'build-progress'      => ($statusCounts[Website::STATUS_BUILD_PROGRESS] ?? 0) + ($statusCounts[Website::STATUS_QC_CHECKING] ?? 0) + ($statusCounts[Website::STATUS_SUPERVISOR_CHECKING] ?? 0),
            'live'                => ($statusCounts[Website::STATUS_LIVE] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_PROGRESS] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_QC_CHECKING] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING] ?? 0),
            'maintenance'         => ($statusCounts[Website::STATUS_MAINTENANCE] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_PROGRESS] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_QC_CHECKING] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING] ?? 0),
            'qc-checking'         => ($statusCounts[Website::STATUS_QC_CHECKING] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_QC_CHECKING] ?? 0),
            'supervisor-checking' => ($statusCounts[Website::STATUS_SUPERVISOR_CHECKING] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING] ?? 0),
            'qc-error'            => ($statusCounts[Website::STATUS_QC_ERROR] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_QC_ERROR] ?? 0),
            'supervisor-error'    => ($statusCounts[Website::STATUS_SUPERVISOR_ERROR] ?? 0) + ($statusCounts[Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR] ?? 0),
            'follow-up'           => $followUpsCount,
        ];

        // --- OPTIMIZATION: Fetch ONLY the websites for the active tab ---
        $buildWebsites = collect();
        $buildProgressWebsites = collect();
        $qcErrorWebsites = collect();
        $qcCheckingWebsites = collect();
        $supervisorErrorWebsites = collect();
        $supervisorCheckingWebsites = collect();
        $liveWebsites = collect();
        $maintenanceWebsites = collect();

        $activeQuery = Website::where('is_archived', false)->orderBy('name')->with($tabRelations);

        switch ($tab) {
            case 'build':
                $buildWebsites = (clone $activeQuery)->where('status', Website::STATUS_BUILD_WEBSITE)->get();
                break;
            case 'build-progress':
                $buildProgressWebsites = (clone $activeQuery)->whereIn('status', [
                    Website::STATUS_BUILD_PROGRESS,
                    Website::STATUS_QC_CHECKING,
                    Website::STATUS_SUPERVISOR_CHECKING,
                ])->get();
                break;
            case 'qc-error':
                $qcErrorWebsites = (clone $activeQuery)->whereIn('status', [
                    Website::STATUS_QC_ERROR,
                    Website::STATUS_MAINTENANCE_QC_ERROR,
                ])->get();
                break;
            case 'qc-checking':
                $qcCheckingWebsites = (clone $activeQuery)->whereIn('status', [
                    Website::STATUS_QC_CHECKING,
                    Website::STATUS_MAINTENANCE_QC_CHECKING,
                ])->get();
                break;
            case 'supervisor-error':
                $supervisorErrorWebsites = (clone $activeQuery)->whereIn('status', [
                    Website::STATUS_SUPERVISOR_ERROR,
                    Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
                ])->get();
                break;
            case 'supervisor-checking':
                $supervisorCheckingWebsites = (clone $activeQuery)->whereIn('status', [
                    Website::STATUS_SUPERVISOR_CHECKING,
                    Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
                ])->get();
                break;
            case 'live':
                $liveWebsites = (clone $activeQuery)->whereIn('status', [
                    Website::STATUS_LIVE, Website::STATUS_MAINTENANCE, 
                    Website::STATUS_MAINTENANCE_PROGRESS, Website::STATUS_MAINTENANCE_QC_CHECKING, 
                    Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING
                ])->get();
                break;
            case 'maintenance':
                $maintenanceWebsites = (clone $activeQuery)->whereIn('status', [
                    Website::STATUS_MAINTENANCE,
                    Website::STATUS_MAINTENANCE_PROGRESS,
                    Website::STATUS_MAINTENANCE_QC_CHECKING,
                    Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
                ])->get();
                break;
        }

        // ── Follow Up Tab ─────────────────────────────────────────────────────
        $followUpFilter = $request->only(['fu_class', 'fu_website', 'fu_type', 'fu_qc', 'fu_member', 'fu_date']);
        
        $followUpsQuery = WebsiteFollowUp::with(['website', 'assignee', 'qcChecker', 'creator'])
            ->orderByDesc('created_at');
            
        // Apply fu_class filter
        if (!empty($followUpFilter['fu_class'])) {
            $cat = $followUpFilter['fu_class'];
            $followUpsQuery->whereHas('website', function($q) use ($cat) {
                if ($cat === '__none__') {
                    $q->whereNull('category');
                } else {
                    $q->where('category', $cat);
                }
            });
        }

        if (!empty($followUpFilter['fu_website'])) {
            $followUpsQuery->where('website_id', $followUpFilter['fu_website']);
        }
        if (!empty($followUpFilter['fu_type'])) {
            $followUpsQuery->where('type', $followUpFilter['fu_type']);
        }
        if (!empty($followUpFilter['fu_qc'])) {
            $followUpsQuery->where('qc_status', $followUpFilter['fu_qc']);
        }
        if (!empty($followUpFilter['fu_member'])) {
            $followUpsQuery->where('assigned_to', $followUpFilter['fu_member']);
        }
        // Default date to today if not provided
        if (!isset($followUpFilter['fu_date'])) {
            $followUpFilter['fu_date'] = now()->format('Y-m-d');
        }

        if (!empty($followUpFilter['fu_date'])) {
            $followUpsQuery->whereDate('website_follow_ups.created_at', $followUpFilter['fu_date']);
        }
        
        if ($tab === 'follow-up') {
            $followUps = $followUpsQuery->paginate(50);
        } else {
            $followUps = collect();
        }

        // ── KPI Stats ─────────────────────────────────────────────────────────
        $stats = [
            'total'       => array_sum($statusCounts),
            'building'    => $tabCounts['build'] + $tabCounts['build-progress'],
            'live'        => $tabCounts['live'],
            'maintenance' => $tabCounts['maintenance'],
            'qc_pending'  => $tabCounts['qc-checking'],
            'follow_ups'  => $followUpsCount,
        ];

        // ── All classes for the filter dropdown ───────────────────────────────
        $allClasses = Website::where('is_archived', false)
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        // ── Category ordering ─────────────────────────────────────────────────
        $setting    = Setting::where('key', 'website_classes_order')->first();
        $orderArray = $setting ? json_decode($setting->value, true) : [];

        $existingCategories = $allClasses->toArray();
        $newCategories      = array_diff($existingCategories, $orderArray);
        if (!empty($newCategories)) {
            $orderArray = array_merge($orderArray, $newCategories);
            Setting::updateOrCreate(['key' => 'website_classes_order'], ['value' => json_encode($orderArray)]);
        }

        // Build grouped view for Build Website tab
        $groupedWebsites = collect();
        foreach ($orderArray as $categoryName) {
            $inCat = $buildWebsites->where('category', $categoryName)->values();
            if ($inCat->isNotEmpty()) {
                $groupedWebsites->put($categoryName, $inCat);
            }
        }
        $uncategorized = $buildWebsites->filter(fn($w) => empty($w->category))->values();
        if ($uncategorized->count() > 0) {
            $groupedWebsites->put('Uncategorized', $uncategorized);
        }

        $users = User::role(['digital-team', 'boss'])->orderBy('name')->get(['id', 'name', 'email']);
        $websiteMembers = WebsiteMember::with('user')->get();
        $memberRolesMap = $websiteMembers->pluck('role', 'user_id')->toArray();

        $websiteTeamMembers = User::whereIn('id', function($q) {
            $q->select('user_id')
              ->from('website_members')
              ->whereIn('role', ['Developer', 'QC']);
        })->orderBy('name')->get(['id', 'name', 'email']);

        $reportUsers = $users->concat($websiteTeamMembers)->unique('id')->sortBy('name')->values();

        // --- PERFORMANCE OPTIMIZATION: Relationships are already eager loaded via with($tabRelations) above ---

        // Fetch all non-archived websites for modals and real-time frontend filtering
        $allWebsites = Website::where('is_archived', false)->orderBy('name')->get(['id', 'name', 'url', 'handled_by', 'category', 'status']);

        return view('websites.index', compact(
            'tab', 'tabCounts', 'stats', 'groupedWebsites', 'orderArray',
            'buildWebsites', 'buildProgressWebsites', 'liveWebsites',
            'maintenanceWebsites', 'followUps', 'followUpFilter', 'users',
            'allClasses', 'websiteMembers', 'memberRolesMap',
            'qcCheckingWebsites', 'supervisorCheckingWebsites',
            'qcErrorWebsites', 'supervisorErrorWebsites', 'websiteTeamMembers', 'reportUsers',
            'allWebsites'
        ));
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'url'              => 'required|url|max:255',
            'category'         => 'nullable|string|max:255',
            'logo'             => 'nullable|image|max:2048',
            'logo_url'         => 'nullable|url|max:1000',
            'handled_by'       => 'nullable|exists:users,id',
            'start_date'       => 'nullable|date',
            'deadline'         => 'nullable|date|after_or_equal:start_date',
            'notes'            => 'nullable|string|max:5000',
        ]);

        $logoPath = $this->resolveLogoPath($request, null);

        $website = Website::create([
            'name'             => $validated['name'],
            'url'              => $validated['url'],
            'category'         => $validated['category'] ?? null,
            'logo_path'        => $logoPath,
            'handled_by'       => $validated['handled_by'] ?? null,
            'start_date'       => $validated['start_date'] ?? null,
            'deadline'         => $validated['deadline'] ?? null,
            'status'           => Website::STATUS_BUILD_WEBSITE,
            'progress_percent' => 0,
            'notes'            => $validated['notes'] ?? null,
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
        ]);

        // Log creation
        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'created',
            'note'         => 'Website project created.',
            'new_status'   => $website->status,
            'new_progress' => 0,
        ]);

        $this->logActivity('website_created', "Website \"{$website->name}\" created.");

        WebsiteActivityNotification::send($website, 'website_created', "New website \"{$website->name}\" created.");

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Website \"{$website->name}\" created successfully.",
                'website' => $website
            ], 201);
        }

        return redirect()->route('websites.index', ['tab' => 'build'])
            ->with('success', "Website \"{$website->name}\" created successfully.");
    }

    // ── UPDATE (basic details) ────────────────────────────────────────────────
    public function update(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'url'        => 'required|url|max:255',
            'category'   => 'nullable|string|max:255',
            'logo'       => 'nullable|image|max:2048',
            'logo_url'   => 'nullable|url|max:1000',
            'handled_by' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'deadline'   => 'nullable|date',
            'notes'      => 'nullable|string|max:5000',
        ]);

        $logoPath = $this->resolveLogoPath($request, $website->logo_path);

        $website->update([
            'name'       => $validated['name'],
            'url'        => $validated['url'],
            'category'   => $validated['category'] ?? null,
            'logo_path'  => $logoPath,
            'handled_by' => $validated['handled_by'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'deadline'   => $validated['deadline'] ?? null,
            'notes'      => $validated['notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        WebsiteActivityNotification::send($website, 'website_updated', "Website \"{$website->name}\" details updated.");

        return back()->with('success', "Website \"{$website->name}\" updated.");
    }

    // ── UPDATE BUILD PROGRESS ─────────────────────────────────────────────────
    public function updateProgress(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate([
            'percent' => 'required|integer|in:0,10,25,50,75,100',
            'note'    => 'required|string|min:5|max:2000',
        ]);

        $oldStatus   = $website->status;
        $oldProgress = $website->progress_percent;
        $newPercent  = (int) $validated['percent'];

        // Auto-determine status
        $newStatus = $oldStatus;
        if ($newPercent > 0 && $newPercent < 100) {
            $newStatus = Website::STATUS_BUILD_PROGRESS;
        } elseif ($newPercent === 100) {
            $newStatus = Website::STATUS_QC_CHECKING;
        }

        $website->update([
            'progress_percent' => $newPercent,
            'status'           => $newStatus,
            'updated_by'       => auth()->id(),
            'completed_at'     => $newPercent === 100 && !$website->completed_at ? now() : $website->completed_at,
        ]);

        // Save progress history log
        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => 'build',
            'user_id'    => auth()->id(),
            'percent'    => $newPercent,
            'note'       => $validated['note'],
            'created_at' => now(),
        ]);

        // Save activity log
        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'progress_updated',
            'note'         => "Build progress updated to {$newPercent}%: {$validated['note']}",
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => $oldProgress,
            'new_progress' => $newPercent,
        ]);

        $this->logActivity('progress_updated', "Build progress for \"{$website->name}\" updated to {$newPercent}%.");

        WebsiteActivityNotification::send($website, 'progress_updated', "Build progress for \"{$website->name}\" updated to {$newPercent}%.", $validated['note'] ?? null);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => 'build-progress'])
            ->with('success', "Build progress updated to {$newPercent}% for \"{$website->name}\".");
    }

    // ── APPROVE QC ────────────────────────────────────────────────────────────
    public function approveQc(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteQc(), 403);

        $validated = $request->validate([
            'qc_note' => 'nullable|string|max:2000',
            'qc_files' => 'nullable|array|max:8',
            'qc_files.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $files = $request->hasFile('qc_files') ? $request->file('qc_files') : [];
        $attachments = collect($files)->filter()->map(fn ($file) => $this->storeHistoryAttachmentFile($file))->values()->all();
        $attachment = $attachments[0] ?? ['path' => null, 'name' => null];

        $oldStatus = $website->status;
        $isMaintenanceFlow = in_array($oldStatus, [
            Website::STATUS_MAINTENANCE_QC_CHECKING,
            Website::STATUS_MAINTENANCE_QC_ERROR
        ]);
        $newStatus = $isMaintenanceFlow 
            ? Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING 
            : Website::STATUS_SUPERVISOR_CHECKING;

        $website->update([
            'status'         => $newStatus,
            'qc_approved_by' => auth()->id(),
            'qc_approved_at' => now(),
            'updated_by'     => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => $isMaintenanceFlow ? $website->maintenance_percent : $website->progress_percent,
            'note'       => 'QC Approved. Pending Supervisor approval.' . ($validated['qc_note'] ? " Note: {$validated['qc_note']}" : ''),
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'  => $website->id,
            'user_id'     => auth()->id(),
            'action'      => 'qc_approved',
            'note'        => 'QC approved. Pending Supervisor approval.' . ($validated['qc_note'] ? " Note: {$validated['qc_note']}" : ''),
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'old_progress'=> $website->progress_percent,
            'new_progress'=> $website->progress_percent,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachments' => $attachments,
        ]);

        $this->logActivity('qc_approved', "QC approved for \"{$website->name}\". Pending Supervisor approval.");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'qc_approved', "QC approved for \"{$website->name}\". Pending Supervisor approval.", $validated['qc_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$website->name}\" QC Approved. Now pending Supervisor approval."]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'build-progress'])
            ->with('success', "\"{$website->name}\" QC Approved. Now pending Supervisor approval.");
    }

    // ── REVERT QC ──────────────────────────────────────────────────────────────
    public function revertQc(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteQc(), 403);

        $validated = $request->validate([
            'revert_note' => 'nullable|string|max:2000',
            'revert_files' => 'nullable|array|max:8',
            'revert_files.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $files = $request->hasFile('revert_files') ? $request->file('revert_files') : [];
        $attachments = collect($files)->filter()->map(fn ($file) => $this->storeHistoryAttachmentFile($file))->values()->all();
        $attachment = $attachments[0] ?? ['path' => null, 'name' => null];

        $oldStatus = $website->status;
        $isMaintenanceFlow = in_array($oldStatus, [
            Website::STATUS_MAINTENANCE_QC_ERROR,
            Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR
        ]);
        $newStatus = $isMaintenanceFlow 
            ? Website::STATUS_MAINTENANCE_QC_CHECKING 
            : Website::STATUS_QC_CHECKING;

        $website->update([
            'status'         => $newStatus,
            'qc_approved_by' => null,
            'qc_approved_at' => null,
            'updated_by'     => auth()->id(),
        ]);

        $note = 'QC Approval Reverted. Sent back to QC Checking.' . (isset($validated['revert_note']) && $validated['revert_note'] ? " Note: {$validated['revert_note']}" : '');

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => $isMaintenanceFlow ? $website->maintenance_percent : $website->progress_percent,
            'note'       => $note,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'  => $website->id,
            'user_id'     => auth()->id(),
            'action'      => 'qc_reverted',
            'note'        => $note,
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'old_progress'=> $website->progress_percent,
            'new_progress'=> $website->progress_percent,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachments' => $attachments,
        ]);

        $this->logActivity('qc_reverted', "QC approval reverted for \"{$website->name}\".");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'qc_reverted', "QC approval reverted for \"{$website->name}\".", $validated['revert_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$website->name}\" QC Approval Reverted. Sent back to QC Checking."]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->back()
            ->with('success', "\"{$website->name}\" QC Approval Reverted. Sent back to QC Checking.");
    }

    // ── QC ERROR ───────────────────────────────────────────────────────────────
    public function qcError(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteQc(), 403);

        $validated = $request->validate([
            'error_note' => 'required|string|min:5|max:2000',
            'error_link' => 'nullable|string|max:1000',
            'error_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'error_files' => 'nullable|array|max:8',
            'error_files.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);
        $errorLink = $validated['error_link'] ?? null;
        $attachments = $this->storeErrorAttachments($request);
        $attachment = $attachments[0] ?? ['path' => null, 'name' => null];

        $oldStatus = $website->status;
        $isMaintenanceFlow = in_array($oldStatus, [
            Website::STATUS_MAINTENANCE_QC_CHECKING,
            Website::STATUS_MAINTENANCE_QC_ERROR
        ]);
        $newStatus = $isMaintenanceFlow
            ? Website::STATUS_MAINTENANCE_QC_ERROR
            : Website::STATUS_QC_ERROR;

        $website->update([
            'status'                => $newStatus,
            'error_note'            => $validated['error_note'],
            'error_link'            => $errorLink,
            'error_attachment_path' => $attachment['path'],
            'error_attachment_name' => $attachment['name'],
            'error_flagged_at'      => now(),
            'error_flagged_by'      => auth()->id(),
            'error_progress_percent'=> 0,
            'updated_by'            => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => 0,
            'note'       => "QC Error flagged: {$validated['error_note']}" . ($errorLink ? " | Link: {$errorLink}" : ''),
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'qc_error',
            'note'         => "QC Error flagged: {$validated['error_note']}" . ($errorLink ? " | Link: {$errorLink}" : ''),
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => $website->progress_percent,
            'new_progress' => 0,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachments' => $attachments,
        ]);

        $this->logActivity('qc_error', "QC flagged error for \"{$website->name}\".");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'qc_error', "QC Error flagged for \"{$website->name}\".", $validated['error_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$website->name}\" flagged as QC Error. Team must fix before re-approval."]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => 'qc-error'])
            ->with('success', "\"{$website->name}\" flagged as QC Error. Team must fix before re-approval.");
    }

    // ── SUPERVISOR ERROR ────────────────────────────────────────────────────────
    public function supervisorError(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteSupervisor(), 403);

        $validated = $request->validate([
            'error_note' => 'required|string|min:5|max:2000',
            'error_link' => 'nullable|string|max:1000',
            'error_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'error_files' => 'nullable|array|max:8',
            'error_files.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);
        $errorLink = $validated['error_link'] ?? null;
        $attachments = $this->storeErrorAttachments($request);
        $attachment = $attachments[0] ?? ['path' => null, 'name' => null];

        $oldStatus = $website->status;
        $isMaintenanceFlow = in_array($oldStatus, [
            Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR
        ]);
        $newStatus = $isMaintenanceFlow
            ? Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR
            : Website::STATUS_SUPERVISOR_ERROR;

        $website->update([
            'status'                => $newStatus,
            'error_note'            => $validated['error_note'],
            'error_link'            => $errorLink,
            'error_attachment_path' => $attachment['path'],
            'error_attachment_name' => $attachment['name'],
            'error_flagged_at'      => now(),
            'error_flagged_by'      => auth()->id(),
            'error_progress_percent'=> 0,
            'updated_by'            => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => 0,
            'note'       => "Supervisor Error flagged: {$validated['error_note']}" . ($errorLink ? " | Link: {$errorLink}" : ''),
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'supervisor_error',
            'note'         => "Supervisor Error flagged: {$validated['error_note']}" . ($errorLink ? " | Link: {$errorLink}" : ''),
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => $website->progress_percent,
            'new_progress' => 0,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachments' => $attachments,
        ]);

        $this->logActivity('supervisor_error', "Supervisor flagged error for \"{$website->name}\".");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'supervisor_error', "Supervisor Error flagged for \"{$website->name}\".", $validated['error_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$website->name}\" flagged as Supervisor Error. Team must fix before re-approval."]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => 'supervisor-error'])
            ->with('success', "\"{$website->name}\" flagged as Supervisor Error. Team must fix before re-approval.");
    }

    // ── UPDATE QC ERROR PROGRESS ───────────────────────────────────────────────────
    public function updateErrorProgress(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate([
            'percent' => 'required|integer|in:0,10,25,50,75,100',
            'note'    => 'required|string|min:5|max:2000',
        ]);

        $newPercent = (int) $validated['percent'];
        $oldStatus  = $website->status;
        $isMaintError = in_array($oldStatus, [
            Website::STATUS_MAINTENANCE_QC_ERROR,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
        ]);

        $website->update([
            'error_progress_percent' => $newPercent,
            'updated_by'             => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintError ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => $newPercent,
            'note'       => "Error fix progress: {$newPercent}%. {$validated['note']}",
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'error_progress_updated',
            'note'         => "Error fix progress: {$newPercent}%. {$validated['note']}",
            'old_status'   => $oldStatus,
            'new_status'   => $oldStatus,
            'old_progress' => $website->error_progress_percent,
            'new_progress' => $newPercent,
        ]);

        $tab = in_array($oldStatus, [Website::STATUS_QC_ERROR, Website::STATUS_MAINTENANCE_QC_ERROR]) ? 'qc-error' : 'supervisor-error';

        WebsiteActivityNotification::send($website, 'error_progress_updated', "Error fix progress for \"{$website->name}\" updated to {$newPercent}%.", $validated['note'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Error fix progress updated to {$newPercent}% for \"{$website->name}\".",
                'website' => $website
            ]);
        }

        return back()->with('success', "Error fix progress updated to {$newPercent}% for \"{$website->name}\".");
    }

    // ── COMPLETE QC ERROR (send back to QC Checking) ─────────────────────────────
    public function completeQcError(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteQc(), 403);

        $this->deleteErrorAttachment($website);

        $oldStatus = $website->status;
        $isMaintenanceFlow = ($oldStatus === Website::STATUS_MAINTENANCE_QC_ERROR);
        $newStatus = $isMaintenanceFlow
            ? Website::STATUS_MAINTENANCE
            : Website::STATUS_BUILDING;

        $website->update([
            'status'                 => $newStatus,
            'error_progress_percent' => 100,
            'error_note'             => null,
            'error_link'             => null,
            'error_attachment_path'  => null,
            'error_attachment_name'  => null,
            'error_flagged_at'       => null,
            'error_flagged_by'       => null,
            'updated_by'             => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => 100,
            'note'       => 'QC Error fix completed. Sent back to ' . ($isMaintenanceFlow ? 'Maintenance' : 'Build Progress') . '.',
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'qc_error_completed',
            'note'         => 'QC Error fix completed. Sent back to ' . ($isMaintenanceFlow ? 'Maintenance' : 'Build Progress') . '.',
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => 100,
            'new_progress' => 100,
        ]);

        $this->logActivity('qc_error_completed', "QC error fix completed for \"{$website->name}\". Sent back to " . ($isMaintenanceFlow ? 'Maintenance' : 'Build Progress') . ".");

        WebsiteActivityNotification::send($website, 'qc_error_completed', "QC error fix completed for \"{$website->name}\". Sent back to " . ($isMaintenanceFlow ? 'Maintenance' : 'Build Progress') . ".");

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'build-progress'])
            ->with('success', "\"{$website->name}\" error fix completed! Website is back to " . ($isMaintenanceFlow ? 'Maintenance' : 'Build Progress') . ".");
    }

    // ── COMPLETE SUPERVISOR ERROR (send back to Supervisor Checking) ──────────────
    public function completeSupervisorError(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteSupervisor(), 403);

        $this->deleteErrorAttachment($website);

        $oldStatus = $website->status;
        $isMaintenanceFlow = ($oldStatus === Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR);
        $newStatus = Website::STATUS_LIVE;

        $website->update([
            'status'                 => $newStatus,
            'error_progress_percent' => 100,
            'error_note'             => null,
            'error_link'             => null,
            'error_attachment_path'  => null,
            'error_attachment_name'  => null,
            'error_flagged_at'       => null,
            'error_flagged_by'       => null,
            'updated_by'             => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => 100,
            'note'       => 'Supervisor Error fix completed. Sent directly to Live.',
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'supervisor_error_completed',
            'note'         => 'Supervisor Error fix completed. Sent directly to Live.',
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => 100,
            'new_progress' => 100,
        ]);

        $this->logActivity('supervisor_error_completed', "Supervisor error fix completed for \"{$website->name}\". Sent directly to Live.");

        WebsiteActivityNotification::send($website, 'supervisor_error_completed', "Supervisor error fix completed for \"{$website->name}\". Sent directly to Live.");

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => 'live'])
            ->with('success', "\"{$website->name}\" Supervisor error fix done! Website is now Live.");
    }

    // ── APPROVE SUPERVISOR ────────────────────────────────────────────────────
    public function approveSupervisor(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteSupervisor(), 403);

        $validated = $request->validate([
            'supervisor_note' => 'nullable|string|max:2000',
            'supervisor_files' => 'nullable|array|max:8',
            'supervisor_files.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $files = $request->hasFile('supervisor_files') ? $request->file('supervisor_files') : [];
        $attachments = collect($files)->filter()->map(fn ($file) => $this->storeHistoryAttachmentFile($file))->values()->all();
        $attachment = $attachments[0] ?? ['path' => null, 'name' => null];

        $oldStatus = $website->status;
        $isMaintenanceFlow = in_array($oldStatus, [
            Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR
        ]);
        $newStatus = $isMaintenanceFlow 
            ? Website::STATUS_MAINTENANCE 
            : Website::STATUS_LIVE;

        $website->update([
            'status'                 => $newStatus,
            'supervisor_approved_by' => auth()->id(),
            'supervisor_approved_at' => now(),
            'live_at'                => $website->live_at ?? now(),
            'updated_by'             => auth()->id(),
            'error_progress_percent' => 0,
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => 100,
            'note'       => 'Supervisor Approved. Website is now ' . ($isMaintenanceFlow ? 'Maintenance' : 'Live') . '.' . ($validated['supervisor_note'] ? " Note: {$validated['supervisor_note']}" : ''),
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'  => $website->id,
            'user_id'     => auth()->id(),
            'action'      => 'supervisor_approved',
            'note'        => 'Supervisor approved. Website is now Live.' . ($validated['supervisor_note'] ? " Note: {$validated['supervisor_note']}" : ''),
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'old_progress'=> $website->progress_percent,
            'new_progress'=> 100,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachments' => $attachments,
        ]);

        $this->logActivity('supervisor_approved', "Supervisor approved \"{$website->name}\". Website is Live.");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'supervisor_approved', "Supervisor approved \"{$website->name}\". Website is Live.", $validated['supervisor_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$website->name}\" Supervisor Approved. Website is now Live!"]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'live'])
            ->with('success', "\"{$website->name}\" Supervisor Approved. Website is now Live!");
    }

    // ── START MAINTENANCE ─────────────────────────────────────────────────────
    public function startMaintenance(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate([
            'maintenance_note' => 'required|string|min:5|max:2000',
        ]);

        $oldStatus = $website->status;

        $website->update([
            'status'                 => Website::STATUS_MAINTENANCE,
            'maintenance_percent'    => 0,
            'maintenance_started_at' => now(),
            'maintenance_completed_at' => null,
            'updated_by'             => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => 'maintenance',
            'user_id'    => auth()->id(),
            'percent'    => 0,
            'note'       => "Maintenance started: {$validated['maintenance_note']}",
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'  => $website->id,
            'user_id'     => auth()->id(),
            'action'      => 'maintenance_started',
            'note'        => "Maintenance started: {$validated['maintenance_note']}",
            'old_status'  => $oldStatus,
            'new_status'  => Website::STATUS_MAINTENANCE,
            'old_progress'=> $website->maintenance_percent,
            'new_progress'=> 0,
        ]);

        $this->logActivity('maintenance_started', "Maintenance started for \"{$website->name}\".");

        WebsiteActivityNotification::send($website, 'maintenance_started', "Maintenance started for \"{$website->name}\".", $validated['maintenance_note'] ?? null);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index', ['tab' => 'maintenance'])
            ->with('success', "Maintenance started for \"{$website->name}\".");
    }

    // ── UPDATE MAINTENANCE PROGRESS ───────────────────────────────────────────
    public function updateMaintenanceProgress(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate([
            'percent' => 'required|integer|in:0,10,25,50,75,100',
            'note'    => 'required|string|min:5|max:2000',
        ]);

        $oldStatus   = $website->status;
        $oldProgress = $website->maintenance_percent;
        $newPercent  = (int) $validated['percent'];

        // Auto-complete maintenance at 100% (Goes to QC Checking)
        $newStatus = $newPercent === 100 ? Website::STATUS_MAINTENANCE_QC_CHECKING : Website::STATUS_MAINTENANCE;

        $website->update([
            'maintenance_percent'      => $newPercent,
            'status'                   => $newStatus,
            'updated_by'               => auth()->id(),
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => 'maintenance',
            'user_id'    => auth()->id(),
            'percent'    => $newPercent,
            'note'       => $validated['note'],
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => $newPercent === 100 ? 'maintenance_qc_pending' : 'maintenance_progress_updated',
            'note'         => "Maintenance progress: {$newPercent}%. {$validated['note']}",
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => $oldProgress,
            'new_progress' => $newPercent,
        ]);

        $this->logActivity(
            $newPercent === 100 ? 'maintenance_qc_pending' : 'maintenance_progress_updated',
            "Maintenance for \"{$website->name}\" updated to {$newPercent}%." . ($newPercent === 100 ? ' Pending QC Check.' : '')
        );

        WebsiteActivityNotification::send($website, 'maintenance_progress_updated', "Maintenance for \"{$website->name}\" updated to {$newPercent}%." . ($newPercent === 100 ? ' Pending QC Check.' : ''), $validated['note'] ?? null);

        $msg = $newPercent === 100
            ? "\"{$website->name}\" maintenance completed and is pending QC check."
            : "Maintenance progress updated to {$newPercent}% for \"{$website->name}\".";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return back()->with('success', $msg);
    }

    // ── EXPORT REPORT ─────────────────────────────────────────────────────────
    public function exportReport(Request $request)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $format = $request->get('format', 'csv');
        $tab = $request->get('tab', 'build');
        $startDateRaw = $request->get('start_date');
        $endDateRaw = $request->get('end_date');
        $memberId = $request->get('member_id');
        
        $filterStart = $startDateRaw ? \Carbon\Carbon::parse($startDateRaw, 'Asia/Phnom_Penh')->startOfDay()->setTimezone('UTC') : null;
        $filterEnd = $endDateRaw ? \Carbon\Carbon::parse($endDateRaw, 'Asia/Phnom_Penh')->endOfDay()->setTimezone('UTC') : null;

        $user = auth()->user();
        if (!$user?->hasAnyRole(['super-admin', 'admin-digital']) && !$user?->hasRole('boss')) {
            $memberId = $user->id;
        }

        if ($tab === 'follow-up') {
            $query = \App\Models\WebsiteFollowUp::with(['website', 'assignee', 'qcChecker'])
                ->whereHas('website', function ($q) {
                    $q->where('is_archived', false);
                });

            $targetUser = $memberId ? \App\Models\User::find($memberId) : auth()->user();
            $isQcReport = $targetUser && $targetUser->isQc();

            if ($isQcReport) {
                $query->whereNotNull('qc_checked_at');
                if ($filterStart) $query->where('qc_checked_at', '>=', $filterStart);
                if ($filterEnd) $query->where('qc_checked_at', '<=', $filterEnd);
                if ($memberId) $query->where('qc_checked_by', $memberId);
            } else {
                if ($filterStart) $query->where('created_at', '>=', $filterStart);
                if ($filterEnd) $query->where('created_at', '<=', $filterEnd);
                if ($memberId) {
                    $query->where(function($q) use ($memberId) {
                        $q->where('created_by', $memberId)->orWhere('assigned_to', $memberId);
                    });
                }
            }

            $followUps = $query->latest()->get();
            $download = $request->boolean('download');

            if (!$download) {
                return view('websites.reports.preview', [
                    'followUps' => $followUps,
                    'startDate' => $startDateRaw,
                    'endDate' => $endDateRaw,
                    'filterStart' => $filterStart,
                    'filterEnd' => $filterEnd,
                    'format' => $format,
                    'tab' => $tab,
                    'memberId' => $memberId,
                ]);
            }

            if ($format === 'csv') {
                return $this->exportFollowUpsCsv($followUps);
            }
            if (in_array($format, ['pdf', 'html'])) {
                $this->logActivity('report_exported', 'Follow Ups report exported as ' . strtoupper($format) . '.');
                $memberName = $memberId ? (\App\Models\User::find($memberId)?->name) : null;
                $pdfView = view('websites.pdf_followups', [
                    'followUps' => $followUps,
                    'startDate' => $startDateRaw,
                    'endDate' => $endDateRaw,
                    'memberName' => $memberName
                ]);
                
                if ($format === 'html') return $pdfView;
                
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($pdfView->render())
                    ->setOption(['isRemoteEnabled' => true])
                    ->setPaper('a4', 'landscape');
                return $pdf->download('follow-ups-report-' . now()->format('Y-m-d') . '.pdf');
            }
            return $this->exportFollowUpsCsv($followUps);
        }

        $query = Website::with([
            'handler', 'creator', 'qcApprover',
            'progressLogs.user', 'maintenanceLogs.user',
            'activityLogs.user',
        ])->where('is_archived', false);



        $query->orderBy('name');

        $targetUser = $memberId ? \App\Models\User::find($memberId) : null;
        $isQcReport = $targetUser && $targetUser->isQc();

        if ($memberId) {
            if ($isQcReport) {
                $query->where(function ($q) use ($memberId, $filterStart, $filterEnd) {
                    $q->whereHas('activityLogs', function($logQ) use ($memberId, $filterStart, $filterEnd) {
                        $logQ->where('user_id', $memberId);
                        if ($filterStart) $logQ->where('created_at', '>=', $filterStart);
                        if ($filterEnd)   $logQ->where('created_at', '<=', $filterEnd);
                    })
                    ->orWhereHas('followUps', function($fuQ) use ($memberId, $filterStart, $filterEnd) {
                        $fuQ->where('qc_checked_by', $memberId);
                        if ($filterStart) $fuQ->where('qc_checked_at', '>=', $filterStart);
                        if ($filterEnd)   $fuQ->where('qc_checked_at', '<=', $filterEnd);
                    })
                    ->orWhere(function($wsQ) use ($memberId, $filterStart, $filterEnd) {
                        $wsQ->where('qc_approved_by', $memberId);
                        if ($filterStart) $wsQ->where('qc_approved_at', '>=', $filterStart);
                        if ($filterEnd)   $wsQ->where('qc_approved_at', '<=', $filterEnd);
                    });
                });
            } else {
                $query->where('handled_by', $memberId);
            }
        }

        if ($filterStart || $filterEnd) {
            $query->where(function ($group) use ($filterStart, $filterEnd) {
                $group->where(function ($q) use ($filterStart, $filterEnd) {
                    if ($filterStart) $q->where('created_at', '>=', $filterStart);
                    if ($filterEnd) $q->where('created_at', '<=', $filterEnd);
                })->orWhereHas('progressLogs', function ($q) use ($filterStart, $filterEnd) {
                    if ($filterStart) $q->where('created_at', '>=', $filterStart);
                    if ($filterEnd) $q->where('created_at', '<=', $filterEnd);
                })->orWhereHas('maintenanceLogs', function ($q) use ($filterStart, $filterEnd) {
                    if ($filterStart) $q->where('created_at', '>=', $filterStart);
                    if ($filterEnd) $q->where('created_at', '<=', $filterEnd);
                })->orWhereHas('activityLogs', function ($q) use ($filterStart, $filterEnd) {
                    if ($filterStart) $q->where('created_at', '>=', $filterStart);
                    if ($filterEnd) $q->where('created_at', '<=', $filterEnd);
                });
            });
        }

        $websites = $query->get();



        $qcStats = null;
        if ($isQcReport && $memberId) {
            $qcStats = [
                'checked' => $websites->count(),
                'approved' => 0,
                'error' => 0,
                'comment' => 0,
            ];
            foreach ($websites as $ws) {
                $logs = $ws->activityLogs->where('user_id', $memberId);
                if ($filterStart) $logs = $logs->where('created_at', '>=', $filterStart);
                if ($filterEnd) $logs = $logs->where('created_at', '<=', $filterEnd);
                
                foreach ($logs as $log) {
                    if ($log->action === 'qc_approved') $qcStats['approved']++;
                    if ($log->action === 'qc_error') $qcStats['error']++;
                    if ($log->action === 'history_comment' || str_contains(strtolower($log->action ?? ''), 'comment')) $qcStats['comment']++;
                }
            }
        }

        $download = $request->boolean('download');

        if (!$download && in_array($format, ['pdf', 'csv', 'preview'])) {
            return view('websites.reports.preview', [
                'websites' => $websites,
                'startDate' => $startDateRaw,
                'endDate' => $endDateRaw,
                'filterStart' => $filterStart,
                'filterEnd' => $filterEnd,
                'format' => $format,
                'tab' => $tab,
                'memberId' => $memberId,
                'qcStats' => $qcStats
            ]);
        }

        if (in_array($format, ['pdf', 'html'])) {
            $this->logActivity('report_exported', 'All Websites report exported as ' . strtoupper($format) . '.');
            $pdfView = view('websites.pdf', [
                'websites' => $websites,
                'startDate' => $startDateRaw,
                'endDate' => $endDateRaw,
                'filterStart' => $filterStart,
                'filterEnd' => $filterEnd,
                'qcStats' => $qcStats
            ]);
            
            if ($format === 'html') return $pdfView;
            
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($pdfView->render())
                ->setOption(['isRemoteEnabled' => true])
                ->setPaper('a4', 'landscape');
            return $pdf->download('all-websites-report-' . now()->format('Y-m-d') . '.pdf');
        }

        // CSV fallback
        return $this->exportCsv($websites, $filterStart, $filterEnd, $qcStats);
    }

    public function exportPersonalReport(Request $request)
    {
        abort_unless(auth()->user()?->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        $format = $request->get('format', 'csv');
        $userId = auth()->id();

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();

        if ($request->filled('date_range') && $request->date_range !== 'all_time') {
            switch ($request->date_range) {
                case 'today':
                    $dateFrom = now()->startOfDay()->toDateString();
                    $dateTo   = now()->endOfDay()->toDateString();
                    break;
                case 'this_week':
                    $dateFrom = now()->startOfWeek()->toDateString();
                    $dateTo   = now()->endOfWeek()->toDateString();
                    break;
                case 'this_month':
                    $dateFrom = now()->startOfMonth()->toDateString();
                    $dateTo   = now()->endOfMonth()->toDateString();
                    break;
                case 'last_month':
                    $dateFrom = now()->subMonth()->startOfMonth()->toDateString();
                    $dateTo   = now()->subMonth()->endOfMonth()->toDateString();
                    break;
                case 'custom':
                case 'custom_period':
                    if ($request->filled('start_date')) $dateFrom = \Carbon\Carbon::parse($request->start_date)->toDateString();
                    if ($request->filled('end_date'))   $dateTo   = \Carbon\Carbon::parse($request->end_date)->toDateString();
                    break;
            }
        } else {
            $dateFrom = '2000-01-01';
            $dateTo   = '2100-01-01';
        }

        $progressLogs = \App\Models\WebsiteProgressLog::with(['website', 'user'])
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->get()
            ->map(function($log) {
                return [
                    'date' => $log->created_at,
                    'website' => $log->website->name ?? 'Unknown',
                    'action' => 'Progress Update',
                    'details' => $log->percent . '% - ' . strip_tags($log->note),
                    'user' => $log->user->name ?? '',
                ];
            });

        $maintenanceLogs = \App\Models\WebsiteMaintenanceLog::with(['website', 'user'])
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->get()
            ->map(function($log) {
                $statusMsg = $log->old_status !== $log->new_status ? "Status changed from {$log->old_status} to {$log->new_status}. " : "";
                return [
                    'date' => $log->created_at,
                    'website' => $log->website->name ?? 'Unknown',
                    'action' => 'Status/Maintenance Update',
                    'details' => $statusMsg . strip_tags($log->note),
                    'user' => $log->user->name ?? '',
                ];
            });

        $followUpQc = collect();
        if (auth()->user()?->isQcOrSupervisor()) {
             $followUpQc = \App\Models\WebsiteFollowUp::with(['website', 'qcChecker'])
                ->whereNotNull('qc_checked_at')
                ->whereBetween('qc_checked_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->when($userId, fn($q) => $q->where('qc_checked_by', $userId))
                ->get()
                ->map(function($fu) {
                    return [
                        'date' => $fu->qc_checked_at,
                        'website' => $fu->website->name ?? 'Unknown',
                        'action' => 'Follow-up QC (' . ucfirst($fu->qc_status) . ')',
                        'details' => $fu->title,
                        'user' => $fu->qcChecker->name ?? '',
                    ];
                });
        }

        $activities = $progressLogs->concat($maintenanceLogs)->concat($followUpQc)->sortBy('date');

        // Show preview for pdf/default format (not csv, not download=1)
        if ($format !== 'csv' && !$request->boolean('download')) {
            $userModel = $userId ? \App\Models\User::find($userId) : null;
            return view('websites.reports.website-personal-preview', [
                'activities' => $activities,
                'dateFrom'   => $dateFrom,
                'dateTo'     => $dateTo,
                'format'     => $format,
                'user'       => $userModel,
            ]);
        }

        if ($format === 'pdf' || $request->boolean('download')) {
            $userModel = $userId ? \App\Models\User::find($userId) : null;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('websites.reports.personal-pdf', [
                'activities' => $activities,
                'user' => $userModel,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ])->setPaper('a4', 'landscape');
            return $pdf->download('website-personal-report-' . now()->format('Y-m-d') . '.pdf');
        }

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="website-personal-report-' . now()->format('Y-m-d') . '.csv"',
            'Pragma'              => 'no-cache',
        ];

        $callback = function () use ($activities) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'User', 'Website', 'Action', 'Details']);

            foreach ($activities as $act) {
                fputcsv($handle, [
                    ($act['date'] instanceof \DateTimeInterface ? $act['date']->format('Y-m-d H:i:s') : $act['date']),
                    $act['user'],
                    $act['website'],
                    $act['action'],
                    $act['details'],
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportFollowUpsCsv($followUps)
    {
        $this->logActivity('report_exported', 'Follow Ups report exported as CSV.');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="follow-ups-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($followUps) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Website', 'Class', 'Type', 'URL', 'Handle by', 'Status',
                'Note', 'Created At', 'QC Status', 'QC Checker', 'QC Note', 'QC Checked At'
            ]);

            foreach ($followUps as $fu) {
                fputcsv($handle, [
                    $fu->website->name ?? 'Unknown',
                    $fu->website->category ?? 'Uncategorized',
                    $fu->getTypeLabel(),
                    $fu->url ?? '',
                    $fu->assignee?->name ?? 'Unassigned',
                    $fu->status,
                    strip_tags($fu->note ?? ''),
                    $fu->created_at->format('d/m/Y H:i'),
                    $fu->qc_status,
                    $fu->qcChecker?->name ?? '',
                    strip_tags($fu->qc_note ?? ''),
                    $fu->qc_checked_at?->format('d/m/Y H:i') ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportCsv($websites, $startDate = null, $endDate = null, $qcStats = null)
    {
        $this->logActivity('report_exported', 'All Websites report exported as CSV.');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="all-websites-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($websites, $startDate, $endDate, $qcStats) {
            $handle = fopen('php://output', 'w');

            if (isset($qcStats)) {
                fputcsv($handle, ['QC REPORT SUMMARY']);
                fputcsv($handle, ['Total Checked', 'QC Approved', 'QC Errors', 'Comments']);
                fputcsv($handle, [$qcStats['checked'], $qcStats['approved'], $qcStats['error'], $qcStats['comment']]);
                fputcsv($handle, []);
            }

            // ── SECTION 1: Website Summary Header ────────────────────────────────────
            fputcsv($handle, [
                'TYPE',
                'Website Name',
                'Website URL',
                'Status',
                'Current Progress %',
                'Handler / Member',
                'Class / Category',
                'Start Date',
                'Deadline',
                'Live At',
                'QC Approved By',
                'QC Approved At',
                'Canva / Reference Link',
                'Current Error Note',
                'Total Updates (All Time)',
                'Updates in Date Range',
                'General Notes',
                'Created At',
            ]);

            foreach ($websites as $ws) {
                // ── Combine History ───────────────────────────────────────────────
                $historyRecords = collect();

                // Progress Logs
                foreach ($ws->progressLogs as $log) {
                    $historyRecords->push([
                        'date' => $log->created_at,
                        'user' => $log->user?->name ?? 'System',
                        'type' => strtoupper($log->type ?? 'BUILD'),
                        'detail' => $log->percent . '%',
                        'note' => strip_tags($log->note ?? ''),
                    ]);
                }

                // Maintenance Logs
                foreach ($ws->maintenanceLogs as $log) {
                    $historyRecords->push([
                        'date' => $log->created_at,
                        'user' => $log->user?->name ?? 'System',
                        'type' => strtoupper($log->type ?? 'MAINTENANCE'),
                        'detail' => $log->percent . '%',
                        'note' => strip_tags($log->note ?? ''),
                    ]);
                }

                // Activity Logs (Status Changes etc)
                foreach ($ws->activityLogs as $log) {
                    $detail = '';
                    if ($log->old_status && $log->new_status && $log->old_status !== $log->new_status) {
                        $detail .= $log->old_status . ' -> ' . $log->new_status;
                    }
                    if ($log->old_progress !== null && $log->new_progress !== null && $log->old_progress !== $log->new_progress) {
                        $detail .= ($detail ? ' | ' : '') . $log->old_progress . '% -> ' . $log->new_progress . '%';
                    }
                    $historyRecords->push([
                        'date' => $log->created_at,
                        'user' => $log->user?->name ?? 'System',
                        'type' => strtoupper($log->action ?? 'ACTIVITY'),
                        'detail' => $detail ?: '-',
                        'note' => strip_tags($log->note ?? ''),
                    ]);
                }

                // Sort history chronologically
                $historyRecords = $historyRecords->sortBy('date');

                // Filter by date if necessary
                $filteredHistory = $historyRecords;
                if ($startDate || $endDate) {
                    $filteredHistory = $historyRecords->filter(function($record) use ($startDate, $endDate) {
                        $logDate = $record['date'];
                        if ($startDate && $logDate < $startDate) return false;
                        if ($endDate && $logDate > $endDate) return false;
                        return true;
                    });
                }

                // Current progress display
                $progressDisplay = $ws->status === 'Maintenance'
                    ? 'Maint: ' . $ws->maintenance_percent . '%'
                    : $ws->progress_percent . '%';

                // ── Website Summary Row ───────────────────────────────────────────
                fputcsv($handle, [
                    'WEBSITE',
                    $ws->name,
                    $ws->url,
                    $ws->status,
                    $progressDisplay,
                    $ws->handler?->name ?? 'Unassigned',
                    $ws->category ?? 'Uncategorized',
                    $ws->start_date?->format('d/m/Y') ?? '',
                    $ws->deadline?->format('d/m/Y') ?? '',
                    $ws->live_at?->format('d/m/Y H:i') ?? '',
                    $ws->qcApprover?->name ?? '',
                    $ws->qc_approved_at?->format('d/m/Y H:i') ?? '',
                    $ws->error_link ?? '',
                    strip_tags($ws->error_note ?? ''),
                    $historyRecords->count(),
                    $filteredHistory->count(),
                    strip_tags($ws->notes ?? ''),
                    $ws->created_at->format('d/m/Y H:i'),
                ]);

                // ── History Update Rows ───────────────────────────────────────────
                if ($filteredHistory->count() > 0) {
                    fputcsv($handle, [
                        '  [HISTORY]',
                        'Date & Time',
                        'Updated By',
                        'Type',
                        'Detail / Percentage',
                        'Update Reason / Note',
                        '', '', '', '', '', '', '', '', '', '', '', ''
                    ]);

                    foreach ($filteredHistory as $record) {
                        fputcsv($handle, [
                            '  -> Update',
                            $record['date']->format('d/m/Y H:i'),
                            $record['user'],
                            $record['type'],
                            $record['detail'],
                            $record['note'],
                            '', '', '', '', '', '', '', '', '', '', '', ''
                        ]);
                    }
                }

                // ── Follow Up Rows ─────────────────────────────────────────────────
                if ($ws->followUps->count() > 0) {
                    fputcsv($handle, [
                        '  [FOLLOW-UPS]',
                        'Created At',
                        'Type',
                        'Page Title',
                        'Page URL',
                        'Assigned To',
                        'Note',
                        'QC Status',
                        'QC Checker',
                        'QC Note',
                        'QC Checked At',
                        '', '', '', '', '', '', ''
                    ]);
                    foreach ($ws->followUps as $fu) {
                        fputcsv($handle, [
                            '  → Follow Up',
                            $fu->created_at->format('d/m/Y H:i'),
                            $fu->getTypeLabel(),
                            $fu->title ?? '',
                            $fu->url ?? '',
                            $fu->assignee?->name ?? 'Unassigned',
                            strip_tags($fu->note ?? ''),
                            $fu->qc_status ?? '',
                            $fu->qcChecker?->name ?? '',
                            strip_tags($fu->qc_note ?? ''),
                            $fu->qc_checked_at?->format('d/m/Y H:i') ?? '',
                            '', '', '', '', '', '', ''
                        ]);
                    }
                }

                // Blank separator row between websites
                fputcsv($handle, array_fill(0, 18, ''));
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────
    public function destroy(Website $website)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

        if ($website->logo_path && !str_starts_with($website->logo_path, 'http')
            && Storage::disk('public')->exists($website->logo_path)) {
            Storage::disk('public')->delete($website->logo_path);
        }

        $this->deleteErrorAttachment($website);

        $website->delete();

        return back()->with('success', 'Website removed successfully.');
    }

    public function viewErrorAttachment(Website $website)
    {
        $this->authorizeWebsiteAttachmentAccess();

        return $this->publicAttachmentResponse(
            $website->error_attachment_path,
            $website->error_attachment_name,
            false
        );
    }

    public function downloadErrorAttachment(Website $website)
    {
        $this->authorizeWebsiteAttachmentAccess();

        return $this->publicAttachmentResponse(
            $website->error_attachment_path,
            $website->error_attachment_name,
            true
        );
    }

    public function viewHistoryAttachment(Request $request, $id)
    {
        $this->authorizeWebsiteAttachmentAccess();

        $log = WebsiteMaintenanceLog::findOrFail($id);
        $attachment = $this->resolveHistoryAttachment($log, $request->query('file'));

        return $this->publicAttachmentResponse($attachment['path'] ?? null, $attachment['name'] ?? null, false);
    }

    public function downloadHistoryAttachment(Request $request, $id)
    {
        $this->authorizeWebsiteAttachmentAccess();

        $log = WebsiteMaintenanceLog::findOrFail($id);
        $attachment = $this->resolveHistoryAttachment($log, $request->query('file'));

        return $this->publicAttachmentResponse($attachment['path'] ?? null, $attachment['name'] ?? null, true);
    }

    private function storeErrorAttachments(Request $request): array
    {
        $files = [];

        if ($request->hasFile('error_files')) {
            $files = $request->file('error_files');
        } elseif ($request->hasFile('error_file')) {
            $files = [$request->file('error_file')];
        }

        return collect($files)->filter()->map(fn ($file) => $this->storeHistoryAttachmentFile($file))->values()->all();
    }

    private function storeHistoryAttachmentFile($file): array
    {
        $path = $file->store('website-error-references', 'public');
        
        \App\Jobs\ConvertImageToWebp::dispatch(null, null, null, $path, null)->delay(now()->addMinutes(2));

        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    private function deleteErrorAttachment(Website $website): void
    {
        $this->deletePublicAttachmentIfUnreferenced($website->error_attachment_path, $website->id);
    }

    private function authorizeWebsiteAttachmentAccess(): void
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);
    }

    private function authorizeWebsiteErrorHistoryManagement(WebsiteMaintenanceLog $log): void
    {
        $user = auth()->user();
        $isAuthor = $user->id === $log->user_id;
        $canManage = $user?->canApproveWebsiteQc() || $user?->canApproveWebsiteSupervisor() || $user?->hasRole('super-admin');
        
        $allowedAction = in_array($log->action, ['qc_error', 'supervisor_error', 'comment'], true);
        
        $hasPermission = false;
        if ($log->action === 'comment') {
            $hasPermission = $isAuthor || $canManage;
        } else {
            $hasPermission = $canManage;
        }

        abort_unless(
            $allowedAction && $hasPermission,
            403,
            'You do not have permission to manage this log.'
        );
    }

    private function resolveHistoryAttachment(WebsiteMaintenanceLog $log, ?string $fileId): ?array
    {
        $attachments = $this->normalizedHistoryAttachments($log);

        if ($fileId) {
            return collect($attachments)->firstWhere('id', $fileId);
        }

        return $attachments[0] ?? null;
    }

    private function normalizedHistoryAttachments(WebsiteMaintenanceLog $log): array
    {
        $attachments = collect($log->attachments ?: [])
            ->filter(fn ($file) => ! empty($file['path']))
            ->values()
            ->all();

        if (empty($attachments) && $log->attachment_path) {
            $attachments[] = [
                'id' => 'legacy',
                'path' => $log->attachment_path,
                'name' => $log->attachment_name ?: basename($log->attachment_path),
            ];
        }

        return $attachments;
    }

    private function publicAttachmentResponse(?string $path, ?string $name, bool $download)
    {
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Attached file not found.');

        $filename = $this->safeAttachmentFilename($name ?: basename($path));

        if ($download) {
            return Storage::disk('public')->download($path, $filename);
        }

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function safeAttachmentFilename(string $filename): string
    {
        $filename = str_replace(["\\", '"', "\r", "\n"], '', $filename);

        return $filename !== '' ? $filename : 'attachment';
    }

    private function deletePublicAttachmentIfUnreferenced(?string $path, ?int $exceptWebsiteId = null, ?int $exceptMaintenanceLogId = null): void
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return;
        }

        $websiteQuery = Website::where('error_attachment_path', $path);
        if ($exceptWebsiteId) {
            $websiteQuery->where('id', '!=', $exceptWebsiteId);
        }

        $logQuery = WebsiteMaintenanceLog::where('attachment_path', $path);
        if ($exceptMaintenanceLogId) {
            $logQuery->where('id', '!=', $exceptMaintenanceLogId);
        }

        if ($websiteQuery->exists() || $logQuery->exists()) {
            return;
        }

        $referencedInAttachmentList = WebsiteMaintenanceLog::whereNotNull('attachments')
            ->when($exceptMaintenanceLogId, fn ($query) => $query->where('id', '!=', $exceptMaintenanceLogId))
            ->get(['attachments'])
            ->contains(function (WebsiteMaintenanceLog $log) use ($path) {
                return collect($log->attachments ?: [])->contains(fn ($file) => ($file['path'] ?? null) === $path);
            });

        if ($referencedInAttachmentList) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    // ── CATEGORY ACTIONS ──────────────────────────────────────────────────────

    public function renameCategory(Request $request)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);
        
        $validated = $request->validate([
            'old_category' => 'required|string|max:255',
            'new_category' => 'required|string|max:255',
        ]);
        
        $old = $validated['old_category'] === 'Uncategorized' ? null : $validated['old_category'];
        Website::where('category', $old)->update(['category' => $validated['new_category']]);
        
        if ($old) {
            $setting = Setting::where('key', 'website_classes_order')->first();
            if ($setting) {
                $orderArray = json_decode($setting->value, true) ?? [];
                $index      = array_search($old, $orderArray);
                if ($index !== false) {
                    $orderArray[$index] = $validated['new_category'];
                    $setting->update(['value' => json_encode($orderArray)]);
                }
            }
        }
        
        return back()->with('success', "Group renamed successfully.");
    }
    
    public function storeCategory(Request $request)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate(['name' => 'required|string|max:255']);

        $setting    = Setting::firstOrCreate(['key' => 'website_classes_order'], ['value' => '[]']);
        $orderArray = json_decode($setting->value, true) ?? [];

        if (!in_array($validated['name'], $orderArray)) {
            $orderArray[] = $validated['name'];
            $setting->update(['value' => json_encode($orderArray)]);
        }

        return back()->with('success', "Group '{$validated['name']}' created successfully.");
    }

    public function destroyCategory(Request $request)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate(['category' => 'required|string|max:255']);

        Website::where('category', $validated['category'])->update(['category' => null]);

        $setting = Setting::where('key', 'website_classes_order')->first();
        if ($setting) {
            $orderArray = json_decode($setting->value, true) ?? [];
            $orderArray = array_values(array_filter($orderArray, fn($c) => $c !== $validated['category']));
            $setting->update(['value' => json_encode($orderArray)]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Group '{$validated['category']}' removed."]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(["success" => true]);
        }
        return redirect()->route('websites.index')
            
            ->with('success', "Group '{$validated['category']}' removed.");
    }

    public function reorderCategory(Request $request)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $validated = $request->validate([
            'categories'   => 'required|array',
            'categories.*' => 'string|max:255',
        ]);

        $setting = Setting::where('key', 'website_classes_order')->first();
        if ($setting) {
            $setting->update(['value' => json_encode($validated['categories'])]);
        }

        return back()->with('success', 'Group reordered.');
    }

    // ── HISTORY LOGS ──────────────────────────────────────────────────────────


    public function addHistoryComment(Request $request, Website $website)
    {
        try {
            abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

            $validated = $request->validate([
                'note'          => 'nullable|string|max:2000',
                'attachments'   => 'nullable|array|max:8',
                'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            ]);

            $noteText = trim($validated['note'] ?? '');
            if ($noteText === '' && !$request->hasFile('attachments')) {
                return response()->json(['success' => false, 'message' => 'Please enter a comment or attach a file.'], 422);
            }

            $files = $request->hasFile('attachments') ? $request->file('attachments') : [];
            $attachments = collect($files)
                ->filter()
                ->map(fn ($file) => $this->storeHistoryAttachmentFile($file))
                ->values()
                ->all();

            $firstAttachment = $attachments[0] ?? null;

            // Save to WebsiteMaintenanceLog — this is what getHistory() reads
            $log = WebsiteMaintenanceLog::create([
                'website_id'      => $website->id,
                'user_id'         => auth()->id(),
                'action'          => 'comment',
                'note'            => $noteText ?: null,
                'new_status'      => $website->status,
                'new_progress'    => $website->progress_percent ?? 0,
                'attachments'     => $attachments ?: null,
                'attachment_path' => $firstAttachment['path'] ?? null,
                'attachment_name' => $firstAttachment['name'] ?? null,
            ]);

            $this->logActivity('history_comment', "Comment added to history for \"{$website->name}\".");

            return response()->json(['success' => true, 'log' => $log->id, 'message' => 'Comment added.']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => basename($e->getFile()),
            ], 500);
        }
    }

    public function destroyHistoryLog(Request $request, $id)
    {
        $log = WebsiteMaintenanceLog::findOrFail($id);
        
        $user = auth()->user();
        $isAuthor = $user->id === $log->user_id;
        $canManage = $user?->canApproveWebsiteQc() || $user?->canApproveWebsiteSupervisor() || $user?->hasRole('super-admin');
        
        $allowedAction = in_array($log->action, ['qc_error', 'supervisor_error', 'comment'], true);
        
        $hasPermission = false;
        if ($log->action === 'comment') {
            $hasPermission = $isAuthor || $canManage;
        } else {
            $hasPermission = $canManage;
        }
        
        abort_unless(
            $allowedAction && $hasPermission,
            403,
            'You do not have permission to delete this log.'
        );

        if ($log->attachment_path) {
            Storage::disk('public')->delete($log->attachment_path);
        }
        
        $attachments = $this->normalizedHistoryAttachments($log);
        foreach ($attachments as $file) {
            if (!empty($file['path'])) {
                Storage::disk('public')->delete($file['path']);
            }
        }

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', 'History log deleted.');
        }
        return back()->with('success', 'History log deleted.');
    }

    public function updateHistoryLog(Request $request, $id)
    {
        $validated = $request->validate([
            'note' => 'required|string|min:5|max:2000',
            'remove_file_ids' => 'nullable|array',
            'remove_file_ids.*' => 'string|max:100',
            'attachments' => 'nullable|array|max:8',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $log = WebsiteMaintenanceLog::findOrFail($id);
        $this->authorizeWebsiteErrorHistoryManagement($log);

        $removeIds = collect($validated['remove_file_ids'] ?? []);
        $removedPaths = [];
        $attachments = collect($this->normalizedHistoryAttachments($log))
            ->reject(function ($file) use ($removeIds, &$removedPaths) {
                $remove = $removeIds->contains((string) ($file['id'] ?? ''))
                    || $removeIds->contains((string) ($file['path'] ?? ''));
                if ($remove && ! empty($file['path'])) {
                    $removedPaths[] = $file['path'];
                }
                return $remove;
            })
            ->values()
            ->all();

        foreach ($request->file('attachments', []) as $file) {
            $attachments[] = $this->storeHistoryAttachmentFile($file);
        }

        $log->update([
            'note' => $validated['note'],
            'attachments' => array_values($attachments),
            'attachment_path' => $attachments[0]['path'] ?? null,
            'attachment_name' => $attachments[0]['name'] ?? null,
        ]);

        foreach ($removedPaths as $path) {
            $this->deletePublicAttachmentIfUnreferenced($path, null, $log->id);
        }

        return $request->filled('redirect_to')
            ? redirect($request->input('redirect_to'))->with('success', 'History updated.')
            : back()->with('success', 'History updated.');
    }

    public function addHistoryAttachments(Request $request, $id)
    {
        $validated = $request->validate([
            'attachments' => 'required|array|min:1|max:8',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $log = WebsiteMaintenanceLog::findOrFail($id);
        $this->authorizeWebsiteErrorHistoryManagement($log);

        $attachments = $this->normalizedHistoryAttachments($log);
        foreach ($request->file('attachments', []) as $file) {
            $attachments[] = $this->storeHistoryAttachmentFile($file);
        }

        $log->update([
            'attachments' => array_values($attachments),
            'attachment_path' => $attachments[0]['path'] ?? null,
            'attachment_name' => $attachments[0]['name'] ?? null,
        ]);

        return $request->filled('redirect_to')
            ? redirect($request->input('redirect_to'))->with('success', 'Attachment added.')
            : back()->with('success', 'Attachment added.');
    }

    public function updateHistoryAttachment(Request $request, $id)
    {
        $validated = $request->validate([
            'attachment' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $log = \App\Models\WebsiteMaintenanceLog::findOrFail($id);

        $this->authorizeWebsiteErrorHistoryManagement($log);

        $attachments = $this->normalizedHistoryAttachments($log);
        $fileId = $request->input('file_id');
        $index = $fileId
            ? collect($attachments)->search(fn ($file) => ($file['id'] ?? null) === $fileId)
            : 0;

        abort_if($index === false || ! isset($attachments[$index]), 404, 'Attachment not found.');

        $oldPath = $attachments[$index]['path'] ?? null;
        $attachments[$index] = $this->storeHistoryAttachmentFile($request->file('attachment'));
        $log->update([
            'attachments' => array_values($attachments),
            'attachment_path' => $attachments[0]['path'] ?? null,
            'attachment_name' => $attachments[0]['name'] ?? null,
        ]);

        $this->deletePublicAttachmentIfUnreferenced($oldPath, null, $log->id);

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', 'Attachment updated successfully.');
        }
        return back()->with('success', 'Attachment updated successfully.');
    }

    public function destroyHistoryAttachment(Request $request, $id, ?string $fileId = null)
    {
        $log = \App\Models\WebsiteMaintenanceLog::findOrFail($id);

        $this->authorizeWebsiteErrorHistoryManagement($log);

        $fileId = $fileId ?: $request->input('file_id');
        $attachments = $this->normalizedHistoryAttachments($log);
        $index = $fileId
            ? collect($attachments)->search(fn ($file) => ($file['id'] ?? null) === $fileId)
            : 0;

        abort_if($index === false || ! isset($attachments[$index]), 404, 'Attachment not found.');

        $oldPath = $attachments[$index]['path'] ?? null;
        array_splice($attachments, $index, 1);
        $log->update([
            'attachments' => array_values($attachments),
            'attachment_path' => $attachments[0]['path'] ?? null,
            'attachment_name' => $attachments[0]['name'] ?? null,
        ]);

        $this->deletePublicAttachmentIfUnreferenced($oldPath, null, $log->id);

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', 'Attachment removed from log.');
        }
        return back()->with('success', 'Attachment removed from log.');
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────

    private function resolveLogoPath(Request $request, ?string $existing): ?string
    {
        if ($request->hasFile('logo')) {
            if ($existing && !str_starts_with($existing, 'http') && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }
            return $request->file('logo')->store('websites', 'public');
        }

        if ($request->filled('logo_url')) {
            if ($existing && !str_starts_with($existing, 'http') && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }
            return $request->input('logo_url');
        }

        return $existing;
    }

    public function getHistory(Website $website)
    {
        abort_unless(auth()->user()?->hasWebsiteAccess(), 403);

        $logs = $website->activityLogs()->with(['user'])->orderByDesc('created_at')->get();

        $formatted = $logs->map(function ($log) {
            $attachments = collect($log->attachments ?: [])
                ->filter(fn ($file) => ! empty($file['path']))
                ->values()
                ->all();

            if (empty($attachments) && $log->attachment_path) {
                $attachments[] = [
                    'id' => 'legacy',
                    'path' => $log->attachment_path,
                    'name' => $log->attachment_name ?: basename($log->attachment_path),
                ];
            }

            $firstAttachment = $attachments[0] ?? null;

            return [
                'id' => $log->id,
                'new_status' => $log->new_status,
                'action' => $log->action,
                'percent' => $log->percent ?? $log->new_progress ?? 0,
                'created_at' => $log->created_at?->toIso8601String(),
                'note' => $log->note,
                'attachments' => $attachments,
                'attachment_path' => $firstAttachment['path'] ?? null,
                'attachment_name' => $firstAttachment['name'] ?? null,
                'user' => $log->user ? ['name' => $log->user->name] : null,
                'user_id' => $log->user_id,
            ];
        });

        return response()->json($formatted);
    }

    private function logActivity(string $action, string $description): void
    {
        try {
            ActivityLog::create([
                'user_id'     => auth()->id(),
                'action'      => $action,
                'description' => $description,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail if activity log table has different schema
        }
    }

    // ── STORE WEBSITE MEMBER ──────────────────────────────────────────────────
    public function storeMember(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

        $validated = $request->validate([
            'user_id'    => 'nullable|exists:users,id',
            'user_ids'   => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'role'       => 'required|string|in:QC,Supervisor,Developer,Viewer',
        ]);

        $userIds = [];
        if (!empty($validated['user_ids'])) {
            $userIds = $validated['user_ids'];
        } elseif (!empty($validated['user_id'])) {
            $userIds = [$validated['user_id']];
        }

        if (empty($userIds)) {
            return redirect()->back()->withErrors(['user_ids' => 'Please select at least one user.']);
        }

        foreach ($userIds as $uid) {
            WebsiteMember::updateOrCreate(
                ['user_id' => $uid],
                ['role'    => $validated['role']]
            );
        }

        $names = User::whereIn('id', $userIds)->pluck('name')->toArray();
        $namesStr = implode(', ', $names);
        $this->logActivity('website_member_added', "Added user(s) \"{$namesStr}\" to websites with role \"{$validated['role']}\".");

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Website member(s) added/updated successfully."]);
        }

        return redirect()->back()
            ->with('success', "Website member(s) added/updated successfully.");
    }

    // ── DESTROY WEBSITE MEMBER ────────────────────────────────────────────────
    public function destroyMember($id)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

        $member = WebsiteMember::findOrFail($id);
        $userName = $member->user?->name ?? 'Unknown';
        $member->delete();

        $this->logActivity('website_member_removed', "Removed user \"{$userName}\" from websites members.");

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Website member removed successfully."]);
        }

        return redirect()->back()
            ->with('success', "Website member removed successfully.");
    }

    /**
     * Check website availability
     */
    public function ping(Website $website)
    {
        if (empty($website->url)) {
            return response()->json(['status' => 'Error', 'http_code' => null, 'message' => 'No URL']);
        }
        
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($website->url);
            
            if ($response->successful()) {
                return response()->json(['status' => 'Available', 'http_code' => $response->status()]);
            }
            
            return response()->json(['status' => 'Unavailable', 'http_code' => $response->status()]);
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['status' => 'Timeout', 'http_code' => null]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'http_code' => null]);
        }
    }
}
