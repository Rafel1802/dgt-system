<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Show the login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle an authentication request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $result = $this->authService->attemptLogin($request);

        if (! $result['success']) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => $result['message']]);
        }

        // If 2FA is enabled, redirect to 2FA challenge
        if ($result['user']->two_factor_enabled) {
            session(['2fa_user_id' => $result['user']->id]);
            Auth::logout();
            return redirect()->route('2fa.challenge');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, ' . $result['user']->name . '!');
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout($request);

        return redirect()->route('login')
            ->with('success', 'You have been logged out successfully.');
    }
}
