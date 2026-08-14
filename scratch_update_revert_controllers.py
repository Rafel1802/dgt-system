import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/app/Http/Controllers/WebsiteController.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Update revertQc
revert_qc_replacement = """    public function revertQc(Request $request, Website $website)
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

        $this->logActivity('qc_reverted', "QC approval reverted for \\"{$website->name}\\".");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'qc_reverted', "QC approval reverted for \\"{$website->name}\\".", $validated['revert_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\\"{$website->name}\\" QC Approval Reverted. Sent back to QC Checking."]);
        }

        return redirect()->back()
            ->with('success', "\\"{$website->name}\\" QC Approval Reverted. Sent back to QC Checking.");
    }"""

content = re.sub(
    r'    public function revertQc.*?->with\(\'success\',.*?;\n    \}',
    revert_qc_replacement,
    content,
    flags=re.DOTALL
)

# 2. Update revertSupervisor
revert_supervisor_replacement = """    public function revertSupervisor(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->canApproveWebsiteSupervisor(), 403);

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
            Website::STATUS_MAINTENANCE,
            Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR
        ]);
        $newStatus = $isMaintenanceFlow 
            ? Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING 
            : Website::STATUS_SUPERVISOR_CHECKING;

        $website->update([
            'status'                 => $newStatus,
            'supervisor_approved_by' => null,
            'supervisor_approved_at' => null,
            'updated_by'             => auth()->id(),
        ]);

        $note = 'Supervisor Approval Reverted. Sent back to Supervisor Checking.' . (isset($validated['revert_note']) && $validated['revert_note'] ? " Note: {$validated['revert_note']}" : '');

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
            'action'      => 'supervisor_reverted',
            'note'        => $note,
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'old_progress'=> $website->progress_percent,
            'new_progress'=> $website->progress_percent,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachments' => $attachments,
        ]);

        $this->logActivity('supervisor_reverted', "Supervisor approval reverted for \\"{$website->name}\\".");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'supervisor_reverted', "Supervisor approval reverted for \\"{$website->name}\\".", $validated['revert_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\\"{$website->name}\\" Supervisor Approval Reverted. Sent back to Supervisor Checking."]);
        }

        return redirect()->back()
            ->with('success', "\\"{$website->name}\\" Supervisor Approval Reverted. Sent back to Supervisor Checking.");
    }"""

content = re.sub(
    r'    public function revertSupervisor.*?->with\(\'success\',.*?;\n    \}',
    revert_supervisor_replacement,
    content,
    flags=re.DOTALL
)

with open(file_path, 'w') as f:
    f.write(content)

print("Updated revertQc and revertSupervisor methods in WebsiteController to support JSON and file uploads.")
