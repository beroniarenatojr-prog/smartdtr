<?php

namespace App\Services;

use App\Models\LeaveRecord;
use App\Models\LeaveRequest;
use App\Models\Personnel;
use App\Models\StaffUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveRequestService
{
    public function __construct(
        protected DailyStatusService $dailyStatusService,
        protected LeaveBalanceService $leaveBalanceService,
        protected NotificationService $notificationService,
        protected AuditLogService $auditLogService,
        protected FaceSampleEnrollmentService $faceSamples
    ) {}

    public function markPendingDivision(LeaveRequest $request, int $adminId, ?string $notes = null): LeaveRequest
    {
        $request->update([
            'status' => LeaveRequest::STATUS_DIVISION,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);

        $this->auditLogService->log(
            'leave.division_pending',
            "Marked leave request #{$request->id} pending Division Office approval",
            $request
        );

        if ($request->staffUser) {
            $this->notificationService->notifyStaff(
                $request->staffUser,
                'Leave pending Division Office',
                'Your '.$request->leave_type.' from '.$request->date_from->format('F d, Y').' to '.$request->date_to->format('F d, Y').' is pending approval of the Division Office.'.($notes ? ' Note: '.$notes : ''),
                route('staff.leave')
            );
        }

        return $request->fresh();
    }

    public function approve(LeaveRequest $request, int $adminId, ?string $notes = null): LeaveRequest
    {
        $approved = DB::transaction(function () use ($request, $adminId, $notes) {
            $request->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'admin_notes' => $notes,
            ]);

            $personnelId = $this->resolvePersonnelId($request);

            if ($personnelId) {
                $this->leaveBalanceService->deductForRequest($request);

                $leaveType = (str_contains(strtolower($request->leave_type), 'travel') || $request->leave_type === 'Travel')
                    ? 'Travel'
                    : 'Leave';

                for ($date = $request->date_from->copy(); $date->lte($request->date_to); $date->addDay()) {
                    LeaveRecord::updateOrCreate(
                        [
                            'personnel_id' => $personnelId,
                            'date' => $date->toDateString(),
                            'leave_type' => $leaveType,
                        ],
                        [
                            'reason' => $request->reason,
                            'created_by' => $adminId,
                        ]
                    );

                    $this->dailyStatusService->computeAndPersist($personnelId, $date->toDateString());
                }

                if (! $request->personnel_id) {
                    $request->update(['personnel_id' => $personnelId]);
                }
            }

            $this->auditLogService->log('leave.approved', "Approved leave request #{$request->id}", $request);

            return $request->fresh();
        });

        // Sent outside the transaction so a slow SMTP round-trip does not hold row locks.
        if ($approved->staffUser) {
            $this->notificationService->notifyStaffLetterDecision(
                $approved->staffUser,
                'Leave Application',
                'approved',
                'Your '.$approved->leave_type.' from '.$approved->date_from->format('F d, Y').' to '.$approved->date_to->format('F d, Y').' has been approved by the school administration.',
                route('staff.leave'),
                $approved->admin_notes
            );
        }

        return $approved;
    }

    public function reject(LeaveRequest $request, int $adminId, ?string $notes = null): LeaveRequest
    {
        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);

        $this->auditLogService->log('leave.rejected', "Rejected leave request #{$request->id}", $request);
        if ($request->staffUser) {
            $this->notificationService->notifyStaffLetterDecision(
                $request->staffUser,
                'Leave Application',
                'rejected',
                'Your '.$request->leave_type.' request from '.$request->date_from->format('F d, Y').' to '.$request->date_to->format('F d, Y').' was not approved.',
                route('staff.leave'),
                $notes
            );
        }

        return $request->fresh();
    }

    protected function resolvePersonnelId(LeaveRequest $request): ?int
    {
        if ($request->personnel_id) {
            return $request->personnel_id;
        }

        $staff = $request->staffUser;

        if ($staff->personnel_id) {
            return $staff->personnel_id;
        }

        $personnel = Personnel::create([
            'name' => $staff->name,
            'role' => $staff->roleLabel(),
            'employee_number' => $staff->employee_number ?: StaffUser::nextEmployeeNumber(),
        ]);

        $this->leaveBalanceService->ensureBalance($personnel);
        $staff->update([
            'personnel_id' => $personnel->id,
            'employee_number' => $personnel->employee_number,
        ]);

        return $personnel->id;
    }

    public function activateStaff(StaffUser $staff, ?int $personnelId = null): StaffUser
    {
        $registeredPersonnelId = $staff->personnel_id;

        $activated = DB::transaction(function () use ($staff, $personnelId) {
            $number = $staff->employee_number;

            if ($personnelId) {
                $personnel = Personnel::find($personnelId);
                $number = $number ?: ($personnel?->employee_number ?: StaffUser::nextEmployeeNumber());
                if ($personnel && ! $personnel->employee_number) {
                    $personnel->update(['employee_number' => $number]);
                }
                $staff->update([
                    'personnel_id' => $personnelId,
                    'employee_number' => $number,
                    'status' => 'active',
                ]);
            } elseif (! $staff->personnel_id) {
                $number = $number ?: StaffUser::nextEmployeeNumber();
                $personnel = Personnel::create([
                    'name' => $staff->name,
                    'role' => $staff->roleLabel(),
                    'employee_number' => $number,
                ]);
                $this->leaveBalanceService->ensureBalance($personnel);
                $staff->update([
                    'personnel_id' => $personnel->id,
                    'employee_number' => $number,
                    'status' => 'active',
                ]);
            } else {
                $personnel = Personnel::find($staff->personnel_id);
                $number = $number ?: ($personnel?->employee_number ?: StaffUser::nextEmployeeNumber());
                $staff->update(['employee_number' => $number, 'status' => 'active']);
                if ($personnel && ! $personnel->employee_number) {
                    $personnel->update(['employee_number' => $number]);
                }
                if ($personnel) {
                    $this->leaveBalanceService->ensureBalance($personnel);
                }
            }

            return $staff->fresh();
        });

        $this->faceSamples->publishPendingRegistration($registeredPersonnelId, $activated->personnel_id);

        $this->notificationService->notifyStaff(
            $activated,
            'Account Approved',
            'Your SMART-DTR staff account has been approved. You can now sign in and use the portal. Employee No.: '.$activated->employee_number,
            route('login')
        );

        return $activated;
    }
}
