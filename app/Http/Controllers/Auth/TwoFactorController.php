<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DeviceLog;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Generate a new 2FA secret and QR code for the authenticated user to set up in their authenticator app.
     * Hardened: Stores pending secret in session so an active 2FA is NEVER destroyed before confirmation.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Rate limit setup calls: 5 attempts per minute
        $limiterKey = '2fa_setup:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return response()->json([
                'success' => false,
                'message' => "Too many setup requests. Please wait {$seconds} seconds.",
            ], 429);
        }
        RateLimiter::hit($limiterKey, 60);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);

        // Store pending secret safely in session — active DB credentials remain untouched until confirm()
        $request->session()->put('two_factor.pending_secret', $secret);

        $company = config('app.name', 'DGT System');
        $otpUrl = $google2fa->getQRCodeUrl($company, $user->email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($otpUrl);
        $qrCodeSvg = trim(preg_replace('/<\?xml[^>]*\?>/', '', $qrCodeSvg));

        return response()->json([
            'success'     => true,
            'secret'      => $secret,
            'qr_code_svg' => $qrCodeSvg,
            'otp_url'     => $otpUrl,
        ]);
    }

    /**
     * Confirm and activate 2FA after the user enters the verification code from their app.
     * Hardened: Strictly rate-limited and validates against pending secret before persisting.
     */
    public function confirm(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Rate limit confirmation attempts: 5 per minute to prevent brute force
        $limiterKey = '2fa_confirm:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return response()->json([
                'success' => false,
                'message' => "Too many confirmation attempts. Please wait {$seconds} seconds.",
            ], 429);
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $pendingSecret = $request->session()->get('two_factor.pending_secret') ?: $user->two_factor_secret;

        if (empty($pendingSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Please generate a 2FA secret key first.',
            ], 422);
        }

        $cleanCode = preg_replace('/[^0-9]/', '', (string) $request->input('code'));

        $google2fa = new Google2FA();
        $valid = strlen($cleanCode) === 6 && $google2fa->verifyKey($pendingSecret, $cleanCode, 1);

        if (! $valid) {
            RateLimiter::hit($limiterKey, 60);
            return response()->json([
                'success' => false,
                'message' => 'The 6-digit confirmation code is invalid. Please check your authenticator app and try again.',
            ], 422);
        }

        RateLimiter::clear($limiterKey);

        // Commit verified secret to user record (stored encrypted at rest)
        $user->two_factor_secret = $pendingSecret;
        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $recoveryCodes = $user->generateTwoFactorRecoveryCodes();
        $user->save();

        $request->session()->forget('two_factor.pending_secret');

        ActivityLog::record(
            $user,
            'user.2fa_enabled',
            'Enabled Two-Factor Authentication (2FA)',
            $user
        );

        return response()->json([
            'success'        => true,
            'message'        => 'Two-Factor Authentication is now enabled for your account!',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA for the authenticated user.
     * Hardened: Requires password or valid TOTP code + rate limiting.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = auth()->user();

        $limiterKey = '2fa_disable:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Please wait {$seconds} seconds.",
            ], 429);
        }

        $google2fa = new Google2FA();
        $passwordValid = !empty($request->input('password')) && Hash::check($request->input('password'), $user->password);
        $cleanCode = preg_replace('/[^0-9]/', '', (string) $request->input('code'));
        $codeValid = strlen($cleanCode) === 6 && !empty($user->two_factor_secret) && $google2fa->verifyKey($user->two_factor_secret, $cleanCode, 1);

        if (! $passwordValid && ! $codeValid) {
            RateLimiter::hit($limiterKey, 60);
            return response()->json([
                'success' => false,
                'message' => 'Please enter your current password or 6-digit authenticator code to confirm.',
            ], 422);
        }

        RateLimiter::clear($limiterKey);

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        $request->session()->forget('two_factor.pending_secret');

        ActivityLog::record(
            $user,
            'user.2fa_disabled',
            'Disabled Two-Factor Authentication (2FA)',
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Two-Factor Authentication has been disabled successfully.',
        ]);
    }

    /**
     * Retrieve the user's recovery codes (requires current password or TOTP code).
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = auth()->user();

        $limiterKey = '2fa_recovery:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return response()->json([
                'success' => false,
                'message' => "Too many requests. Please wait {$seconds} seconds.",
            ], 429);
        }

        $google2fa = new Google2FA();
        $passwordValid = !empty($request->input('password')) && Hash::check($request->input('password'), $user->password);
        $cleanCode = preg_replace('/[^0-9]/', '', (string) $request->input('code'));
        $codeValid = strlen($cleanCode) === 6 && !empty($user->two_factor_secret) && $google2fa->verifyKey($user->two_factor_secret, $cleanCode, 1);

        if (! $passwordValid && ! $codeValid) {
            RateLimiter::hit($limiterKey, 60);
            return response()->json([
                'success' => false,
                'message' => 'Please enter your current password or 6-digit authenticator code to view recovery codes.',
            ], 422);
        }

        RateLimiter::clear($limiterKey);

        if (! $user->hasEnabledTwoFactor()) {
            return response()->json([
                'success' => false,
                'message' => 'Two-Factor Authentication is not enabled.',
            ], 400);
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        if (empty($codes)) {
            $codes = $user->generateTwoFactorRecoveryCodes();
        }

        return response()->json([
            'success'        => true,
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Regenerate fresh recovery codes (requires current password or TOTP code).
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = auth()->user();

        $limiterKey = '2fa_recovery:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $seconds = RateLimiter::availableIn($limiterKey);
            return response()->json([
                'success' => false,
                'message' => "Too many requests. Please wait {$seconds} seconds.",
            ], 429);
        }

        $google2fa = new Google2FA();
        $passwordValid = !empty($request->input('password')) && Hash::check($request->input('password'), $user->password);
        $cleanCode = preg_replace('/[^0-9]/', '', (string) $request->input('code'));
        $codeValid = strlen($cleanCode) === 6 && !empty($user->two_factor_secret) && $google2fa->verifyKey($user->two_factor_secret, $cleanCode, 1);

        if (! $passwordValid && ! $codeValid) {
            RateLimiter::hit($limiterKey, 60);
            return response()->json([
                'success' => false,
                'message' => 'Please enter your current password or 6-digit authenticator code to regenerate recovery codes.',
            ], 422);
        }

        RateLimiter::clear($limiterKey);

        if (! $user->hasEnabledTwoFactor()) {
            return response()->json([
                'success' => false,
                'message' => 'Two-Factor Authentication is not enabled.',
            ], 400);
        }

        $codes = $user->generateTwoFactorRecoveryCodes();

        ActivityLog::record(
            $user,
            'user.2fa_recovery_regenerated',
            'Regenerated 2FA recovery codes',
            $user
        );

        return response()->json([
            'success'        => true,
            'message'        => 'New recovery codes generated successfully.',
            'recovery_codes' => $codes,
        ]);
    }

    // ── Login 2FA Challenge ───────────────────────────────────────────────────

    /**
     * Show the 2FA challenge screen during sign-in.
     * Hardened: Enforces 10-minute session expiration.
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $userId = $request->session()->get('login.2fa.user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        // Enforce 10-minute pending 2FA login session expiration
        $expiresAt = $request->session()->get('login.2fa.expires_at');
        if ($expiresAt && now()->timestamp > $expiresAt) {
            $request->session()->forget(['login.2fa.user_id', 'login.2fa.remember', 'login.2fa.expires_at']);
            return redirect()->route('login')->withErrors(['email' => 'Your 2FA session expired. Please sign in again.']);
        }

        $user = User::find($userId);
        if (! $user || ! $user->is_active || ! $user->hasEnabledTwoFactor()) {
            $request->session()->forget(['login.2fa.user_id', 'login.2fa.remember', 'login.2fa.expires_at']);
            return redirect()->route('login');
        }

        // Mask email for display (e.g. j***@kiuq.com)
        $parts = explode('@', $user->email);
        $namePart = $parts[0] ?? '';
        $domainPart = $parts[1] ?? '';
        $maskedEmail = (strlen($namePart) > 2 ? substr($namePart, 0, 2) . str_repeat('*', max(1, strlen($namePart) - 2)) : $namePart . '*') . '@' . $domainPart;

        return view('auth.two-factor-challenge', [
            'user'        => $user,
            'maskedEmail' => $maskedEmail,
        ]);
    }

    /**
     * Verify the 2FA challenge code and complete login.
     * Hardened:
     * - Replay attack prevention (OTP blacklisted after first use)
     * - Dual-tier rate limiting (by User ID and Client IP)
     * - Atomic race-condition safe consumption of recovery codes
     * - Session ID and CSRF regeneration on successful login
     */
    public function verifyChallenge(Request $request): RedirectResponse|JsonResponse
    {
        $userId = $request->session()->get('login.2fa.user_id');
        if (! $userId) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Your session expired. Please sign in again.'], 401);
            }
            return redirect()->route('login')->withErrors(['email' => 'Your login session expired. Please try again.']);
        }

        // Check 10-minute expiration
        $expiresAt = $request->session()->get('login.2fa.expires_at');
        if ($expiresAt && now()->timestamp > $expiresAt) {
            $request->session()->forget(['login.2fa.user_id', 'login.2fa.remember', 'login.2fa.expires_at']);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Your 2FA session expired. Please sign in again.'], 401);
            }
            return redirect()->route('login')->withErrors(['email' => 'Your 2FA session expired. Please sign in again.']);
        }

        $user = User::find($userId);
        if (! $user || ! $user->is_active || ! $user->hasEnabledTwoFactor()) {
            $request->session()->forget(['login.2fa.user_id', 'login.2fa.remember', 'login.2fa.expires_at']);
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Account inactive or invalid.'], 403);
            }
            return redirect()->route('login')->withErrors(['email' => 'User account is not accessible.']);
        }

        // Dual-tier rate limiting: 5 attempts per user, 10 attempts per client IP
        $ip = $request->ip();
        $userLimiterKey = '2fa_verify:user:' . $user->id;
        $ipLimiterKey   = '2fa_verify:ip:' . $ip;

        if (RateLimiter::tooManyAttempts($userLimiterKey, 5) || RateLimiter::tooManyAttempts($ipLimiterKey, 10)) {
            $seconds = max(RateLimiter::availableIn($userLimiterKey), RateLimiter::availableIn($ipLimiterKey));
            $msg = "Too many failed 2FA attempts. Please try again in {$seconds} seconds.";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 429);
            }
            return back()->withErrors(['code' => $msg]);
        }

        $success = false;
        $isRecovery = $request->filled('recovery_code');

        if ($isRecovery) {
            $recoveryCode = trim((string) $request->input('recovery_code'));
            // Atomic consumption under lock to prevent race conditions
            $success = DB::transaction(function () use ($user, $recoveryCode) {
                $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                return $lockedUser ? $lockedUser->consumeRecoveryCode($recoveryCode) : false;
            });
        } else {
            $code = preg_replace('/[^0-9]/', '', (string) $request->input('code'));

            // Replay attack prevention: verify this code was not already used in the current time window
            $replayKey = '2fa_used_otp:' . $user->id . ':' . $code;
            if (Cache::has($replayKey)) {
                $errorMsg = 'This code was already used. Please wait for the authenticator code to refresh.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $errorMsg], 422);
                }
                return back()->withErrors(['code' => $errorMsg]);
            }

            if (strlen($code) === 6) {
                $google2fa = new Google2FA();
                $success = !empty($user->two_factor_secret) && $google2fa->verifyKey($user->two_factor_secret, $code, 1);
                if ($success) {
                    // Blacklist this OTP for 90 seconds to prevent replay attacks
                    Cache::put($replayKey, true, 90);
                }
            }
        }

        if (! $success) {
            RateLimiter::hit($userLimiterKey, 60);
            RateLimiter::hit($ipLimiterKey, 60);

            // Log suspicious repeated failures
            if (RateLimiter::attempts($userLimiterKey) >= 4) {
                ActivityLog::record(
                    $user,
                    'security.2fa_failed_repeatedly',
                    "Multiple failed 2FA verification attempts from IP: {$ip}",
                    $user
                );
            }

            $errorMsg = $isRecovery
                ? 'The provided recovery code is invalid or has already been used.'
                : 'The 6-digit authentication code is invalid. Please check your app.';

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMsg], 422);
            }
            return back()->withErrors([
                $isRecovery ? 'recovery_code' : 'code' => $errorMsg,
            ])->withInput();
        }

        // Clear rate limiters on successful login
        RateLimiter::clear($userLimiterKey);
        RateLimiter::clear($ipLimiterKey);

        // Authentication Success: login, clear 2FA session, regenerate session ID & CSRF
        $remember = (bool) $request->session()->get('login.2fa.remember', false);
        Auth::login($user, $remember);

        $request->session()->forget(['login.2fa.user_id', 'login.2fa.remember', 'login.2fa.expires_at']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        try {
            $user->update([
                'last_login_at'      => now(),
                'last_login_ip'      => $ip,
                'failed_login_count' => 0,
                'locked_until'       => null,
            ]);

            DeviceLog::record($user, $ip, $request->userAgent());
            ActivityLog::record(
                $user,
                'user.login_2fa',
                $isRecovery ? 'Signed in using 2FA recovery code' : 'Signed in using 2FA authenticator PIN',
                $user
            );
        } catch (\Throwable $e) {}

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('dashboard'),
                'message'  => 'Authentication successful.',
            ]);
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }
}
