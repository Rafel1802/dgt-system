<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteFollowUp;
use App\Models\WebsiteMaintenanceLog;
use App\Models\ActivityLog;
use App\Models\User;
use App\Jobs\GoogleBlogsSyncJob;
use App\Services\GoogleBlogsSheetService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class WebsiteFollowUpController extends Controller
{
    const ALLOWED_ROLES = ['super-admin', 'admin-digital', 'digital-team', 'boss'];
    const ADMIN_ROLES   = ['super-admin', 'admin-digital'];

    private function canManageFollowUp($user): bool
    {
        return $user !== null;
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        abort_unless($this->canManageFollowUp(auth()->user()), 403);
        @set_time_limit(120);

        $validated = $request->validate([
            'type'               => 'required|string|max:100',
            'custom_type'        => 'nullable|string|max:100',
            'assigned_to'        => 'nullable|exists:users,id',
            'created_at'         => 'nullable|date',
            'force_overwrite'    => 'nullable',
            'skip_sheet_sync'    => 'nullable',
            'items'              => 'required|array|min:1',
            'items.*.website_id' => 'required|exists:websites,id',
            'items.*.url'        => 'nullable|url|max:1000',
            'items.*.blog_sheet_class' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== '' && !array_key_exists($value, GoogleBlogsSheetService::CLASS_BLOCKS)) {
                    $supported = implode(', ', array_keys(GoogleBlogsSheetService::CLASS_BLOCKS));
                    $fail("Invalid blog sheet class. Supported classes: {$supported}.");
                }
            }],
        ]);

        $finalType = ($validated['type'] === 'other' && !empty($validated['custom_type']))
                        ? $validated['custom_type']
                        : $validated['type'];

        $sheetEnabled   = !empty(config('services.google_blogs.apps_script_url'));
        $forceOverwrite = $request->boolean('force_overwrite');
        $skipSheetSync  = $request->boolean('skip_sheet_sync');

        $targetDate = null;
        if (!empty($validated['created_at'])) {
            $targetDate = Carbon::parse($validated['created_at'], config('app.timezone', 'Asia/Phnom_Penh'))->startOfDay();
        }

        $successCount = 0;
        $errors = [];
        $hasConflict = false;

        foreach ($validated['items'] as $index => $item) {
            $websiteId = $item['website_id'];
            $url = $item['url'] ?? null;
            $blogClass = $item['blog_sheet_class'] ?? null;

            $recentDuplicate = WebsiteFollowUp::where('website_id', $websiteId)
                ->where('created_by', auth()->id())
                ->where('type', $finalType)
                ->where('url', $url)
                ->where('updated_at', '>=', now()->subSeconds(10))
                ->first();

            if ($recentDuplicate) {
                $successCount++;
                continue;
            }

            // For Blog Posts, sync with Google Sheets unless user opted to skip
            if ($finalType === 'blog_post' && $sheetEnabled && !$skipSheetSync) {
                if (empty($blogClass) || empty($url) || empty($targetDate)) {
                    $errors[] = "Item #" . ($index + 1) . ": Class, URL, and Date are required for Blog Posts.";
                    continue;
                }

                $month = $targetDate->month;
                if ($month < 9 || $month > 12) {
                    $errors[] = "Item #" . ($index + 1) . ": Google Sheet synchronization is currently configured for September–December Blogs (found month {$month}).";
                    continue;
                }

                $website = Website::find($websiteId);
                $dateForSheet = $targetDate->format('m/d/Y');
                $sheetTab = match ($month) {
                    9  => 'Sep Blogs',
                    10 => 'Oct Blogs',
                    11 => 'Nov Blogs',
                    12 => 'Dec Blogs',
                    default => 'Blogs',
                };

                if ($index > 0) {
                    usleep(500000); // 0.5s pause to allow Google Apps Script lock to cleanly release
                }

                $googleService = new GoogleBlogsSheetService();
                $syncResult = $googleService->syncBlogFollowUp(
                    (string) $blogClass,
                    $url,
                    $dateForSheet,
                    $website?->name ?? 'Unknown',
                    $forceOverwrite,
                    $sheetTab
                );

                if (!$syncResult['success']) {
                    $rawMsg = $syncResult['error'] ?? $syncResult['message'] ?? 'Unknown error';

                    $isDocLinkError = str_contains(strtolower($rawMsg), 'doc link') || str_contains(strtolower($rawMsg), 'docs link');
                    $isConflict = (!$isDocLinkError) && (
                        !empty($syncResult['needs_confirmation'])
                        || str_contains($rawMsg, 'already has a Public Link')
                        || str_contains($rawMsg, 'replace it')
                    );

                    if ($isConflict) {
                        $hasConflict = true;
                        $existingLink = $syncResult['existing_link'] ?? null;
                        $linkNote = ($existingLink && filter_var($existingLink, FILTER_VALIDATE_URL)) ? " ({$existingLink})" : "";
                        $reason = "Row in Google Sheet ({$sheetTab} / Class {$blogClass}) for '{$website?->name}' already has a Public Link{$linkNote}. You can overwrite it by confirming replacement.";
                    } elseif ($isDocLinkError) {
                        $hasConflict = false;
                        $reason = $rawMsg;
                    } else {
                        $hasConflict = false;
                        $reason = "Google Sheet sync issue ({$sheetTab} / Class {$blogClass}): " . $rawMsg;
                    }

                    $errors[] = "Item #" . ($index + 1) . " (" . ($website?->name ?? 'Website') . "): " . $reason;
                    continue;
                }
            }

            $followUp = new WebsiteFollowUp([
                'website_id'          => $websiteId,
                'type'                => $finalType,
                'url'                 => $url,
                'google_indexed'      => 'pending',
                'assigned_to'         => $validated['assigned_to'] ?? null,
                'qc_status'           => 'pending',
                'created_by'          => auth()->id(),
                'blog_sheet_class'    => $blogClass,
                'google_sheet_status' => ($finalType === 'blog_post' && $sheetEnabled && !$skipSheetSync) ? 'synced' : 'skipped',
            ]);

            $followUp->save();

            if ($targetDate) {
                // Force update created_at after save so Laravel doesn't override it with now()
                $followUp->timestamps = false;
                $followUp->created_at = $targetDate;
                $followUp->save();
            }

            // Background image-fetch
            if (!empty($url)) {
                dispatch(function () use ($followUp, $url) {
                    try {
                        $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);
                        if ($response->successful()) {
                            $html = $response->body();
                            $fetchedImageUrl = null;
                            if (preg_match('/<meta[^>]*property=[\'"]og:image[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
                                $fetchedImageUrl = $matches[1];
                            } elseif (preg_match('/<meta[^>]*content=[\'"]([^\'"]+)[\'"][^>]*property=[\'"]og:image[\'"]/i', $html, $matches)) {
                                $fetchedImageUrl = $matches[1];
                            }
                            if ($fetchedImageUrl) {
                                $followUp->updateQuietly(['image_url' => $fetchedImageUrl]);
                            }
                        }
                    } catch (\Exception $e) {}
                })->afterResponse();
            }

            $website = Website::find($websiteId);
            $this->logActivity('followup_added', "Follow-up ({$followUp->getTypeLabel()}) added for \"{$website?->name}\".");
            
            $successCount++;
        }

        if (count($errors) > 0) {
            $errorMsg = implode("\n", $errors);
            if ($successCount === 0) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success'            => false,
                        'needs_confirmation' => $hasConflict,
                        'message'            => "Failed to add follow-ups:\n\n" . $errorMsg,
                        'confirm_message'    => "Google Sheet Conflict Detected:\n\n" . $errorMsg . "\n\nWould you like to overwrite/replace the existing Public Link in Google Sheets?",
                    ]);
                }
                return back()->with('error', "Failed to add follow-ups:\n\n" . $errorMsg);
            } else {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'warning' => "Successfully added {$successCount} follow-up(s), with some issues:\n\n" . $errorMsg,
                    ]);
                }
                return redirect()->route('websites.index', ['tab' => 'follow-up'])
                    ->with('warning', "Successfully added {$successCount} follow-ups, but with some issues:\n\n" . $errorMsg);
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => "Successfully added {$successCount} follow-ups."]);
        }
        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Successfully added {$successCount} follow-ups.");
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless($this->canManageFollowUp(auth()->user()), 403);

        $validated = $request->validate([
            'type'           => 'required|string|max:100',
            'custom_type'    => 'nullable|string|max:100',
            'title'          => 'nullable|string|max:255',
            'url'            => 'nullable|url|max:1000',
            'google_indexed' => 'nullable|in:yes,no,pending',
            'note'           => 'nullable|string|max:3000',
            'assigned_to'    => 'nullable|exists:users,id',
            'created_at'     => 'nullable|date',
        ]);

        $finalType = ($validated['type'] === 'other' && !empty($validated['custom_type'])) 
                        ? $validated['custom_type'] 
                        : $validated['type'];

        $imageUrl = $websiteFollowUp->image_url;

        $websiteFollowUp->update([
            'type'           => $finalType,
            'title'          => $validated['title'] ?? null,
            'url'            => $validated['url'] ?? null,
            'image_url'      => $imageUrl,
            'google_indexed' => $validated['google_indexed'] ?? 'pending',
            'note'           => $validated['note'] ?? null,
            'assigned_to'    => $validated['assigned_to'] ?? null,
        ]);

        if (!empty($validated['created_at'])) {
            $websiteFollowUp->created_at = \Carbon\Carbon::parse($validated['created_at'], config('app.timezone', 'Asia/Phnom_Penh'))->startOfDay();
        }
        $websiteFollowUp->save();

        if (!empty($validated['url']) && $validated['url'] !== $websiteFollowUp->getOriginal('url')) {
            $url = $validated['url'];
            dispatch(function () use ($websiteFollowUp, $url) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);
                    if ($response->successful()) {
                        $html = $response->body();
                        $fetchedImageUrl = null;
                        if (preg_match('/<meta[^>]*property=[\'"]og:image[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
                            $fetchedImageUrl = $matches[1];
                        } elseif (preg_match('/<meta[^>]*content=[\'"]([^\'"]+)[\'"][^>]*property=[\'"]og:image[\'"]/i', $html, $matches)) {
                            $fetchedImageUrl = $matches[1];
                        }
                        if ($fetchedImageUrl) {
                            $websiteFollowUp->updateQuietly(['image_url' => $fetchedImageUrl]);
                        }
                    }
                } catch (\Exception $e) {}
            })->afterResponse();
        }

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up updated.");
    }

    public function destroy(WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless($this->canManageFollowUp(auth()->user()), 403);

        $type = $websiteFollowUp->type;
        $blogClass = $websiteFollowUp->blog_sheet_class;
        $url = $websiteFollowUp->url;
        $sheetRow = $websiteFollowUp->google_sheet_row;

        $websiteFollowUp->delete();

        if ($type === 'blog_post' && !empty($blogClass) && (!empty($sheetRow) || !empty($url))) {
            dispatch(new \App\Jobs\GoogleBlogsDeleteJob($blogClass, $sheetRow ?? 0, $url))->afterResponse();
        }

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up deleted.");
    }

    // ── QC CHECK ──────────────────────────────────────────────────────────────
    public function qcCheck(Request $request, WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES) || auth()->user()?->isQcOrSupervisor() || str_contains(strtolower(auth()->user()->name), 'qc') || \App\Models\WebsiteMember::where('user_id', auth()->id())->whereIn('role', ['QC', 'Supervisor'])->exists(), 403);

        $validated = $request->validate([
            'qc_status' => 'required|in:checked,approved',
            'qc_note'   => 'nullable|string|max:1000',
        ]);

        $websiteFollowUp->update([
            'qc_status'     => $validated['qc_status'],
            'qc_checked_by' => auth()->id(),
            'qc_checked_at' => now(),
        ]);

        $this->logActivity('followup_qc_checked', "Follow-up QC marked as {$validated['qc_status']} for website \"{$websiteFollowUp->website?->name}\".");

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up QC status updated to " . ucfirst($validated['qc_status']) . ".");
    }

    // ── RETRY GOOGLE SHEET SYNC ───────────────────────────────────────────────
    public function retrySheetSync(Request $request, WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'admin-digital', 'boss']), 403);
        abort_unless(!empty(config('services.google_blogs.apps_script_url')), 422, 'Google Blogs Sheet is not configured.');

        if (empty($websiteFollowUp->blog_sheet_class) || empty($websiteFollowUp->url)) {
            return response()->json([
                'success' => false,
                'message' => 'This follow-up has no blog class or URL — cannot sync to Google Sheet.',
            ], 422);
        }

        $date = Carbon::parse($websiteFollowUp->created_at, config('app.timezone', 'Asia/Phnom_Penh'))
            ->format('d/m');

        $websiteFollowUp->updateQuietly(['google_sheet_status' => 'pending', 'google_sheet_error' => null]);

        GoogleBlogsSyncJob::dispatch(
            $websiteFollowUp->id,
            (string) $websiteFollowUp->blog_sheet_class,
            $websiteFollowUp->url,
            $date,
        )->onQueue('default');

        return response()->json([
            'success' => true,
            'message' => 'Sync job re-queued. Status will update shortly.',
        ]);
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

    public function exportPersonalReport(Request $request)
    {
        abort_unless(auth()->user()?->isQcOrSupervisor(), 403, 'Unauthorized access to personal reports.');

        $format = $request->get('format', 'pdf');
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

        $query = WebsiteFollowUp::with(['website', 'assignee', 'qcChecker'])
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($userId) {
            $query->where(function($q) use ($userId) {
                $q->where('assigned_to', $userId)
                  ->orWhere('qc_checked_by', $userId);
            });
        }

        $followUps = $query->orderBy('created_at')->get();

        // Show preview instead of immediate download
        if ($format !== 'csv' && !$request->boolean('download')) {
            $userModel = $userId ? \App\Models\User::find($userId) : null;
            return view('websites.reports.followup-preview', [
                'followUps' => $followUps,
                'dateFrom'  => $dateFrom,
                'dateTo'    => $dateTo,
                'format'    => $format,
                'user'      => $userModel,
            ]);
        }

        if ($format === 'pdf' || $request->boolean('download')) {
            $userModel = $userId ? \App\Models\User::find($userId) : null;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('websites.reports.followup-personal-pdf', [
                'followUps' => $followUps,
                'user'      => $userModel,
                'dateFrom'  => $dateFrom,
                'dateTo'    => $dateTo,
            ])->setPaper('a4', 'landscape');
            return $pdf->download('follow-up-personal-report-' . now()->format('Y-m-d') . '.pdf');
        }

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="follow-up-personal-report-' . now()->format('Y-m-d') . '.csv"',
            'Pragma'              => 'no-cache',
        ];

        $callback = function () use ($followUps) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date', 'Website', 'Class', 'Type', 'URL', 'Handle By (User)',
                'QC Status', 'QC Checker', 'QC Checked At', 'Note'
            ]);

            foreach ($followUps as $fu) {
                fputcsv($handle, [
                    $fu->created_at->format('Y-m-d H:i:s'),
                    $fu->website->name ?? 'Unknown',
                    $fu->website->category ?? 'Uncategorized',
                    $fu->getTypeLabel(),
                    $fu->url ?? '',
                    $fu->assignee?->name ?? 'Unassigned',
                    $fu->qc_status,
                    $fu->qcChecker?->name ?? '',
                    $fu->qc_checked_at?->format('Y-m-d H:i:s') ?? '',
                    strip_tags($fu->note ?? ''),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
