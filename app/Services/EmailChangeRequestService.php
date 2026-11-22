<?php

namespace App\Services;

use App\Models\EmailChangeRequest;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmailChangeRequestService
{
    public function __construct(
        protected NotificationService $notifications,
        protected AuditLogService $auditLog
    ) {}

    public function request(StaffUser $staff, string $newEmail): EmailChangeRequest
    {
        $newEmail = mb_strtolower(trim($newEmail));
        $current = mb_strtolower(trim((string) $staff->email));

        if ($newEmail === $current) {
            throw new RuntimeException('Enter a different Gmail address from your current one.');
        }

        if ($staff->emailChangeRequests()->pending()->exists()) {
            throw new RuntimeException('You already have a Gmail change waiting for HR approval.');
        }

        if (StaffUser::query()->where('email', $newEmail)->where('id', '!=', $staff->id)->exists()) {
            throw new RuntimeException('That Gmail address is already used by another account.');
        }

        if (EmailChangeRequest::query()->pending()->where('new_email', $newEmail)->exists()) {
            throw new RuntimeException('That Gmail address is already pending approval for another employee.');
        }

        $request = EmailChangeRequest::create([
            'staff_user_id' => $staff->id,
            'current_email' => $current,
            'new_email' => $newEmail,
            'status' => 'pending',
        ]);

        $this->auditLog->log(
            'email_change.requested',
            "{$staff->name} requested Gmail change from {$current} to {$newEmail}",
            $request
        );

        $this->notifications->notifyAllAdmins(
            'Gmail change pending',
            "{$staff->name} requested to change their Gmail from {$current} to {$newEmail}.",
            route('admin.hr.email-changes')
        );

        return $request;
    }

    public function approve(EmailChangeRequest $request, int $adminId, ?string $notes = null): EmailChangeRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException('This Gmail change request was already processed.');
        }

        $approved = DB::transaction(function () use ($request, $adminId, $notes) {
            $staff = StaffUser::query()->lockForUpdate()->findOrFail($request->staff_user_id);
            $newEmail = mb_strtolower(trim($request->new_email));

            if (StaffUser::query()->where('email', $newEmail)->where('id', '!=', $staff->id)->exists()) {
                throw new RuntimeException('That Gmail address is already used by another account.');
            }

            $staff->update(['email' => $newEmail]);

            $request->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'admin_notes' => $notes,
            ]);

            $this->auditLog->log(
                'email_change.approved',
                "Approved Gmail change for {$staff->name} to {$newEmail}",
                $request
            );

            return $request->fresh(['staffUser', 'reviewer']);
        });

        if ($approved->staffUser) {
            $this->notifications->notifyStaff(
                $approved->staffUser,
                'Gmail change approved',
                "Your login Gmail is now {$approved->new_email}. Use this address the next time you sign in.",
                route('staff.profile')
            );
        }

        return $approved;
    }

    public function reject(EmailChangeRequest $request, int $adminId, ?string $notes = null): EmailChangeRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException('This Gmail change request was already processed.');
        }

        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);

        $this->auditLog->log(
            'email_change.rejected',
            "Rejected Gmail change for {$request->staffUser?->name} to {$request->new_email}",
            $request
        );

        if ($request->staffUser) {
            $message = "Your request to change Gmail to {$request->new_email} was not approved.";
            if ($notes) {
                $message .= ' Note: '.$notes;
            }

            $this->notifications->notifyStaff(
                $request->staffUser,
                'Gmail change rejected',
                $message,
                route('staff.profile')
            );
        }

        return $request->fresh(['staffUser', 'reviewer']);
    }
}
