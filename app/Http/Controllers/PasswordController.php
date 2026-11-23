<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\StaffUser;
use App\Rules\StrongPassword;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLog,
        protected NotificationService $notifications
    ) {}

    public function showForgot(string $guard): View
    {
        return view('auth.forgot-password', ['guard' => $guard]);
    }

    public function sendResetLink(Request $request, string $guard): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = $guard === 'admin'
            ? AdminUser::where('email', $request->email)->first()
            : StaffUser::where('email', $request->email)->first();

        if ($user) {
            $token = Str::random(64);
            $user->update([
                'reset_token' => hash('sha256', $token),
                'reset_token_expires_at' => now()->addHour(),
            ]);

            $route = $guard === 'admin' ? 'password.reset.admin' : 'password.reset.staff';
            $url = route($route, ['token' => $token, 'email' => $user->email]);

            $this->notifications->notifyAdmin(
                $guard === 'admin' ? $user : AdminUser::first(),
                'Password Reset',
                'Use this link to reset password: '.$url,
                $url
            );

            if ($guard === 'staff') {
                $this->notifications->notifyStaff($user, 'Password Reset', 'Use the link sent to your email to reset your password.', $url);
            }
        }

        return back()->with('success', 'If that email exists, a reset link has been sent.');
    }

    public function showReset(string $guard, Request $request): View
    {
        return view('auth.reset-password', [
            'guard' => $guard,
            'token' => $request->token,
            'email' => $request->email,
        ]);
    }

    public function reset(Request $request, string $guard): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        $user = $guard === 'admin'
            ? AdminUser::where('email', $request->email)->first()
            : StaffUser::where('email', $request->email)->first();

        if (! $user || ! $user->reset_token || ! $user->reset_token_expires_at?->isFuture()) {
            return back()->withErrors(['email' => 'Invalid or expired reset link.']);
        }

        if (! hash_equals($user->reset_token, hash('sha256', $request->token))) {
            return back()->withErrors(['email' => 'Invalid or expired reset link.']);
        }

        $user->update([
            'password' => $request->password,
            'reset_token' => null,
            'reset_token_expires_at' => null,
        ]);

        $this->auditLog->log('password.reset', 'Password reset via email link', $user);

        return redirect()->route('login')->with('success', 'Password updated. You may sign in now.');
    }

    public function showChangePassword(string $guard): View
    {
        return view('auth.change-password', compact('guard'));
    }

    public function changePassword(Request $request, string $guard): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
        ]);

        if ($guard === 'admin') {
            $user = $this->adminUser();
            if (! Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            $user->update(['password' => $request->password]);
            $this->auditLog->log('password.changed', 'Password changed by user', $user);
        } else {
            /** @var StaffUser $user */
            $user = auth('staff')->user();
            abort_unless($user, 403);
            if (! Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            $user->update(['password' => $request->password]);
            $this->auditLog->log('password.changed', 'Password changed by user', $user);
        }

        return back()->with('success', 'Password updated successfully.');
    }
}
