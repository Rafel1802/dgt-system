<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    use FormatsApiResponses;

    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $email = strtolower($credentials['email']);
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $rateLimiterKey = 'api-login:' . $ip . '|' . $email;
        $maxAttempts = (int) config('auth.login_max_attempts', 5);
        $decayMinutes = (int) config('auth.login_decay_minutes', 15);

        if (RateLimiter::tooManyAttempts($rateLimiterKey, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many login attempts. Please try again later.',
                'retry_after' => RateLimiter::availableIn($rateLimiterKey),
            ], 429);
        }

        $user = User::with('roles')->where('email', $email)->first();

        if ($user && $user->isLockedOut()) {
            $this->recordAttempt($email, $ip, $userAgent, false);
            return response()->json(['message' => 'Account is locked. Please try again later.'], 423);
        }

        if ($user && ! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated. Please contact admin.'], 403);
        }

        $validPassword = false;
        if ($user) {
            try {
                $validPassword = Hash::check($credentials['password'], $user->password);
            } catch (RuntimeException) {
                $validPassword = false;
            }
        }

        if (! $user || ! $validPassword) {
            RateLimiter::hit($rateLimiterKey, $decayMinutes * 60);
            $this->recordAttempt($email, $ip, $userAgent, false);
            $this->registerFailedUserAttempt($user, $ip);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($rateLimiterKey);
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $this->recordAttempt($email, $ip, $userAgent, true);
        $this->authService->logActivity($user, 'user.api_login', 'auth', 'User logged in from macOS app', $ip, $userAgent);

        $tokenName = $credentials['device_name'] ?? 'DGT System macOS';
        $token = $user->createToken($tokenName, ['macos-app'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userSummary($user->fresh('roles')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->currentAccessToken()?->delete();

        if ($user) {
            $this->authService->logActivity($user, 'user.api_logout', 'auth', 'User logged out from macOS app', $request->ip(), $request->userAgent());
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userSummary($request->user()->loadMissing('roles')),
            'permissions' => $request->user()->getAllPermissions()->pluck('name')->values(),
            'unread_notifications' => $request->user()->unreadNotifications()->count(),
        ]);
    }

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

    private function registerFailedUserAttempt(?User $user, string $ip): void
    {
        $banThreshold = (int) Setting::get('security_max_login_attempts', 10);
        $failedIpCount = LoginAttempt::where('ip_address', $ip)
            ->where('was_successful', false)
            ->where('attempted_at', '>=', now()->subMinutes(15))
            ->count();

        if ($failedIpCount >= $banThreshold) {
            $banDurationMinutes = (int) Setting::get('security_ban_duration_minutes', 0);
            $deviceToken = Str::uuid()->toString();

            \App\Models\IpBan::updateOrCreate([
                'ip_address' => $ip,
            ], [
                'device_token' => $deviceToken,
                'reason' => "Auto-blocked after {$banThreshold} failed login attempts",
                'is_active' => true,
                'banned_at' => now(),
                'expires_at' => $banDurationMinutes > 0 ? now()->addMinutes($banDurationMinutes) : null,
            ]);

            $cookieDuration = $banDurationMinutes > 0 ? $banDurationMinutes : 60 * 24 * 365 * 10;
            Cookie::queue('dgt_device_token', $deviceToken, $cookieDuration);
        }

        if ($user) {
            $user->increment('failed_login_count');
            if ($user->failed_login_count >= (int) config('auth.login_max_attempts', 5)) {
                $user->forceFill(['locked_until' => now()->addMinutes((int) config('auth.login_decay_minutes', 15))])->save();
            }
        }
    }
}
