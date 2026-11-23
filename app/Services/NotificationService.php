<?php

namespace App\Services;

use App\Mail\LetterDecisionMail;
use App\Mail\SystemNotificationMail;
use App\Models\AdminUser;
use App\Models\StaffUser;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected ?string $lastEmailError = null;

    protected ?string $lastEmailRecipient = null;

    public function __construct(
        protected SmsService $sms,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Reason the most recent email could not be delivered, or null when it went out.
     */
    public function lastEmailError(): ?string
    {
        return $this->lastEmailError;
    }

    /**
     * Address the most recent email was delivered to, or null when none was sent.
     */
    public function lastEmailRecipient(): ?string
    {
        return $this->lastEmailRecipient;
    }

    public function notifyStaff(StaffUser $staff, string $title, string $message, ?string $url = null, ?string $key = null): void
    {
        $this->resetEmailState();

        $staff->notify(new AppNotification($title, $message, $url, $key));

        if ($staff->email) {
            $this->sendEmail($staff->email, $title, $message, $url);
        } else {
            $this->lastEmailError = 'This employee has no email address on file.';
        }

        if ($staff->phone) {
            $this->sendSms($staff->phone, $title, $message, $url);
        }
    }

    public function notifyStaffLetterDecision(
        StaffUser $staff,
        string $letterType,
        string $status,
        string $details,
        string $url,
        ?string $adminNotes = null
    ): void {
        $verb = $status === 'approved' ? 'Approved' : 'Rejected';
        $title = $letterType.' '.$verb;
        $message = $details.($adminNotes ? ' Note: '.$adminNotes : '');

        $this->resetEmailState();
        $staff->notify(new AppNotification($title, $message, $url));

        if (! $staff->email) {
            $this->lastEmailError = 'This employee has no email address on file.';

            return;
        }

        $this->sendLetterDecisionEmail(
            $staff->email,
            $staff->name,
            $letterType,
            $status,
            $details,
            $url,
            $adminNotes
        );

        if ($staff->phone) {
            $this->sendSms($staff->phone, $title, $message, $url);
        }
    }

    public function notifyAdmin(AdminUser $admin, string $title, string $message, ?string $url = null): void
    {
        $this->resetEmailState();

        $admin->notify(new AppNotification($title, $message, $url));

        if ($admin->email) {
            $this->sendEmail($admin->email, $title, $message, $url);
        }
    }

    public function notifyAllAdmins(string $title, string $message, ?string $url = null, ?string $role = null): void
    {
        $query = AdminUser::query();
        if ($role) {
            $query->where('role', $role);
        }

        foreach ($query->get() as $admin) {
            $this->notifyAdmin($admin, $title, $message, $url);
        }
    }

    protected function resetEmailState(): void
    {
        $this->lastEmailError = null;
        $this->lastEmailRecipient = null;
    }

    /**
     * True when SMTP has real credentials (not blank / example placeholders).
     */
    protected function mailIsConfigured(): bool
    {
        if (config('mail.default') !== 'smtp') {
            return true;
        }

        $username = strtolower(trim((string) config('mail.mailers.smtp.username')));
        $password = trim((string) config('mail.mailers.smtp.password'));

        if ($username === '' || $password === '') {
            return false;
        }

        $placeholderUsers = [
            'your@gmail.com',
            'example@gmail.com',
            'null',
        ];
        $placeholderPasswords = [
            'your-gmail-app-password',
            'password',
            'secret',
            'null',
        ];

        if (in_array($username, $placeholderUsers, true) || in_array(strtolower($password), $placeholderPasswords, true)) {
            return false;
        }

        return true;
    }

    protected function sendEmail(string $email, string $title, string $message, ?string $url): bool
    {
        $this->resetEmailState();

        if (! $this->mailIsConfigured()) {
            $this->lastEmailError = 'Email is not configured yet. Set a real Gmail address and App Password in the server .env (MAIL_USERNAME / MAIL_PASSWORD).';
            Log::warning('Notification email skipped: SMTP credentials missing or still placeholders', ['email' => $email]);
            $this->auditLog->log('email.skipped', "Email to {$email} skipped — \"{$title}\" (mail not configured)");

            return false;
        }

        try {
            Mail::to($email)->send(new SystemNotificationMail($title, $message, $url));
            $this->lastEmailRecipient = $email;
            $this->auditLog->log('email.sent', "Email delivered to {$email} — \"{$title}\"");

            return true;
        } catch (\Throwable $e) {
            $this->lastEmailError = $e->getMessage();
            Log::error('Notification email failed', [
                'email' => $email,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            $this->auditLog->log('email.failed', "Email to {$email} failed — \"{$title}\": ".$e->getMessage());

            return false;
        }
    }

    protected function sendLetterDecisionEmail(
        string $email,
        string $employeeName,
        string $letterType,
        string $status,
        string $details,
        ?string $url,
        ?string $adminNotes
    ): bool {
        if (! $this->mailIsConfigured()) {
            $this->lastEmailError = 'Email is not configured yet. Set a real Gmail address and App Password in the server .env (MAIL_USERNAME / MAIL_PASSWORD).';
            Log::warning('Letter decision email skipped: SMTP credentials missing or still placeholders', ['email' => $email]);
            $this->auditLog->log('email.skipped', "Email to {$email} skipped — {$letterType} {$status} (mail not configured)");

            return false;
        }

        try {
            Mail::to($email)->send(new LetterDecisionMail(
                $employeeName,
                $letterType,
                $status,
                $details,
                $url,
                $adminNotes
            ));
            $this->lastEmailRecipient = $email;
            $this->auditLog->log('email.sent', "Gmail notice delivered to {$email} — {$letterType} {$status}");

            return true;
        } catch (\Throwable $e) {
            $this->lastEmailError = $e->getMessage();
            Log::error('Letter decision email failed', [
                'email' => $email,
                'letter' => $letterType,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            $this->auditLog->log('email.failed', "Gmail notice to {$email} failed — {$letterType} {$status}: ".$e->getMessage());

            return false;
        }
    }

    protected function sendSms(string $phone, string $title, string $message, ?string $url): void
    {
        $body = 'SMART-DTR: '.$title.'. '.$message;
        if ($url) {
            $body .= ' '.$url;
        }

        $this->sms->send($phone, $body);
    }
}
