<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpBan;
use App\Models\User;
use App\Models\LoginAttempt;
use App\Models\ActivityLog;
use App\Models\DeviceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    /**
     * Show the Security Dashboard.
     */
    public function index(Request $request): View
    {
        $failedAttempts = LoginAttempt::where('was_successful', false)
            ->orderByDesc('attempted_at')
            ->paginate(10, ['*'], 'attempt_page')
            ->withQueryString();

        $blockedUsers = User::where(function ($query) {
                $query->whereNotNull('locked_until')
                      ->where('locked_until', '>', now());
            })
            ->orWhere('failed_login_count', '>=', 10)
            ->orderByDesc('locked_until')
            ->paginate(10, ['*'], 'user_page')
            ->withQueryString();

        $bannedIps = IpBan::with('bannedBy')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'ip_page')
            ->withQueryString();

        $activityLogs = ActivityLog::where('module', 'auth')
            ->orWhere('action', 'like', 'user.%')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'activity_page')
            ->withQueryString();

        // Statistics
        $stats = [
            'total_attempts_24h' => LoginAttempt::where('attempted_at', '>=', now()->subDay())->count(),
            'failed_attempts_24h' => LoginAttempt::where('was_successful', false)
                ->where('attempted_at', '>=', now()->subDay())->count(),
            'blocked_users_count' => User::where(function ($query) {
                    $query->whereNotNull('locked_until')
                          ->where('locked_until', '>', now());
                })->orWhere('failed_login_count', '>=', 10)->count(),
            'banned_ips_count' => IpBan::active()->count(),
        ];

        $settings = [
            'max_attempts' => \App\Models\Setting::get('security_max_login_attempts', 10),
            'ban_duration' => \App\Models\Setting::get('security_ban_duration_minutes', 0),
        ];

        return view('admin.security.index', compact(
            'failedAttempts',
            'blockedUsers',
            'bannedIps',
            'activityLogs',
            'stats',
            'settings'
        ));
    }

    /**
     * Manually ban an IP Address.
     */
    public function banIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip', 'unique:ip_bans,ip_address'],
            'reason'     => ['nullable', 'string', 'max:255'],
            'duration'   => ['required', 'in:permanent,1h,24h,7d'],
        ]);

        $expiresAt = null;
        if ($validated['duration'] === '1h') {
            $expiresAt = now()->addHour();
        } elseif ($validated['duration'] === '24h') {
            $expiresAt = now()->addDay();
        } elseif ($validated['duration'] === '7d') {
            $expiresAt = now()->addWeek();
        }

        IpBan::create([
            'ip_address' => $validated['ip_address'],
            'reason'     => $validated['reason'] ?? 'Manually banned by Admin',
            'banned_by'  => auth()->id(),
            'is_active'  => true,
            'banned_at'  => now(),
            'expires_at' => $expiresAt,
        ]);

        return back()->with('success', "IP Address {$validated['ip_address']} has been banned successfully.");
    }

    /**
     * Unban a banned IP Address.
     */
    public function unbanIp(IpBan $ipBan): RedirectResponse
    {
        $ip = $ipBan->ip_address;
        $ipBan->delete();

        return back()->with('success', "IP Address {$ip} has been unbanned.");
    }

    /**
     * Unblock a locked out user account.
     */
    public function unblockUser(User $user): RedirectResponse
    {
        $user->update([
            'failed_login_count' => 0,
            'locked_until'       => null,
        ]);

        return back()->with('success', "User account for {$user->email} has been unblocked successfully.");
    }

    /**
     * Store security configuration settings.
     */
    public function storeSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'security_max_login_attempts' => 'required|integer|min:1',
            'security_ban_duration_minutes' => 'required|integer|min:0',
        ]);

        foreach ($validated as $key => $value) {
            \App\Models\Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Security settings have been updated.');
    }

    /**
     * Clear all security activity logs (Super Admin only).
     */
    public function clearActivity(): RedirectResponse
    {
        if (!auth()->user()->hasAnyRole(['super-admin', 'admin-digital', 'admin-crm'])) {
            abort(403, 'Unauthorized action.');
        }

        ActivityLog::where('module', 'auth')
            ->orWhere('action', 'like', 'user.%')
            ->delete();

        // Optional: log that the super admin cleared the logs
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'security.logs_cleared',
            'description' => 'Cleared all security activity logs.',
            'module' => 'auth',
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Security activity logs have been cleared.');
    }
}
