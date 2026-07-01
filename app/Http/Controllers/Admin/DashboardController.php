<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the main admin dashboard.
     * Returns aggregated stats for the logged-in user's role level.
     */
    public function index(): View
    {
        $user = auth()->user();

        // Stats - will expand in later phases with real data
        $stats = [
            'total_users' => User::active()->count(),
            'online_users' => User::where('last_login_at', '>=', now()->subMinutes(30))->count(),
            'recent_activities' => ActivityLog::with('user')
                ->where('module', 'board')
                ->latest('created_at')
                ->limit(50)
                ->get(),
        ];

        $appearance = $this->dashboardAppearance($user);
        $externalTools = Setting::externalTools();
        $canManageExternalTools = $user->hasAnyRole(['super-admin', 'admin-digital']);

        return view('dashboard.index', compact('user', 'stats', 'appearance', 'externalTools', 'canManageExternalTools'));
    }

    /** Save per-user dashboard and cover background preferences. */
    public function updateAppearance(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->input('appearance_action') === 'reset') {
            $this->deleteStoredAppearanceImage(data_get($user->dashboard_appearance, 'background_value'));
            $this->deleteStoredAppearanceImage(data_get($user->dashboard_appearance, 'cover_value'));
            $user->forceFill(['dashboard_appearance' => null])->save();

            return back()->with('success', 'Dashboard appearance reset.');
        }

        $validated = $request->validate([
            'background_type' => ['required', 'in:color,gradient,image'],
            'background_value' => ['nullable', 'string', 'max:2048'],
            'background_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
            'cover_type' => ['required', 'in:color,gradient,image'],
            'cover_value' => ['nullable', 'string', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        ]);

        $current = $this->dashboardAppearance($user);

        $appearance = [
            'background_type' => $validated['background_type'],
            'background_value' => $validated['background_value'] ?: $current['background_value'],
            'cover_type' => $validated['cover_type'],
            'cover_value' => $validated['cover_value'] ?: $current['cover_value'],
        ];

        if ($request->hasFile('background_image')) {
            $this->deleteStoredAppearanceImage($current['background_value']);
            $appearance['background_type'] = 'image';
            $appearance['background_value'] = Storage::url(
                $request->file('background_image')->store("dashboard-backgrounds/{$user->id}", 'public')
            );
        }

        if ($request->hasFile('cover_image')) {
            $this->deleteStoredAppearanceImage($current['cover_value']);
            $appearance['cover_type'] = 'image';
            $appearance['cover_value'] = Storage::url(
                $request->file('cover_image')->store("dashboard-covers/{$user->id}", 'public')
            );
        }

        foreach (['background', 'cover'] as $target) {
            $type = $appearance["{$target}_type"];
            $value = trim((string) $appearance["{$target}_value"]);

            if ($type === 'color' && ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
                return back()->withErrors(["{$target}_value" => 'Choose a valid hex color.'])->withInput();
            }

            if ($type === 'gradient' && ! Str::startsWith($value, ['linear-gradient(', 'radial-gradient('])) {
                return back()->withErrors(["{$target}_value" => 'Choose a valid CSS gradient.'])->withInput();
            }

            if ($type === 'image' && ! $this->isAllowedAppearanceImageValue($value)) {
                return back()->withErrors(["{$target}_value" => 'Enter a valid image URL or upload an image.'])->withInput();
            }
        }

        $user->forceFill(['dashboard_appearance' => $appearance])->save();

        return back()->with('success', 'Dashboard appearance saved.');
    }

    private function dashboardAppearance(User $user): array
    {
        $appearance = is_array($user->dashboard_appearance) ? $user->dashboard_appearance : [];

        return [
            'background_type' => $appearance['background_type'] ?? 'gradient',
            'background_value' => $appearance['background_value'] ?? 'linear-gradient(180deg,#f8fafc,#eef2f7)',
            'cover_type' => $appearance['cover_type'] ?? 'gradient',
            'cover_value' => $appearance['cover_value'] ?? 'linear-gradient(135deg,#2F68ED 0%,#2457cf 46%,#173a92 100%)',
        ];
    }

    private function isAllowedAppearanceImageValue(string $value): bool
    {
        $value = trim($value);

        return (bool) filter_var($value, FILTER_VALIDATE_URL)
            || Str::startsWith($value, ['/storage/', 'storage/']);
    }

    private function deleteStoredAppearanceImage(?string $value): void
    {
        if (! $value) {
            return;
        }

        $normalized = ltrim(parse_url($value, PHP_URL_PATH) ?: $value, '/');

        if (! Str::startsWith($normalized, ['storage/dashboard-backgrounds/', 'storage/dashboard-covers/'])) {
            return;
        }

        Storage::disk('public')->delete(Str::after($normalized, 'storage/'));
    }
}
