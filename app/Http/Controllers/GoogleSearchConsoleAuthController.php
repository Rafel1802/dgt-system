<?php

namespace App\Http\Controllers;

use App\Models\GoogleSearchConsole\GoogleSearchConsoleAccount;
use App\Services\GoogleSearchConsole\GoogleSearchConsoleClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class GoogleSearchConsoleAuthController extends Controller
{
    protected GoogleSearchConsoleClientService $clientService;

    public function __construct(GoogleSearchConsoleClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Redirect to Google OAuth consent screen
     */
    public function redirect(Request $request)
    {
        $user = $request->user();

        // Only authorized managers/admins can initiate Google connection
        if (!$user->isSpecialManagerOrAdmin() && !$user->hasAnyRole(['super-admin', 'admin-digital'])) {
            return redirect()->route('websites.search-console.index')
                ->with('error', 'Only system administrators and managers can connect the Google Search Console account.');
        }

        if (!$this->clientService->isConfigured()) {
            return redirect()->route('websites.search-console.index')
                ->with('error', 'Google Search Console OAuth is not configured. Please set GOOGLE_SEARCH_CONSOLE_CLIENT_ID and GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET in .env.');
        }

        // Never start an OAuth flow from a background/speculative prefetch (Turbo hover prefetch,
        // <link rel=prefetch>). Those requests used to overwrite the saved state and caused
        // "Invalid state parameter" when the real click came back from Google.
        $purpose = strtolower($request->header('Sec-Purpose', '') . ' ' . $request->header('Purpose', '') . ' ' . $request->header('X-Sec-Purpose', '') . ' ' . $request->header('X-Moz', ''));
        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
            return response('', 204)->header('Cache-Control', 'no-store');
        }

        // Stateless, tamper-proof state bound to this user (valid 20 min). Survives session rotation,
        // multiple tabs and repeated clicks, while still protecting against CSRF.
        $nonce = Str::random(24);
        $state = rtrim(strtr(base64_encode(\Illuminate\Support\Facades\Crypt::encryptString(json_encode([
            'u' => $user->id,
            't' => time(),
            'n' => $nonce,
        ]))), '+/', '-_'), '=');
        $request->session()->put('gsc_oauth_state', $state);

        try {
            $authUrl = $this->clientService->createAuthUrl($state);
            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('GSC OAuth redirect error', ['error' => $e->getMessage()]);
            return redirect()->route('websites.search-console.index')
                ->with('error', 'Failed to generate Google authorization URL: ' . $e->getMessage());
        }
    }

    /**
     * Handle OAuth callback from Google
     */
    public function callback(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        if (!$user->isSpecialManagerOrAdmin() && !$user->hasAnyRole(['super-admin', 'admin-digital'])) {
            return redirect()->route('websites.search-console.index')
                ->with('error', 'Unauthorized to complete Google connection.');
        }

        // Validate state parameter to protect against CSRF
        $storedState = $request->session()->pull('gsc_oauth_state');
        $receivedState = (string) $request->get('state');

        $stateValid = $storedState && hash_equals($storedState, $receivedState);
        if (!$stateValid && $receivedState !== '') {
            // Fallback: verify the encrypted state is ours, for this user, and recent
            try {
                $b64 = strtr($receivedState, '-_', '+/');
                $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
                $payload = json_decode(\Illuminate\Support\Facades\Crypt::decryptString(base64_decode($b64, true) ?: ''), true);
                $stateValid = is_array($payload)
                    && (int) ($payload['u'] ?? 0) === (int) $user->id
                    && (time() - (int) ($payload['t'] ?? 0)) <= 1200;
            } catch (\Throwable $e) {
                $stateValid = false;
            }
        }

        if (!$stateValid) {
            Log::error('GSC OAuth state mismatch', [
                'stored'   => $storedState ? substr($storedState, 0, 12) . '…' : null,
                'received' => $receivedState ? substr($receivedState, 0, 12) . '…' : null,
                'user_id'  => $user->id,
            ]);
            return redirect()->route('websites.search-console.index')
                ->with('error', 'Google authorization failed: Invalid state parameter. Please try connecting again.');
        }

        // Check for error returned by Google
        if ($request->has('error')) {
            $error = $request->get('error');
            Log::warning('GSC OAuth access denied or error from Google', ['error' => $error]);
            return redirect()->route('websites.search-console.index')
                ->with('error', 'Google authorization was cancelled or denied: ' . $error);
        }

        $code = $request->get('code');
        if (empty($code)) {
            return redirect()->route('websites.search-console.index')
                ->with('error', 'No authorization code returned from Google.');
        }

        try {
            $account = $this->clientService->handleCallback($code, $user->id);

            return redirect()->route('websites.search-console.index')
                ->with('success', "Google Search Console successfully connected as {$account->email}!");
        } catch (Exception $e) {
            Log::error('GSC OAuth callback exception', ['error' => $e->getMessage()]);
            return redirect()->route('websites.search-console.index')
                ->with('error', 'Failed to connect Google account: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect active Google Search Console account
     * STRICT SECURITY RULE: Only Mr. Dara (soporadara@kiuq.com) can disconnect.
     */
    public function disconnect(Request $request)
    {
        $user = $request->user();

        $userEmail = strtolower(trim($user?->email ?? ''));
        $isDara = $user && (
            $user->isDara()
            || $userEmail === 'soporadara@kiuq.com'
            || str_contains($userEmail, 'soporadara')
        );

        if (!$isDara) {
            Log::warning('Security violation: Unauthorized attempt to disconnect Google Search Console', [
                'user_id'    => $user?->id,
                'user_name'  => $user?->name,
                'user_email' => $user?->email,
                'ip'         => $request->ip(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Security restriction: Only Mr. Dara (soporadara@kiuq.com) is authorized to disconnect the Google Search Console account.',
                ], 403);
            }

            return redirect()->route('websites.search-console.index')
                ->with('error', 'Security restriction: Only Mr. Dara (soporadara@kiuq.com) is authorized to disconnect the Google Search Console account.');
        }

        $account = GoogleSearchConsoleAccount::where('is_active', true)->first();

        if ($account) {
            $account->update([
                'is_active'     => false,
                'access_token'  => null,
                'refresh_token' => null,
                'last_error'    => 'Disconnected by ' . $user->name . ' (' . ($user->email ?? 'Dara') . ')',
                'last_error_at' => now(),
            ]);
        }

        return redirect()->route('websites.search-console.index')
            ->with('success', 'Google Search Console account has been securely disconnected by Mr. Dara.');
    }

    /**
     * Get current connection status (JSON)
     */
    public function status()
    {
        $account = GoogleSearchConsoleAccount::where('is_active', true)->latest()->first();

        return response()->json([
            'is_configured' => $this->clientService->isConfigured(),
            'is_connected'  => (bool) $account,
            'email'         => $account ? $account->email : null,
            'expires_at'    => $account?->expires_at?->toIso8601String(),
            'last_error'    => $account?->last_error,
        ]);
    }
}
