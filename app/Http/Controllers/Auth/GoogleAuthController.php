<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Google_Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Handle incoming Google ID Token or Access Token credential.
     */
    public function handleCredential(Request $request): JsonResponse|RedirectResponse
    {
        $credential = $request->input('credential');
        $accessToken = $request->input('access_token');

        if (empty($credential) && empty($accessToken)) {
            return $this->errorResponse($request, 'No Google credential or access token received.', 400);
        }

        $clientId = config('services.google_oauth.client_id');
        $googleId = '';
        $email = '';
        $name = '';
        $avatar = '';
        $hostedDomain = '';

        if (!empty($credential)) {
            $client = new Google_Client(['client_id' => $clientId]);
            try {
                $payload = $client->verifyIdToken($credential);
            } catch (\Throwable $e) {
                return $this->errorResponse($request, 'Google token verification failed: ' . $e->getMessage(), 401);
            }

            if (! $payload) {
                return $this->errorResponse($request, 'Invalid or expired Google credential.', 401);
            }

            $googleId = (string) ($payload['sub'] ?? '');
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $name = (string) ($payload['name'] ?? ($payload['given_name'] ?? explode('@', $email)[0]));
            $avatar = (string) ($payload['picture'] ?? '');
            $hostedDomain = strtolower((string) ($payload['hd'] ?? ''));
        } elseif (!empty($accessToken)) {
            try {
                $userinfoRes = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withToken($accessToken)
                    ->get('https://www.googleapis.com/oauth2/v3/userinfo');

                if (! $userinfoRes->successful()) {
                    return $this->errorResponse($request, 'Failed to fetch Google profile with access token.', 401);
                }

                $userinfo = $userinfoRes->json();
                $googleId = (string) ($userinfo['sub'] ?? '');
                $email = strtolower(trim((string) ($userinfo['email'] ?? '')));
                $name = (string) ($userinfo['name'] ?? ($userinfo['given_name'] ?? explode('@', $email)[0]));
                $avatar = (string) ($userinfo['picture'] ?? '');
                $hostedDomain = strtolower((string) ($userinfo['hd'] ?? ''));
            } catch (\Throwable $e) {
                return $this->errorResponse($request, 'Google authentication error: ' . $e->getMessage(), 500);
            }
        }

        if (empty($email)) {
            return $this->errorResponse($request, 'Google profile email could not be retrieved.', 422);
        }

        // 1. Find by google_id
        $user = User::where('google_id', $googleId)->first();

        // 2. If not found by google_id, match by email
        if (! $user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                // Link this existing staff account to their Google identity
                $user->google_id = $googleId;
                $user->google_email = $email;
                if (empty($user->avatar) && !empty($avatar)) {
                    $user->avatar = $avatar;
                }
                $user->save();
            }
        }

        // 3. If still no user exists, check if domain is authorized (@kiuq.com / @kiuq)
        if (! $user) {
            $domain = substr(strrchr($email, "@"), 1);
            $isKiuqDomain = str_contains($domain, 'kiuq') || $hostedDomain === 'kiuq.com';

            if (! $isKiuqDomain) {
                return $this->errorResponse(
                    $request,
                    "Access restricted: The Google account ({$email}) is not authorized. Please use an official @kiuq.com email address.",
                    403
                );
            }

            // Provision a new staff user
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'username' => explode('@', $email)[0],
                'password' => Hash::make(Str::random(32)),
                'avatar' => $avatar ?: null,
                'google_id' => $googleId,
                'google_email' => $email,
                'is_active' => true,
            ]);
        }

        // Check active status
        if (! $user->is_active) {
            return $this->errorResponse($request, 'Your user account has been deactivated. Please contact an administrator.', 403);
        }

        // Log the user in
        Auth::login($user, true);
        $request->session()->regenerate();

        try {
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'failed_login_count' => 0,
                'locked_until' => null,
            ]);
        } catch (\Throwable $e) {}

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'redirect' => route('dashboard'),
                'message' => 'Signed in successfully with Google.',
            ]);
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome, ' . $user->name . '! Signed in via Google.');
    }

    /**
     * Link Google Account to the authenticated user from their Profile.
     */
    public function link(Request $request): JsonResponse|RedirectResponse
    {
        $credential = $request->input('credential');
        $accessToken = $request->input('access_token');

        if (empty($credential) && empty($accessToken)) {
            return response()->json(['success' => false, 'message' => 'No Google credential or access token provided.'], 400);
        }

        $clientId = config('services.google_oauth.client_id');
        $googleId = '';
        $email = '';
        $avatar = '';
        $hostedDomain = '';

        if (!empty($credential)) {
            $client = new Google_Client(['client_id' => $clientId]);
            try {
                $payload = $client->verifyIdToken($credential);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Google verification failed: ' . $e->getMessage()], 401);
            }

            if (! $payload) {
                return response()->json(['success' => false, 'message' => 'Invalid Google credential.'], 401);
            }

            $googleId = (string) ($payload['sub'] ?? '');
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $avatar = (string) ($payload['picture'] ?? '');
            $hostedDomain = strtolower((string) ($payload['hd'] ?? ''));
        } elseif (!empty($accessToken)) {
            try {
                $userinfoRes = \Illuminate\Support\Facades\Http::timeout(10)
                    ->withToken($accessToken)
                    ->get('https://www.googleapis.com/oauth2/v3/userinfo');

                if (! $userinfoRes->successful()) {
                    return response()->json(['success' => false, 'message' => 'Failed to verify Google access token.'], 401);
                }

                $userinfo = $userinfoRes->json();
                $googleId = (string) ($userinfo['sub'] ?? '');
                $email = strtolower(trim((string) ($userinfo['email'] ?? '')));
                $avatar = (string) ($userinfo['picture'] ?? '');
                $hostedDomain = strtolower((string) ($userinfo['hd'] ?? ''));
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Google verification error: ' . $e->getMessage()], 500);
            }
        }

        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'Google profile email could not be retrieved.'], 422);
        }

        // Domain verification: check if domain is authorized (@kiuq.com / @kiuq)
        $domain = substr(strrchr($email, "@"), 1);
        $isKiuqDomain = str_contains($domain, 'kiuq') || $hostedDomain === 'kiuq.com';

        if (! $isKiuqDomain) {
            return response()->json([
                'success' => false,
                'message' => "Access restricted: The Google account ({$email}) is not authorized. Please use an official @kiuq.com email address.",
            ], 403);
        }

        // Check if already linked to another account
        $existing = User::where('google_id', $googleId)->where('id', '!=', auth()->id())->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "This Google account ({$email}) is already linked to user '{$existing->name}'.",
            ], 422);
        }

        $user = auth()->user();
        $user->google_id = $googleId;
        $user->google_email = $email;
        if (empty($user->avatar) && !empty($avatar)) {
            $user->avatar = $avatar;
        }
        $user->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Google account ({$email}) linked successfully!",
                'google_email' => $email,
            ]);
        }

        return back()->with('success', "Google account ({$email}) linked successfully!");
    }

    /**
     * Unlink Google Account from the authenticated user.
     */
    public function unlink(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();

        $user->google_id = null;
        $user->google_email = null;
        $user->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Google account unlinked successfully.',
            ]);
        }

        return back()->with('success', 'Google account unlinked successfully.');
    }

    /**
     * Helper to return standard error format for JSON or standard redirect.
     */
    private function errorResponse(Request $request, string $message, int $status = 400): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return back()->withInput()->withErrors(['email' => $message]);
    }
}
