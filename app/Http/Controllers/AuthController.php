<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        if (Auth::guard('staff')->check()) {
            return redirect()->route('staff.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim($validated['username']);
        $password = $validated['password'];
        $remember = $request->boolean('remember');

        // One Sign In page for both portals — do not advertise separate admin URLs.
        if ($this->attemptAdmin($login, $password, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        if ($this->attemptStaff($login, $password, $remember)) {
            $staff = Auth::guard('staff')->user();

            if (! $staff->isActive()) {
                Auth::guard('staff')->logout();
                $message = match ($staff->status) {
                    'pending' => 'Your account is pending HR approval.',
                    'rejected' => 'Your registration was not approved. Contact HR.',
                    'suspended' => 'Your account is suspended. Contact HR.',
                    default => 'Your account is not active.',
                };

                return back()->withErrors(['username' => $message])->onlyInput('username');
            }

            $request->session()->regenerate();

            return redirect()->intended(route('staff.dashboard'));
        }

        return back()->withErrors(['username' => 'Invalid credentials.'])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function attemptAdmin(string $login, string $password, bool $remember): bool
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return Auth::guard('admin')->attempt([$field => $login, 'password' => $password], $remember);
    }

    protected function attemptStaff(string $login, string $password, bool $remember): bool
    {
        // Employees sign in with registered Gmail/email.
        $email = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($login)
            : mb_strtolower($login);

        return Auth::guard('staff')->attempt(['email' => $email, 'password' => $password], $remember);
    }
}
