import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/app/Http/Controllers/WebsiteController.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Update approveQc
approve_qc_replacement = """    public function approveQc(Request $request, Website $website)
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

        $this->logActivity('qc_approved', "QC approved for \\"{$website->name}\\". Pending Supervisor approval.");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'qc_approved', "QC approved for \\"{$website->name}\\". Pending Supervisor approval.", $validated['qc_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\\"{$website->name}\\" QC Approved. Now pending Supervisor approval."]);
        }

        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'build-progress'])
            ->with('success', "\\"{$website->name}\\" QC Approved. Now pending Supervisor approval.");
    }"""

content = re.sub(
    r'    public function approveQc.*?->with\(\'success\',.*?;\n    \}',
    approve_qc_replacement,
    content,
    flags=re.DOTALL
)

# 2. Update approveSupervisor
approve_supervisor_replacement = """    public function approveSupervisor(Request $request, Website $website)
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

        $this->logActivity('supervisor_approved', "Supervisor approved \\"{$website->name}\\". Website is Live.");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'supervisor_approved', "Supervisor approved \\"{$website->name}\\". Website is Live.", $validated['supervisor_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\\"{$website->name}\\" Supervisor Approved. Website is now Live!"]);
        }

        return redirect()->route('websites.index', ['tab' => $isMaintenanceFlow ? 'maintenance' : 'live'])
            ->with('success', "\\"{$website->name}\\" Supervisor Approved. Website is now Live!");
    }"""

content = re.sub(
    r'    public function approveSupervisor.*?->with\(\'success\',.*?;\n    \}',
    approve_supervisor_replacement,
    content,
    flags=re.DOTALL
)

with open(file_path, 'w') as f:
    f.write(content)

print("Updated WebsiteController for file uploads.")
