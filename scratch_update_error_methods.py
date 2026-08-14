import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/app/Http/Controllers/WebsiteController.php'
with open(file_path, 'r') as f:
    content = f.read()

# 1. Update qcError
qc_error_replacement = """    public function qcError(Request $request, Website $website)
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

        $this->logActivity('qc_error', "QC flagged error for \\"{$website->name}\\".");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'qc_error', "QC Error flagged for \\"{$website->name}\\".", $validated['error_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\\"{$website->name}\\" flagged as QC Error. Team must fix before re-approval."]);
        }

        return redirect()->route('websites.index', ['tab' => 'qc-error'])
            ->with('success', "\\"{$website->name}\\" flagged as QC Error. Team must fix before re-approval.");
    }"""

content = re.sub(
    r'    public function qcError.*?->with\(\'success\',.*?;\n    \}',
    qc_error_replacement,
    content,
    flags=re.DOTALL
)

# 2. Update supervisorError
supervisor_error_replacement = """    public function supervisorError(Request $request, Website $website)
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

        $this->logActivity('supervisor_error', "Supervisor flagged error for \\"{$website->name}\\".");

        $attachmentUrl = $attachment['path'] ? asset('storage/' . $attachment['path']) : null;
        WebsiteActivityNotification::send($website, 'supervisor_error', "Supervisor Error flagged for \\"{$website->name}\\".", $validated['error_note'] ?? null, $attachmentUrl);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "\\"{$website->name}\\" flagged as Supervisor Error. Team must fix before re-approval."]);
        }

        return redirect()->route('websites.index', ['tab' => 'supervisor-error'])
            ->with('success', "\\"{$website->name}\\" flagged as Supervisor Error. Team must fix before re-approval.");
    }"""

content = re.sub(
    r'    public function supervisorError.*?->with\(\'success\',.*?;\n    \}',
    supervisor_error_replacement,
    content,
    flags=re.DOTALL
)

with open(file_path, 'w') as f:
    f.write(content)

print("Updated qcError and supervisorError methods in WebsiteController to return JSON.")
