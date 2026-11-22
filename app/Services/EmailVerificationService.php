<?php

namespace App\Services;

use App\Mail\EmailVerificationCodeMail;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailVerificationService
{
    public const CODE_TTL_MINUTES = 15;

    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Issue a fresh 6-digit code and email it. Returns the plain code (tests only).
     */
    public function sendCode(StaffUser $staff): string
    {
        if ($staff->hasVerifiedEmail()) {
            throw new RuntimeException('This email is already verified.');
        }

        $code = (string) random_int(100000, 999999);

        $staff->forceFill([
            'email_verification_code' => hash('sha256', $code),
            'email_verification_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        Mail::to($staff->email)->send(new EmailVerificationCodeMail($code, $staff->first_name ?: $staff->name));

        return $code;
    }

    public function canResend(StaffUser $staff): bool
    {
        if ($staff->hasVerifiedEmail()) {
            return false;
        }

        if (! $staff->email_verification_expires_at) {
            return true;
        }

        $issuedAt = $staff->email_verification_expires_at->copy()->subMinutes(self::CODE_TTL_MINUTES);

        return $issuedAt->lte(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS));
    }

    public function verify(StaffUser $staff, string $code): bool
    {
        if ($staff->hasVerifiedEmail()) {
            return true;
        }

        $code = trim($code);
        if ($code === '' || ! $staff->email_verification_code || ! $staff->email_verification_expires_at) {
            return false;
        }

        if ($staff->email_verification_expires_at->isPast()) {
            return false;
        }

        if (! hash_equals($staff->email_verification_code, hash('sha256', $code))) {
            return false;
        }

        $staff->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return true;
    }
}
