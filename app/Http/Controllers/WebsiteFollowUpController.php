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

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

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

        $followUp = new WebsiteFollowUp([
            'website_id'     => $validated['website_id'],
            'type'           => $finalType,
            'title'          => $validated['title'] ?? null,
            'url'            => $validated['url'] ?? null,
            'google_indexed' => $validated['google_indexed'] ?? 'pending',
            'note'           => $validated['note'] ?? null,
            'assigned_to'    => $validated['assigned_to'] ?? null,
            'qc_status'      => 'pending',
            'created_by'     => auth()->id(),
        ]);

        if (!empty($validated['created_at'])) {
            $followUp->created_at = \Carbon\Carbon::parse($validated['created_at']);
        }
        $followUp->save();

        $website = Website::find($validated['website_id']);
        $this->logActivity('followup_added', "Follow-up ({$followUp->getTypeLabel()}) added for \"{$website?->name}\".");

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up added successfully.");
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

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

        $websiteFollowUp->fill([
            'type'           => $finalType,
            'title'          => $validated['title'] ?? null,
            'url'            => $validated['url'] ?? null,
            'google_indexed' => $validated['google_indexed'] ?? 'pending',
            'note'           => $validated['note'] ?? null,
            'assigned_to'    => $validated['assigned_to'] ?? null,
        ]);

        if (!empty($validated['created_at'])) {
            $websiteFollowUp->created_at = \Carbon\Carbon::parse($validated['created_at']);
        }
        $websiteFollowUp->save();

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up updated.");
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────
    public function destroy(WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

        $websiteFollowUp->delete();

        return redirect()->route('websites.index', ['tab' => 'follow-up'])
            ->with('success', "Follow-up deleted.");
    }

    // ── QC CHECK ──────────────────────────────────────────────────────────────
    public function qcCheck(Request $request, WebsiteFollowUp $websiteFollowUp)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ADMIN_ROLES), 403);

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
}
