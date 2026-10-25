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

        $state = Str::random(40);
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
        $receivedState = $request->get('state');

        if (!$storedState || !hash_equals($storedState, (string) $receivedState)) {
            Log::warning('GSC OAuth state mismatch', [
                'stored'   => $storedState,
                'received' => $receivedState,
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
