import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dgt-system/app/Http/Controllers/WebsiteController.php'
with open(file_path, 'r') as f:
    content = f.read()

replacement = """
    public function addHistoryComment(Request $request, Website $website)
    {
        try {
            $validated = $request->validate([
                'note' => 'required|string|max:2000',
                'attachments' => 'nullable|array|max:8',
                'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            ]);

            $files = $request->hasFile('attachments') ? $request->file('attachments') : [];
            $attachments = collect($files)->filter()->map(fn ($file) => $this->storeHistoryAttachmentFile($file))->values()->all();
            $attachment = $attachments[0] ?? ['path' => null, 'name' => null];

            $isMaintenanceFlow = in_array($website->status, [
                Website::STATUS_MAINTENANCE,
                Website::STATUS_MAINTENANCE_PROGRESS,
                Website::STATUS_MAINTENANCE_QC_CHECKING,
                Website::STATUS_MAINTENANCE_SUPERVISOR_CHECKING,
                Website::STATUS_MAINTENANCE_QC_ERROR,
                Website::STATUS_MAINTENANCE_SUPERVISOR_ERROR,
            ]);

            $log = WebsiteProgressLog::create([
                'website_id' => $website->id,
                'type'       => $isMaintenanceFlow ? 'maintenance' : 'build',
                'user_id'    => auth()->id(),
                'percent'    => $isMaintenanceFlow ? $website->maintenance_percent : $website->progress_percent,
                'note'       => 'Comment: ' . $validated['note'],
                'attachment_path' => $attachment['path'],
                'attachment_name' => $attachment['name'],
                'created_at' => now(),
            ]);

            $this->logActivity('history_comment', "Comment added to history for \\"{$website->name}\\".");

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'log' => $log, 'message' => 'Comment added.']);
            }

            return redirect()->back()->with('success', 'Comment added.');
        } catch (\\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
"""

content = re.sub(
    r'public function addHistoryComment\(Request \$request, Website \$website\)\s*\{.*?(?=\s+public function updateHistoryLog)',
    lambda m: replacement.strip(),
    content,
    flags=re.DOTALL
)

with open(file_path, 'w') as f:
    f.write(content)
