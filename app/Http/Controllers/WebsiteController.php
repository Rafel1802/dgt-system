<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\WebsiteMaintenanceLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebsiteController extends Controller
{
    // ── Allowed roles ─────────────────────────────────────────────────────────
    const ALLOWED_ROLES = ['super-admin', 'admin-digital', 'digital-team', 'boss'];

    // ── INDEX (3 tabs) ────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

        $tab = $request->get('tab', 'built');

        // Fetch all non-archived websites with handler
        $allWebsites = Website::with('handler')
            ->where('is_archived', false)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // ── KPI Stats ─────────────────────────────────────────────────────────
        $stats = [
            'total'       => $allWebsites->count(),
            'in_progress' => $allWebsites->whereIn('status', [Website::STATUS_IN_PROGRESS, Website::STATUS_QC_REVIEW])->count(),
            'qc_review'   => $allWebsites->where('status', Website::STATUS_QC_REVIEW)->count(),
            'live'        => $allWebsites->where('status', Website::STATUS_LIVE)->count(),
            'needs_fix'   => $allWebsites->whereIn('status', [Website::STATUS_NEEDS_UPDATE, Website::STATUS_ERROR])->count(),
        ];

        // ── Category ordering (preserved from original) ───────────────────────
        $setting    = Setting::where('key', 'website_classes_order')->first();
        $orderArray = $setting ? json_decode($setting->value, true) : [];

        $existingCategories = $allWebsites->pluck('category')->filter()->unique()->values()->toArray();
        $newCategories      = array_diff($existingCategories, $orderArray);
        if (! empty($newCategories)) {
            $orderArray = array_merge($orderArray, $newCategories);
            Setting::updateOrCreate(['key' => 'website_classes_order'], ['value' => json_encode($orderArray)]);
        }

        // ── Built Websites — grouped by category ──────────────────────────────
        $groupedWebsites = collect();
        foreach ($orderArray as $categoryName) {
            $groupedWebsites->put($categoryName, $allWebsites->where('category', $categoryName)->values());
        }
        $uncategorized = $allWebsites->where('category', null)->values();
        if ($uncategorized->count() > 0) {
            $groupedWebsites->put('Uncategorized', $uncategorized);
        }

        // ── Website Progress tab — in-progress websites ───────────────────────
        $progressWebsites = $allWebsites->filter(fn($w) => $w->isInProgress())
            ->sortByDesc('deadline')
            ->values();

        // ── Live Websites tab ─────────────────────────────────────────────────
        $liveWebsites = $allWebsites->where('status', Website::STATUS_LIVE)
            ->sortByDesc('live_at')
            ->values();

        // ── Users for handler dropdown ────────────────────────────────────────
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('websites.index', compact(
            'tab', 'stats', 'allWebsites', 'groupedWebsites', 'orderArray',
            'progressWebsites', 'liveWebsites', 'users'
        ));
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'url'             => 'required|url|max:255',
            'category'        => 'nullable|string|max:255',
            'logo'            => 'nullable|image|max:2048',
            'logo_url'        => 'nullable|url|max:1000',
            'handled_by'      => 'nullable|exists:users,id',
            'start_date'      => 'nullable|date',
            'deadline'        => 'nullable|date|after_or_equal:start_date',
            'status'          => 'nullable|string|in:' . implode(',', Website::STATUSES),
            'progress_percent'=> 'nullable|integer|in:0,10,25,50,75,100',
            'notes'           => 'nullable|string|max:5000',
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
            'status'           => $validated['status'] ?? Website::STATUS_DRAFT,
            'progress_percent' => $validated['progress_percent'] ?? 0,
            'notes'            => $validated['notes'] ?? null,
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
        ]);

        // Log creation
        WebsiteMaintenanceLog::create([
            'website_id'  => $website->id,
            'user_id'     => auth()->id(),
            'action'      => 'created',
            'note'        => 'Website created.',
            'new_status'  => $website->status,
            'new_progress'=> $website->progress_percent,
        ]);

        return redirect()->route('websites.index', ['tab' => 'built'])
            ->with('success', "Website \"{$website->name}\" created successfully.");
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, Website $website)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'url'             => 'required|url|max:255',
            'category'        => 'nullable|string|max:255',
            'logo'            => 'nullable|image|max:2048',
            'logo_url'        => 'nullable|url|max:1000',
            'handled_by'      => 'nullable|exists:users,id',
            'start_date'      => 'nullable|date',
            'deadline'        => 'nullable|date',
            'status'          => 'nullable|string|in:' . implode(',', Website::STATUSES),
            'progress_percent'=> 'nullable|integer|in:0,10,25,50,75,100',
            'notes'           => 'nullable|string|max:5000',
        ]);

        $oldStatus   = $website->status;
        $oldProgress = $website->progress_percent;

        $logoPath = $this->resolveLogoPath($request, $website->logo_path);

        $newStatus   = $validated['status'] ?? $website->status;
        $newProgress = $validated['progress_percent'] ?? $website->progress_percent;

        $website->update([
            'name'             => $validated['name'],
            'url'              => $validated['url'],
            'category'         => $validated['category'] ?? null,
            'logo_path'        => $logoPath,
            'handled_by'       => $validated['handled_by'] ?? null,
            'start_date'       => $validated['start_date'] ?? null,
            'deadline'         => $validated['deadline'] ?? null,
            'status'           => $newStatus,
            'progress_percent' => $newProgress,
            'notes'            => $validated['notes'] ?? null,
            'updated_by'       => auth()->id(),
            'live_at'          => $newStatus === Website::STATUS_LIVE && ! $website->live_at ? now() : $website->live_at,
            'completed_at'     => $newProgress == 100 && ! $website->completed_at ? now() : $website->completed_at,
        ]);

        // Log if status or progress changed
        if ($oldStatus !== $newStatus || $oldProgress !== $newProgress) {
            WebsiteMaintenanceLog::create([
                'website_id'   => $website->id,
                'user_id'      => auth()->id(),
                'action'       => 'updated',
                'note'         => 'Website details updated.',
                'old_status'   => $oldStatus,
                'new_status'   => $newStatus,
                'old_progress' => $oldProgress,
                'new_progress' => $newProgress,
            ]);
        }

        return redirect()->route('websites.index', ['tab' => 'built'])
            ->with('success', "Website \"{$website->name}\" updated successfully.");
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────
    public function destroy(Website $website)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

        if ($website->logo_path && ! str_starts_with($website->logo_path, 'http')
            && Storage::disk('public')->exists($website->logo_path)) {
            Storage::disk('public')->delete($website->logo_path);
        }

        $website->delete();

        return redirect()->route('websites.index')
            ->with('success', 'Website removed successfully.');
    }

    // ── CATEGORY ACTIONS (preserved from original) ────────────────────────────

    public function renameCategory(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

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

        return redirect()->route('websites.index')
            ->with('success', "Group '{$validated['old_category']}' renamed to '{$validated['new_category']}' successfully.");
    }

    public function storeCategory(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

        $validated = $request->validate(['name' => 'required|string|max:255']);

        $setting    = Setting::firstOrCreate(['key' => 'website_classes_order'], ['value' => '[]']);
        $orderArray = json_decode($setting->value, true) ?? [];

        if (! in_array($validated['name'], $orderArray)) {
            $orderArray[] = $validated['name'];
            $setting->update(['value' => json_encode($orderArray)]);
        }

        return redirect()->route('websites.index')
            ->with('success', "Group '{$validated['name']}' created successfully.");
    }

    public function destroyCategory(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

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
        abort_unless(auth()->user()?->hasAnyRole(self::ALLOWED_ROLES), 403);

        $validated = $request->validate([
            'category'  => 'required|string|max:255',
            'direction' => 'required|in:up,down',
        ]);

        $setting = Setting::where('key', 'website_classes_order')->first();
        if ($setting) {
            $orderArray = json_decode($setting->value, true) ?? [];
            $index      = array_search($validated['category'], $orderArray);
            if ($index !== false) {
                if ($validated['direction'] === 'up' && $index > 0) {
                    [$orderArray[$index - 1], $orderArray[$index]] = [$orderArray[$index], $orderArray[$index - 1]];
                } elseif ($validated['direction'] === 'down' && $index < count($orderArray) - 1) {
                    [$orderArray[$index + 1], $orderArray[$index]] = [$orderArray[$index], $orderArray[$index + 1]];
                }
                $setting->update(['value' => json_encode($orderArray)]);
            }
        }

        return redirect()->route('websites.index')->with('success', 'Group reordered.');
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────

    private function resolveLogoPath(Request $request, ?string $existing): ?string
    {
        if ($request->hasFile('logo')) {
            // Delete old local file
            if ($existing && ! str_starts_with($existing, 'http') && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }
            return $request->file('logo')->store('websites', 'public');
        }

        if ($request->filled('logo_url')) {
            if ($existing && ! str_starts_with($existing, 'http') && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }
            return $request->input('logo_url');
        }

        return $existing;
    }
}
