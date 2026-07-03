<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DeviceLog;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class AuthService
{
    /**
     * Attempt to log in a user with rate-limiting and security checks.
     *
     * Returns an array with 'success' bool and optional 'message' on failure.
     */
    public function attemptLogin(Request $request): array
    {
        $credentials = $request->only('email', 'password');
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $rateLimiterKey = 'login:' . $ip . '|' . $credentials['email'];
        $maxAttempts = (int) config('auth.login_max_attempts', 5);
        $decayMinutes = (int) config('auth.login_decay_minutes', 15);

        // ── Rate limit check ────────────────────────────────────────────────
        if (RateLimiter::tooManyAttempts($rateLimiterKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimiterKey);
            $this->recordAttempt($credentials['email'], $ip, $userAgent, false);

            return [
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                'throttled' => true,
            ];
        }

        // ── Find user and check if account is locked ─────────────────────
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLockedOut()) {
            $this->recordAttempt($credentials['email'], $ip, $userAgent, false);
            $minutes = now()->diffInMinutes($user->locked_until);

            return [
                'success' => false,
                'message' => "Account is locked. Try again in {$minutes} minutes.",
            ];
        }

        // ── Check if user is active ──────────────────────────────────────
        if ($user && ! $user->is_active) {
            return [
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact admin.',
            ];
        }

        // ── Attempt authentication ────────────────────────────────────────
        $authenticated = false;

        try {
            $authenticated = Auth::attempt(
                $credentials,
                (bool) config('auth.remember_users_by_default', true) || $request->boolean('remember')
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'This password does not use the Bcrypt algorithm.') {
                throw $exception;
            }

            Log::warning('Login blocked because stored password hash is invalid.', [
                'email' => $credentials['email'],
                'ip' => $ip,
            ]);
        }

        if ($authenticated) {
            $user = Auth::user();

            // Clear rate limiter on success
            RateLimiter::clear($rateLimiterKey);

            // Update login tracking fields
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $ip,
                'failed_login_count' => 0,
                'locked_until' => null,
            ]);

            // Record successful attempt
            $this->recordAttempt($credentials['email'], $ip, $userAgent, true);

            // Log device
            $this->logDevice($user, $ip, $userAgent);

            // Log activity
            $this->logActivity($user, 'user.login', 'auth', 'User logged in', $ip, $userAgent);

            $request->session()->regenerate();

            return ['success' => true, 'user' => $user];
        }

        // ── Failed login ──────────────────────────────────────────────────
        RateLimiter::hit($rateLimiterKey, $decayMinutes * 60);
        $this->recordAttempt($credentials['email'], $ip, $userAgent, false);

        // Auto block IP and Device if failed attempts reach the threshold
        $banThreshold = (int) \App\Models\Setting::get('security_max_login_attempts', 10);
        
        $failedIpCount = LoginAttempt::where('ip_address', $ip)
            ->where('was_successful', false)
            ->where('attempted_at', '>=', now()->subMinutes(15))
            ->count();

        if ($failedIpCount >= $banThreshold) {
            $banDurationMinutes = (int) \App\Models\Setting::get('security_ban_duration_minutes', 0); // 0 = permanent
            $deviceToken = \Illuminate\Support\Str::uuid()->toString();
            
            \App\Models\IpBan::updateOrCreate([
                'ip_address' => $ip,
            ], [
                'device_token' => $deviceToken,
                'reason' => "Auto-blocked after {$banThreshold} failed login attempts",
                'is_active' => true,
                'banned_at' => now(),
                'expires_at' => $banDurationMinutes > 0 ? now()->addMinutes($banDurationMinutes) : null,
            ]);

            // Queue the ban cookie to track the device
            $cookieDuration = $banDurationMinutes > 0 ? $banDurationMinutes : 60 * 24 * 365 * 10; // 10 years if permanent
            \Illuminate\Support\Facades\Cookie::queue('dgt_device_token', $deviceToken, $cookieDuration);
        }

        // Increment failed login counter on user record
        if ($user) {
            $user->increment('failed_login_count');

            // Lock account after max attempts
            if ($user->failed_login_count >= $maxAttempts) {
                $user->update(['locked_until' => now()->addMinutes($decayMinutes)]);
            }
        }

        $retriesLeft = RateLimiter::retriesLeft($rateLimiterKey, $maxAttempts);
        $ipRetriesLeft = max(0, $banThreshold - $failedIpCount);
        $attemptsRemaining = min($retriesLeft, $ipRetriesLeft);
        
        if ($user) {
            $userRetriesLeft = max(0, $maxAttempts - $user->failed_login_count);
            $attemptsRemaining = min($attemptsRemaining, $userRetriesLeft);
        }

        $message = 'These credentials do not match our records.';
        if ($attemptsRemaining > 0) {
            $message .= " You have {$attemptsRemaining} attempt" . ($attemptsRemaining === 1 ? '' : 's') . " remaining.";
        }

        return [
            'success' => false,
            'message' => $message,
        ];
    }

    /**
     * Log the user out and clean up the session.
     */
    public function logout(Request $request): void
    {
        $user = Auth::user();

        if ($user) {
            $this->logActivity(
                $user,
                'user.logout',
                'auth',
                'User logged out',
                $request->ip(),
                $request->userAgent()
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function recordAttempt(string $email, string $ip, ?string $userAgent, bool $success): void
    {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'was_successful' => $success,
            'attempted_at' => now(),
        ]);
    }

    private function logDevice(User $user, string $ip, ?string $userAgent): void
    {
        $fingerprint = hash('sha256', ($userAgent ?? '') . $ip);

        DeviceLog::updateOrCreate(
            ['user_id' => $user->id, 'device_fingerprint' => $fingerprint],
            [
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'last_seen_at' => now(),
            ]
        );
    }

    public function logActivity(
        User $user,
        string $action,
        string $module,
        string $description,
        ?string $ip = null,
        ?string $userAgent = null,
        array $properties = [],
        ?object $subject = null
    ): void {
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'properties' => $properties,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
