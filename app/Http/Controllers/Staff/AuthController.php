<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Models\StaffUser;
use App\Rules\StrongPassword;
use App\Services\EmailVerificationService;
use App\Services\FaceSampleEnrollmentService;
use App\Services\RegistrationSchemaRepair;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('staff.register');
    }

    public function register(Request $request, FaceSampleEnrollmentService $faces, RegistrationSchemaRepair $schema): RedirectResponse|JsonResponse
    {
        return $this->attemptRegistration($request, $faces, $schema, 1);
    }

    private function attemptRegistration(Request $request, FaceSampleEnrollmentService $faces, RegistrationSchemaRepair $schema, int $attempt): RedirectResponse|JsonResponse
    {
        $request->merge([
            'last_name' => $this->formatPersonName((string) $request->input('last_name')),
            'first_name' => $this->formatPersonName((string) $request->input('first_name')),
            'middle_name' => $this->formatPersonName((string) $request->input('middle_name')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'department' => filled(trim((string) $request->input('department')))
                ? trim((string) $request->input('department'))
                : null,
            'phone' => $request->filled('phone') ? preg_replace('/\D+/', '', (string) $request->input('phone')) : null,
        ]);

        $nameRule = ['required', 'string', 'max:100', 'regex:/^\p{Lu}[\p{L}]*(?:[ .\'\-]\p{Lu}[\p{L}]*)*$/u'];

        $validated = $request->validate([
            'last_name' => $nameRule,
            'first_name' => $nameRule,
            'middle_name' => $nameRule,
            'suffix' => 'nullable|in:Jr.,Sr.,II,III,IV,V',
            'email' => 'required|email:rfc|max:255|unique:staff_users,email',
            'phone' => 'nullable|regex:/^09[0-9]{9}$/|unique:staff_users,phone',
            'password' => ['required', 'string', 'confirmed', new StrongPassword],
            'department' => 'nullable|string|max:100|regex:/^[\p{L}]+(?:[ .&\/\-][\p{L}]+)*$/u',
            'images' => ['required', 'array', 'size:3'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg', 'max:5120', 'dimensions:min_width=320,min_height=240'],
            'blink_verifications' => ['required', 'array', 'size:3'],
            'blink_verifications.*' => ['required', 'accepted'],
        ], [
            'last_name.regex' => 'Last name must start with a capital letter and contain letters only.',
            'first_name.regex' => 'First name must start with a capital letter and contain letters only.',
            'middle_name.regex' => 'Middle name must start with a capital letter and contain letters only.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email address is already used by another account. Sign in instead, or use a different email.',
            'phone.regex' => 'Mobile number must be 11 digits starting with 09.',
            'phone.unique' => 'This mobile number is already registered to another account.',
            'department.regex' => 'Department may contain letters only.',
            'images.required' => 'Register your face with three blink captures before creating the account.',
            'images.size' => 'Register your face with three blink captures before creating the account.',
            'blink_verifications.required' => 'Each of the three face captures must have its own verified blink.',
            'blink_verifications.size' => 'Each of the three face captures must have its own verified blink.',
            'blink_verifications.*.accepted' => 'A fresh blink is required for every capture.',
        ]);

        $personnelId = null;
        $staff = null;

        try {
            DB::beginTransaction();

            $staff = StaffUser::create([
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'],
                'suffix' => $validated['suffix'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'department' => $validated['department'] ?? null,
                'name' => StaffUser::composeName(
                    $validated['last_name'],
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['suffix'] ?? null
                ),
                'username' => null,
                'role' => 'employee',
                'status' => 'pending',
            ]);

            $personnel = Personnel::create([
                'name' => $staff->name,
                'role' => 'Employee',
                'employee_number' => StaffUser::nextEmployeeNumber(),
                'is_active' => false,
                'deactivated_at' => now(),
            ]);
            $personnelId = $personnel->id;
            $staff->update([
                'personnel_id' => $personnel->id,
                'employee_number' => $personnel->employee_number,
            ]);

            $faces->saveThreeSamples(
                $personnel,
                $request->file('images'),
                'face.registered_with_account',
                "{$staff->name} registered three blink-verified face samples while creating an account",
                false
            );

            DB::commit();
        } catch (UniqueConstraintViolationException $e) {
            DB::rollBack();
            $this->deleteRegistrationFaces($personnelId);

            return $this->registrationFailed($request, [
                $this->duplicateField($e->getMessage()) => 'This account was already registered. Please sign in instead.',
            ]);
        } catch (QueryException $e) {
            // QueryException is a RuntimeException; never show raw SQL to the public.
            DB::rollBack();
            $this->deleteRegistrationFaces($personnelId);

            if ($attempt === 1 && $schema->isRepairable($e)) {
                try {
                    $fixed = $schema->repair();
                } catch (\Throwable $repairError) {
                    report($repairError);
                    $fixed = null;
                }

                if (is_array($fixed) && $fixed !== []) {
                    Log::warning('Registration schema was repaired after a database error. Retrying the save.', [
                        'repaired' => $fixed,
                    ]);

                    return $this->attemptRegistration($request, $faces, $schema, 2);
                }
            }

            report($e);

            return $this->registrationFailed($request, [
                'email' => 'Registration could not be saved because of a server problem. Please try again later or contact HR.',
            ]);
        } catch (RuntimeException $e) {
            DB::rollBack();
            $this->deleteRegistrationFaces($personnelId);

            return $this->registrationFailed($request, ['images' => $e->getMessage()]);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->deleteRegistrationFaces($personnelId);
            throw $e;
        }

        $request->session()->put('email_verification_email', $staff->email);

        $mailSent = true;
        try {
            app(EmailVerificationService::class)->sendCode($staff->fresh());
        } catch (\Throwable $e) {
            report($e);
            $mailSent = false;
        }

        $message = $mailSent
            ? 'Account created. Enter the 6-digit code sent to your email to verify it. HR will review your registration after verification.'
            : 'Account created, but the verification email could not be sent. Use Resend code on the next page, or contact HR.';

        if ($request->expectsJson()) {
            session()->flash('success', $message);

            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect' => route('staff.verify-email'),
            ]);
        }

        return redirect()->route('staff.verify-email')->with('success', $message);
    }

    public function showVerifyEmail(Request $request): View|RedirectResponse
    {
        $email = old('email', $request->session()->get('email_verification_email', ''));

        return view('staff.verify-email', compact('email'));
    }

    public function verifyEmail(Request $request, EmailVerificationService $verification): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ], [
            'code.size' => 'Enter the 6-digit code from your email.',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $staff = StaffUser::query()->where('email', $email)->first();

        if (! $staff) {
            return back()->withInput()->withErrors(['email' => 'No registration found for that email.']);
        }

        if ($staff->hasVerifiedEmail()) {
            $request->session()->forget('email_verification_email');

            return redirect()->route('login')->with('success', 'Your email is already verified. Sign in after HR approves your account.');
        }

        if (! $verification->verify($staff, $validated['code'])) {
            return back()->withInput()->withErrors(['code' => 'Invalid or expired code. Request a new one if needed.']);
        }

        $request->session()->forget('email_verification_email');

        return redirect()->route('login')->with('success', 'Email verified. Wait for HR approval before signing in.');
    }

    public function resendVerificationCode(Request $request, EmailVerificationService $verification): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $staff = StaffUser::query()->where('email', $email)->first();

        if (! $staff) {
            return back()->withInput()->withErrors(['email' => 'No registration found for that email.']);
        }

        if ($staff->hasVerifiedEmail()) {
            return redirect()->route('login')->with('success', 'Your email is already verified. You can sign in after HR approval.');
        }

        if (! $verification->canResend($staff)) {
            return back()->withInput()->withErrors(['email' => 'Please wait about a minute before requesting another code.']);
        }

        try {
            $verification->sendCode($staff);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        $request->session()->put('email_verification_email', $staff->email);

        return back()->with('success', 'A new verification code was sent to '.$staff->email.'.');
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function registrationFailed(Request $request, array $errors): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => reset($errors) ?: 'Registration could not be completed.',
                'errors' => $errors,
            ], 422);
        }

        return back()->withInput()->withErrors($errors);
    }

    private function deleteRegistrationFaces(?int $personnelId): void
    {
        if ($personnelId) {
            Storage::disk('faces')->deleteDirectory((string) $personnelId);
        }
    }

    /**
     * Work out which field a database unique-index violation refers to.
     */
    private function duplicateField(string $errorMessage): string
    {
        foreach (['email', 'phone', 'employee_number'] as $field) {
            if (str_contains($errorMessage, "staff_users_{$field}_unique")) {
                return $field;
            }
        }

        return 'email';
    }

    /**
     * Letters only; each word starts with an uppercase letter (Juan, Dela Cruz).
     */
    private function formatPersonName(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        return preg_replace_callback('/(^|[ .\'\-])(\p{L})(\p{L}*)/u', function (array $match): string {
            return $match[1].mb_strtoupper($match[2], 'UTF-8').mb_strtolower($match[3], 'UTF-8');
        }, $value) ?? $value;
    }

    public function showLogin(): RedirectResponse
    {
        // Single public Sign In page for employees and admins.
        return redirect()->route('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::guard('staff')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }

        $staff = $this->staffUser();

        if (! $staff->hasVerifiedEmail()) {
            Auth::guard('staff')->logout();
            $request->session()->put('email_verification_email', $staff->email);

            return redirect()->route('staff.verify-email')
                ->withErrors(['code' => 'Verify your email with the 6-digit code before signing in.']);
        }

        if (! $staff->isActive()) {
            Auth::guard('staff')->logout();
            $message = match ($staff->status) {
                'pending' => 'Your account is pending HR approval.',
                'rejected' => 'Your registration was not approved. Contact HR.',
                'suspended' => 'Your account is suspended. Contact HR.',
                default => 'Your account is not active.',
            };

            return back()->withErrors(['email' => $message]);
        }

        $request->session()->regenerate();

        return redirect()->route('staff.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
