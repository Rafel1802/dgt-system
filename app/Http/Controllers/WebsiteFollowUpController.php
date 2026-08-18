<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteFollowUp;
use App\Models\WebsiteMaintenanceLog;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

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

        $validated = $request->validate([
            'website_id'     => 'required|exists:websites,id',
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

        $imageUrl = null;
        if (!empty($validated['url'])) {
            try {
                $html = @file_get_contents($validated['url'], false, stream_context_create(['http' => ['timeout' => 2]]));
                if ($html) {
                    if (preg_match('/<meta[^>]*property=[\'"]og:image[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
                        $imageUrl = $matches[1];
                    } elseif (preg_match('/<meta[^>]*content=[\'"]([^\'"]+)[\'"][^>]*property=[\'"]og:image[\'"]/i', $html, $matches)) {
                        $imageUrl = $matches[1];
                    }
                }
            } catch (\Exception $e) {}
        }

        $recentDuplicate = WebsiteFollowUp::where('website_id', $validated['website_id'])
            ->where('created_by', auth()->id())
            ->where('type', $finalType)
            ->where('url', $validated['url'] ?? null)
            ->where('updated_at', '>=', now()->subSeconds(10))
            ->first();

        if ($recentDuplicate) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => "Follow-up added successfully."]);
            }
            return redirect()->route('websites.index', ['tab' => 'follow-up'])
                ->with('success', "Follow-up added successfully.");
        }

        $followUp = new WebsiteFollowUp([
            'website_id'     => $validated['website_id'],
            'type'           => $finalType,
            'title'          => $validated['title'] ?? null,
            'url'            => $validated['url'] ?? null,
            'image_url'      => $imageUrl,
            'google_indexed' => $validated['google_indexed'] ?? 'pending',
            'note'           => $validated['note'] ?? null,
            'assigned_to'    => $validated['assigned_to'] ?? null,
            'qc_status'      => 'pending',
            'created_by'     => auth()->id(),
        ]);

        $followUp->save();

        if (!empty($validated['created_at'])) {
            // Parse with app timezone so the stored timestamp aligns with the date filter
            $newDate = \Carbon\Carbon::parse($validated['created_at'], config('app.timezone', 'Asia/Phnom_Penh'))->startOfDay();
            // Force update created_at after save so Laravel doesn't override it with now()
            $followUp->timestamps = false;
            $followUp->created_at = $newDate;
            $followUp->save();
        }

        $website = Website::find($validated['website_id']);
        $this->logActivity('followup_added', "Follow-up ({$followUp->getTypeLabel()}) added for \"{$website?->name}\".");

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => "Follow-up added successfully."]);
        }

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up added successfully.");
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
        if (!empty($validated['url']) && $validated['url'] !== $websiteFollowUp->url) {
            try {
                $html = @file_get_contents($validated['url'], false, stream_context_create(['http' => ['timeout' => 2]]));
                if ($html) {
                    if (preg_match('/<meta[^>]*property=[\'"]og:image[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
                        $imageUrl = $matches[1];
                    } elseif (preg_match('/<meta[^>]*content=[\'"]([^\'"]+)[\'"][^>]*property=[\'"]og:image[\'"]/i', $html, $matches)) {
                        $imageUrl = $matches[1];
                    }
                }
            } catch (\Exception $e) {}
        }

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

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up updated.");
    }

    public function destroy(WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless($this->canManageFollowUp(auth()->user()), 403);

        $websiteFollowUp->delete();

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

        $query = WebsiteFollowUp::with(['website', 'assignee', 'qcChecker'])
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($userId) {
            // Include if they were assigned to it, OR if they QC checked it (assuming QC might pull their own report)
            $query->where(function($q) use ($userId) {
                $q->where('assigned_to', $userId)
                  ->orWhere('qc_checked_by', $userId);
            });
        }

        $followUps = $query->orderBy('created_at')->get();

        if ($format === 'pdf') {
            $userModel = $userId ? \App\Models\User::find($userId) : null;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('websites.reports.followup-personal-pdf', [
                'followUps' => $followUps,
                'user' => $userModel,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
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
