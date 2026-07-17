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

        // Fetch all non-archived websites with relationships
        $allWebsites = Website::with([
            'handler', 'progressLogs', 'maintenanceLogs', 'qcChecks',
            'activityLogs', 'activityLogs.user',
        ])
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();


        // ── Build Website Tab ─────────────────────────────────────────────────
        $buildWebsites = $allWebsites->where('status', Website::STATUS_BUILD_WEBSITE)->values();

        // ── Build Progress Tab ────────────────────────────────────────────────
        $buildProgressWebsites = $allWebsites->whereIn('status', [
            Website::STATUS_BUILD_PROGRESS,
            Website::STATUS_QC_CHECKING,
            Website::STATUS_SUPERVISOR_CHECKING,
        ])->values();

        // ── QC Error Tab ──────────────────────────────────────────────────────────────
        $qcErrorWebsites = $allWebsites->whereIn('status', [
            Website::STATUS_QC_ERROR,
            Website::STATUS_MAINTENANCE_QC_ERROR,
        ])->values();

        // ── Supervisor Error Tab ────────────────────────────────────────────────────
        $supervisorErrorWebsites = $allWebsites->whereIn('status', [
            Website::STATUS_SUPERVISOR_ERROR,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
        ])->values();

        // ── Live Websites Tab ─────────────────────────────────────────────────
        $liveWebsites = $allWebsites->filter(fn($w) => $w->isLiveOrMaintenance())->values();

        // ── Maintenance Progress Tab ──────────────────────────────────────────
        $maintenanceWebsites = $allWebsites->filter(fn($w) => $w->isMaintenance())->values();

        // ── Follow Up Tab ─────────────────────────────────────────────────────
        $followUpFilter = $request->only(['fu_class', 'fu_website', 'fu_type', 'fu_qc', 'fu_member', 'fu_date']);
        
        $followUpsQuery = WebsiteFollowUp::with(['website', 'assignee', 'qcChecker', 'creator'])
            ->orderByDesc('created_at');
            
        // Apply fu_class filter
        if (!empty($followUpFilter['fu_class'])) {
            $filteredWebsiteIds = $allWebsites->where('category', $followUpFilter['fu_class'])->pluck('id')->toArray();
            if ($followUpFilter['fu_class'] === '__none__') {
                $filteredWebsiteIds = $allWebsites->whereNull('category')->pluck('id')->toArray();
            }
            $followUpsQuery->whereIn('website_id', $filteredWebsiteIds);
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
        if (!empty($followUpFilter['fu_date'])) {
            $followUpsQuery->whereDate('created_at', $followUpFilter['fu_date']);
        }
        
        $followUps = $followUpsQuery->get();

        // ── KPI Stats ─────────────────────────────────────────────────────────
        $stats = [
            'total'       => $allWebsites->count(),
            'building'    => $allWebsites->filter(fn($w) => $w->isBuilding())->count(),
            'live'        => $allWebsites->where('status', Website::STATUS_LIVE)->count(),
            'maintenance' => $allWebsites->filter(fn($w) => $w->isMaintenance())->count(),
            'qc_pending'  => $allWebsites->where('status', Website::STATUS_QC_CHECKING)->count(),
            'follow_ups'  => $followUps->count(),
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

        $existingCategories = $allWebsites->pluck('category')->filter()->unique()->values()->toArray();
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
        $uncategorized = $buildWebsites->where('category', null)->values();
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

        return view('websites.index', compact(
            'tab', 'stats', 'allWebsites', 'groupedWebsites', 'orderArray',
            'buildWebsites', 'buildProgressWebsites', 'liveWebsites',
            'maintenanceWebsites', 'followUps', 'followUpFilter', 'users',
            'allClasses', 'websiteMembers', 'memberRolesMap',
            'qcErrorWebsites', 'supervisorErrorWebsites', 'websiteTeamMembers'
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

        return redirect()->route('websites.index', ['tab' => 'build-progress'])
            ->with('success', "Build progress updated to {$newPercent}% for \"{$website->name}\".");
    }

    // ── APPROVE QC ────────────────────────────────────────────────────────────
    public function approveQc(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteQc(), 403);

        $validated = $request->validate([
            'qc_note' => 'nullable|string|max:2000',
        ]);

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
        ]);

        $this->logActivity('qc_approved', "QC approved for \"{$website->name}\". Pending Supervisor approval.");

        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'build-progress'])
            ->with('success', "\"{$website->name}\" QC Approved. Now pending Supervisor approval.");
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

        return redirect()->route('websites.index', ['tab' => 'qc-error'])
            ->with('success', "\"{$website->name}\" flagged as QC Error. Team must fix and complete before re-approval.");
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
            ? Website::STATUS_MAINTENANCE_QC_CHECKING
            : Website::STATUS_QC_CHECKING;

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
            'note'       => 'QC Error fix completed. Sent back to QC Checking.',
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'qc_error_completed',
            'note'         => 'QC Error fix completed. Sent back to QC Checking.',
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => 100,
            'new_progress' => 100,
        ]);

        $this->logActivity('qc_error_completed', "QC error fix completed for \"{$website->name}\". Sent back to QC Checking.");

        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'build-progress'])
            ->with('success', "\"{$website->name}\" error fix completed! QC must approve again.");
    }

    // ── COMPLETE SUPERVISOR ERROR (send back to Supervisor Checking) ──────────────
    public function completeSupervisorError(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteSupervisor(), 403);

        $this->deleteErrorAttachment($website);

        $oldStatus = $website->status;
        $isMaintenanceFlow = ($oldStatus === Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR);
        $newStatus = $isMaintenanceFlow
            ? Website::STATUS_MAINTENANCE_QC_CHECKING
            : Website::STATUS_QC_CHECKING;

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
            'note'       => 'Supervisor Error fix completed. Sent back to QC Checking for re-check.',
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'   => $website->id,
            'user_id'      => auth()->id(),
            'action'       => 'supervisor_error_completed',
            'note'         => 'Supervisor Error fix completed. Sent back to QC Checking for re-check.',
            'old_status'   => $oldStatus,
            'new_status'   => $newStatus,
            'old_progress' => 100,
            'new_progress' => 100,
        ]);

        $this->logActivity('supervisor_error_completed', "Supervisor error fix completed for \"{$website->name}\". Sent back to QC Checking.");

        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'build-progress'])
            ->with('success', "\"{$website->name}\" Supervisor error fix done! QC must approve first.");
    }

    // ── APPROVE SUPERVISOR ────────────────────────────────────────────────────
    public function approveSupervisor(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteSupervisor(), 403);

        $validated = $request->validate([
            'supervisor_note' => 'nullable|string|max:2000',
        ]);

        $oldStatus = $website->status;
        $isMaintenanceFlow = in_array($oldStatus, [
            Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR
        ]);

        $website->update([
            'status'         => Website::STATUS_LIVE,
            'live_at'        => $website->live_at ?? now(),
            'updated_by'     => auth()->id(),
            'maintenance_completed_at' => $isMaintenanceFlow ? now() : $website->maintenance_completed_at,
        ]);

        WebsiteProgressLog::create([
            'website_id' => $website->id,
            'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
            'user_id'    => auth()->id(),
            'percent'    => $isMaintenanceFlow ? $website->maintenance_percent : $website->progress_percent,
            'note'       => 'Supervisor Approved. Website is now LIVE.' . ($validated['supervisor_note'] ? " Note: {$validated['supervisor_note']}" : ''),
            'created_at' => now(),
        ]);

        WebsiteMaintenanceLog::create([
            'website_id'  => $website->id,
            'user_id'     => auth()->id(),
            'action'      => 'supervisor_approved',
            'note'        => 'Supervisor approved. Website is now LIVE.' . ($validated['supervisor_note'] ? " Note: {$validated['supervisor_note']}" : ''),
            'old_status'  => $oldStatus,
            'new_status'  => Website::STATUS_LIVE,
            'old_progress'=> $website->progress_percent,
            'new_progress'=> $website->progress_percent,
        ]);

        $this->logActivity('supervisor_approved', "Supervisor approved for \"{$website->name}\". Website is now LIVE.");

        return redirect()->route('websites.index', ['tab' => 'live'])
            ->with('success', "\"{$website->name}\" Supervisor approved and is now LIVE.");
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

        $msg = $newPercent === 100
            ? "\"{$website->name}\" maintenance completed and is pending QC check."
            : "Maintenance progress updated to {$newPercent}% for \"{$website->name}\".";

        return back()->with('success', $msg);
    }

    // ── EXPORT REPORT ─────────────────────────────────────────────────────────
    public function exportReport(Request $request)
    {
        abort_unless(auth()->user()?->canUpdateWebsiteProgress(), 403);

        $format = $request->get('format', 'csv');
        $tab = $request->get('tab', 'build');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $memberId = $request->get('member_id');

        $user = auth()->user();
        if (!$user?->hasAnyRole(['super-admin', 'admin-digital']) && !$user?->hasRole('boss')) {
            $memberId = $user->id;
        }

        if ($tab === 'follow-up') {
            $query = \App\Models\WebsiteFollowUp::with(['website', 'assignee', 'qcChecker'])
                ->whereHas('website', function ($q) {
                    $q->where('is_archived', false);
                });

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }
            if ($memberId) {
                $query->where('assigned_to', $memberId);
            }

            $followUps = $query->latest()->get();

            if ($format === 'csv') {
                return $this->exportFollowUpsCsv($followUps);
            }
            if ($format === 'pdf') {
                $this->logActivity('report_exported', 'Follow Ups report exported as PDF.');
                $memberName = $memberId ? (\App\Models\User::find($memberId)?->name) : null;
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('websites.pdf_followups', compact('followUps', 'startDate', 'endDate', 'memberName'))
                    ->setPaper('a4', 'landscape');
                return $pdf->download('follow-ups-report-' . now()->format('Y-m-d') . '.pdf');
            }
            return $this->exportFollowUpsCsv($followUps);
        }

        $query = Website::with([
            'handler', 'creator', 'qcApprover',
            'progressLogs.user', 'maintenanceLogs.user',
            'activityLogs.user', 'followUps.assignee', 'followUps.qcChecker',
        ])->where('is_archived', false)
          ->orderBy('name');

        if ($memberId) {
            $query->where('handler_id', $memberId);
        }
        
        // Removed the website created_at filter so that we can fetch all websites and filter their activity logs by the date range instead.

        $websites = $query->get();

        if ($format === 'csv') {
            return $this->exportCsv($websites, $startDate, $endDate);
        }

        if ($format === 'pdf') {
            $this->logActivity('report_exported', 'All Websites report exported as PDF.');
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('websites.pdf', compact('websites', 'startDate', 'endDate'))
                ->setPaper('a4', 'landscape');
            return $pdf->download('all-websites-report-' . now()->format('Y-m-d') . '.pdf');
        }

        // CSV fallback
        return $this->exportCsv($websites, $startDate, $endDate);
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

    private function exportCsv($websites, $startDate = null, $endDate = null)
    {
        $this->logActivity('report_exported', 'All Websites report exported as CSV.');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="all-websites-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($websites, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

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
                // ── Filter progress logs by date range ────────────────────────────
                $allProgressLogs = $ws->progressLogs->sortBy('created_at');
                $filteredProgressLogs = $allProgressLogs;
                if ($startDate || $endDate) {
                    $filteredProgressLogs = $allProgressLogs->filter(function($log) use ($startDate, $endDate) {
                        $logDate = $log->created_at->startOfDay();
                        if ($startDate && $logDate < \Carbon\Carbon::parse($startDate)->startOfDay()) return false;
                        if ($endDate && $logDate > \Carbon\Carbon::parse($endDate)->endOfDay()) return false;
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
                    $allProgressLogs->count(),
                    $filteredProgressLogs->count(),
                    strip_tags($ws->notes ?? ''),
                    $ws->created_at->format('d/m/Y H:i'),
                ]);

                // ── History Update Rows (per percentage step) ─────────────────────
                // Header for this website's history
                fputcsv($handle, [
                    '  [HISTORY]',
                    'Date & Time',
                    'Updated By',
                    'Type',
                    'Percentage',
                    'Update Reason / Note',
                    '', '', '', '', '', '', '', '', '', '', '', ''
                ]);

                foreach ($allProgressLogs as $log) {
                    $inRange = true;
                    if ($startDate || $endDate) {
                        $logDate = $log->created_at->startOfDay();
                        if ($startDate && $logDate < \Carbon\Carbon::parse($startDate)->startOfDay()) $inRange = false;
                        if ($endDate && $logDate > \Carbon\Carbon::parse($endDate)->endOfDay()) $inRange = false;
                    }

                    fputcsv($handle, [
                        $inRange ? '  → Update' : '  → Update (outside range)',
                        $log->created_at->format('d/m/Y H:i'),
                        $log->user?->name ?? 'System',
                        strtoupper($log->type ?? 'build'),
                        $log->percent . '%',
                        $log->note,
                        '', '', '', '', '', '', '', '', '', '', '', ''
                    ]);
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
        return [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'path' => $file->store('website-error-references', 'public'),
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
        $allowedAction = in_array($log->action, ['qc_error', 'supervisor_error'], true);

        abort_unless(
            $allowedAction && ($user?->canApproveWebsiteQc() || $user?->canApproveWebsiteSupervisor()),
            403,
            'Only QC, Supervisor, or super-admin can manage QC/Supervisor error history.'
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
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);
        
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
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

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
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

        $validated = $request->validate(['category' => 'required|string|max:255']);

        Website::where('category', $validated['category'])->update(['category' => null]);

        $setting = Setting::where('key', 'website_classes_order')->first();
        if ($setting) {
            $orderArray = json_decode($setting->value, true) ?? [];
            $orderArray = array_values(array_filter($orderArray, fn($c) => $c !== $validated['category']));
            $setting->update(['value' => json_encode($orderArray)]);
        }

        return redirect()->route('websites.index')
            ->with('success', "Group '{$validated['category']}' removed.");
    }

    public function reorderCategory(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

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

        return redirect()->back()->with('success', "Website member(s) added/updated successfully.");
    }

    // ── DESTROY WEBSITE MEMBER ────────────────────────────────────────────────
    public function destroyMember($id)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

        $member = WebsiteMember::findOrFail($id);
        $userName = $member->user?->name ?? 'Unknown';
        $member->delete();

        $this->logActivity('website_member_removed', "Removed user \"{$userName}\" from websites members.");

        return redirect()->back()->with('success', "Website member removed successfully.");
    }
}
