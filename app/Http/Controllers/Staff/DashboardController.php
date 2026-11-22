<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\LocatorRequest;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\ScheduleOverride;
use App\Models\TravelOrder;
use App\Services\DailyStatusService;
use App\Services\EmailChangeRequestService;
use App\Services\ScheduleWeekService;
use App\Services\LeaveApplicationExportService;
use App\Services\LeaveBalanceService;
use App\Services\NotificationService;
use App\Services\TravelAuthorityWordService;
use App\Services\TravelOrderWordService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DailyStatusService $dailyStatusService,
        protected LeaveBalanceService $leaveBalanceService,
        protected NotificationService $notifications,
        protected TravelAuthorityWordService $travelAuthorityWord,
        protected TravelOrderWordService $travelOrderWord,
        protected LeaveApplicationExportService $leaveApplicationExport,
        protected ScheduleWeekService $scheduleWeek,
        protected EmailChangeRequestService $emailChangeRequests
    ) {}

    public function index(): View
    {
        $staff = $this->staffUser();
        $personnelId = $staff->personnel_id;

        $todayStatus = null;
        $recentLogs = collect();
        $monthStats = ['present' => 0, 'late' => 0, 'absent' => 0];
        $leaveBalance = null;

        if ($personnelId) {
            $today = now()->toDateString();
            $todayStatus = $this->dailyStatusService->computeDailyStatus($personnelId, $today);
            $recentLogs = AttendanceLog::where('personnel_id', $personnelId)
                ->orderByDesc('date')
                ->orderByDesc('time')
                ->limit(10)
                ->get();

            $monthStart = now()->startOfMonth()->toDateString();
            $monthEnd = now()->endOfMonth()->toDateString();
            $records = Attendance::where('personnel_id', $personnelId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->get();

            foreach ($records as $record) {
                match ($record->status) {
                    'Present' => $monthStats['present']++,
                    'Late' => $monthStats['late']++,
                    'Absent' => $monthStats['absent']++,
                    default => null,
                };
            }

            $person = Personnel::find($personnelId);
            if ($person && ! $staff->isAttendanceOnly()) {
                $leaveBalance = $this->leaveBalanceService->ensureBalance($person);
            }
        }

        $pendingRequests = $staff->leaveRequests()->whereIn('status', ['pending', LeaveRequest::STATUS_DIVISION])->count();
        $unreadNotifications = $staff->unreadNotifications()->count();

        return view('staff.dashboard', compact('staff', 'todayStatus', 'recentLogs', 'monthStats', 'pendingRequests', 'leaveBalance', 'unreadNotifications'));
    }

    public function leave(): View
    {
        $staff = $this->staffUser();
        $requests = $staff->leaveRequests()->latest()->get();
        $leaveBalance = $staff->personnel_id
            ? $this->leaveBalanceService->ensureBalance(Personnel::find($staff->personnel_id))
            : null;

        return view('staff.leave', compact('staff', 'requests', 'leaveBalance'));
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $staff = $this->staffUser();

        $allowedTypes = array_merge(LeaveRequest::leaveTypesFor($staff), ['Leave', 'Travel', 'Sick', 'Emergency', 'Other']);

        $validated = $request->validate([
            'leave_type' => 'required|in:'.implode(',', $allowedTypes),
            'office_department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'salary' => 'nullable|string|max:100',
            'date_from' => 'required|date|after_or_equal:today',
            'date_to' => 'required|date|after_or_equal:date_from',
            'reason' => 'required|string|max:1000',
            'leave_details' => 'nullable|string|max:500',
            'commutation' => 'nullable|in:not_requested,requested',
        ]);

        $days = LeaveRequest::inclusiveDayCount($validated['date_from'], $validated['date_to']);

        if ($days > LeaveRequest::LONG_LEAVE_MAX_DAYS) {
            return back()->withErrors([
                'date_to' => 'The 60 Days Above leave form allows a maximum of '.LeaveRequest::LONG_LEAVE_MAX_DAYS.' days. Please choose an earlier end date.',
            ])->withInput();
        }

        if ($staff->personnel_id) {
            $personnel = Personnel::find($staff->personnel_id);
            if ($personnel && ! $this->leaveBalanceService->hasSufficientBalance($personnel, $validated['leave_type'], $days)) {
                return back()->withErrors(['leave_type' => 'Insufficient leave balance for this request.'])->withInput();
            }
        }

        $leaveRequest = LeaveRequest::create([
            ...$validated,
            'office_department' => $validated['office_department'] ?? ($staff->department ?: 'Isabela National High School'),
            'position' => $validated['position'] ?? ($staff->department ?: $staff->roleLabel()),
            'leave_details' => $validated['leave_details'] ?? $validated['reason'],
            'commutation' => $validated['commutation'] ?? 'not_requested',
            'staff_user_id' => $staff->id,
            'personnel_id' => $staff->personnel_id,
            'status' => 'pending',
        ]);

        $this->notifications->notifyAllAdmins(
            'New Leave Request',
            "{$staff->name} submitted a {$validated['leave_type']} request.",
            route('admin.hr.leave-requests'),
            'hr_officer'
        );

        return back()->with('success', 'Leave request submitted. HR will review your application.');
    }

    public function printLeave(LeaveRequest $leaveRequest): BinaryFileResponse
    {
        $staff = $this->staffUser();

        abort_unless($leaveRequest->staff_user_id === $staff->id, 403);
        abort_unless($leaveRequest->status === 'approved', 403);

        $path = $this->leaveApplicationExport->generateExcel($leaveRequest);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function printLeavePdf(LeaveRequest $leaveRequest): BinaryFileResponse|RedirectResponse
    {
        $staff = $this->staffUser();

        abort_unless($leaveRequest->staff_user_id === $staff->id, 403);
        abort_unless($leaveRequest->status === 'approved', 403);

        try {
            $path = $this->leaveApplicationExport->generatePdf($leaveRequest);

            return response()->download($path, basename($path), [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Leave PDF download failed: '.$e->getMessage());
        }
    }

    public function locator(): View
    {
        $staff = $this->staffUser();
        $requests = $staff->locatorRequests()->latest()->get();

        return view('staff.locator', compact('staff', 'requests'));
    }

    public function printLocator(LocatorRequest $locatorRequest): BinaryFileResponse|RedirectResponse
    {
        $staff = $this->staffUser();

        abort_unless($locatorRequest->staff_user_id === $staff->id, 403);
        abort_unless($locatorRequest->status === 'approved', 403);

        try {
            return $this->travelAuthorityWord->download($locatorRequest);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('staff.locator')
                ->with('error', 'Could not print Locator Slip: '.$e->getMessage());
        }
    }

    public function storeLocator(Request $request): RedirectResponse
    {
        $staff = $this->staffUser();

        $validated = $request->validate([
            'position_designation' => 'required|string|max:255',
            'date' => 'required|date|after_or_equal:today',
            'date_to' => 'nullable|date|after_or_equal:date',
            'time_out' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'time_in_expected' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'destination' => 'required|string|max:255',
            'purpose' => 'required|string|max:1000',
            'nature' => 'required|in:Official Business,Official Time',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('locator_attachments', 'local');
                if ($attachmentPath === false) {
                    return back()->withInput()->withErrors([
                        'attachment' => 'Could not save the attachment on the server. Please try again or contact HR.',
                    ]);
                }
            }

            LocatorRequest::create([
                'position_designation' => $validated['position_designation'],
                'date' => $validated['date'],
                'date_to' => $validated['date_to'] ?? $validated['date'],
                'time_out' => $this->normalizeTime($validated['time_out']),
                'time_in_expected' => $this->normalizeTime($validated['time_in_expected']),
                'destination' => $validated['destination'],
                'purpose' => $validated['purpose'],
                'nature' => $validated['nature'],
                'attachment_path' => $attachmentPath,
                'staff_user_id' => $staff->id,
                'personnel_id' => $staff->personnel_id,
                'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            report($e);
            Log::error('Locator slip save failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', $this->databaseSaveErrorMessage($e, 'locator slip'));
        }

        try {
            $this->notifications->notifyAllAdmins(
                'New Locator Slip Request',
                "{$staff->name} requested a locator slip to {$validated['destination']}.",
                route('admin.hr.locator-requests'),
                'hr_officer'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Locator slip submitted. HR/Admin will review your request.');
    }

    public function travelOrders(): View
    {
        $staff = $this->staffUser();
        $orders = $staff->travelOrders()->latest()->get();

        return view('staff.travel-orders', compact('staff', 'orders'));
    }

    public function storeTravelOrder(Request $request): RedirectResponse
    {
        $staff = $this->staffUser();

        $validated = $request->validate([
            'position_designation' => 'required|string|max:255',
            'purpose' => 'required|string|max:1000',
            'host_of_activity' => 'required|string|max:255',
            'travel_type' => 'required|in:within,outside',
            'date_from' => 'required|date|after_or_equal:today',
            'date_to' => 'required|date|after_or_equal:date_from',
            'destination' => 'required|string|max:255',
            'fund_source' => 'required|string|max:255',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ]);

        try {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('travel_order_attachments', 'local');
                if ($attachmentPath === false) {
                    return back()->withInput()->withErrors([
                        'attachment' => 'Could not save the attachment on the server. Please try again or contact HR.',
                    ]);
                }
            }

            TravelOrder::create([
                'staff_user_id' => $staff->id,
                'personnel_id' => $staff->personnel_id,
                'position_designation' => $validated['position_designation'],
                'permanent_station' => 'Isabela National High School',
                'purpose' => $validated['purpose'],
                'host_of_activity' => $validated['host_of_activity'],
                'travel_type' => $validated['travel_type'],
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'destination' => $validated['destination'],
                'fund_source' => $validated['fund_source'],
                'attachment_path' => $attachmentPath,
                'status' => 'pending',
            ]);
        } catch (QueryException $e) {
            report($e);
            Log::error('Travel order save failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', $this->databaseSaveErrorMessage($e, 'travel order'));
        }

        try {
            $this->notifications->notifyAllAdmins(
                'New Travel Order Request',
                "{$staff->name} requested a travel order to {$validated['destination']}.",
                route('admin.hr.travel-orders'),
                'hr_officer'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Travel order submitted. HR/Admin will review your request.');
    }

    public function printTravelOrder(TravelOrder $travelOrder): BinaryFileResponse|RedirectResponse
    {
        $staff = $this->staffUser();

        abort_unless($travelOrder->staff_user_id === $staff->id, 403);
        abort_unless($travelOrder->status === 'approved', 403);

        try {
            return $this->travelOrderWord->download($travelOrder);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('staff.travel-orders')
                ->with('error', 'Could not print Travel Order: '.$e->getMessage());
        }
    }

    public function attendance(Request $request): View
    {
        $staff = $this->staffUser();
        $month = $request->input('month', now()->format('Y-m'));

        $logs = collect();
        $attendance = collect();

        if ($staff->personnel_id) {
            $start = \Carbon\Carbon::parse($month.'-01')->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $logs = AttendanceLog::where('personnel_id', $staff->personnel_id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->orderBy('date')
                ->orderBy('time')
                ->get()
                ->groupBy(fn ($l) => $l->date->format('Y-m-d'));

            $attendance = Attendance::where('personnel_id', $staff->personnel_id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->keyBy(fn ($a) => $a->date->format('Y-m-d'));
        }

        return view('staff.attendance', compact('staff', 'month', 'logs', 'attendance'));
    }

    public function schedule(): View
    {
        $staff = $this->staffUser()->load('personnel');
        $personnel = $staff->personnel;
        $schedules = collect();
        $overrides = collect();

        if ($personnel) {
            $schedules = Schedule::where('personnel_id', $personnel->id)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();
            $overrides = ScheduleOverride::where('personnel_id', $personnel->id)
                ->latest('date')
                ->limit(50)
                ->get();
        }

        return view('staff.schedule', compact('staff', 'personnel', 'schedules', 'overrides'));
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $staff = $this->staffUser();
        if (! $staff->personnel_id) {
            return back()->with('error', 'Your account is not linked to a personnel record yet. Contact HR.');
        }

        $validated = $request->validate([
            'days' => 'required|array|min:1',
            'days.*' => 'integer|min:0|max:6',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'subject' => 'required|string|max:255',
        ]);

        $saved = $this->scheduleWeek->assignWeek(
            [(int) $staff->personnel_id],
            $validated['days'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['subject']
        );

        return back()->with('success', $saved === 1
            ? 'Weekly schedule saved.'
            : "Saved {$saved} weekdays in one step.");
    }

    public function destroySchedule(Schedule $schedule): RedirectResponse
    {
        $staff = $this->staffUser();
        abort_unless($staff->personnel_id && $schedule->personnel_id === $staff->personnel_id, 403);

        $schedule->delete();

        return back()->with('success', 'Schedule entry removed.');
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $staff = $this->staffUser();
        if (! $staff->personnel_id) {
            return back()->with('error', 'Your account is not linked to a personnel record yet. Contact HR.');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'override_type' => 'required|in:Class,Off-duty',
            'start_time' => 'nullable|required_if:override_type,Class',
            'end_time' => 'nullable|required_if:override_type,Class',
            'subject' => 'nullable|string|max:255',
        ]);

        ScheduleOverride::updateOrCreate(
            ['personnel_id' => $staff->personnel_id, 'date' => $validated['date']],
            [
                'override_type' => $validated['override_type'],
                'start_time' => isset($validated['start_time']) ? date('H:i:s', strtotime($validated['start_time'])) : null,
                'end_time' => isset($validated['end_time']) ? date('H:i:s', strtotime($validated['end_time'])) : null,
                'subject' => $validated['subject'] ?? null,
            ]
        );

        $this->dailyStatusService->computeAndPersist((int) $staff->personnel_id, $validated['date']);

        return back()->with('success', 'Schedule override saved.');
    }

    public function destroyOverride(ScheduleOverride $override): RedirectResponse
    {
        $staff = $this->staffUser();
        abort_unless($staff->personnel_id && $override->personnel_id === $staff->personnel_id, 403);

        $date = $override->date->toDateString();
        $override->delete();
        $this->dailyStatusService->computeAndPersist((int) $staff->personnel_id, $date);

        return back()->with('success', 'Override removed.');
    }

    public function profile(): View
    {
        $staff = $this->staffUser()->load('personnel');
        $leaveCredits = $staff->personnel
            ? $this->leaveBalanceService->remaining($staff->personnel)
            : null;
        $pendingEmailChange = $staff->emailChangeRequests()->pending()->latest()->first();
        $recentEmailChanges = $staff->emailChangeRequests()->latest()->limit(5)->get();

        return view('staff.profile', compact('staff', 'leaveCredits', 'pendingEmailChange', 'recentEmailChanges'));
    }

    public function requestEmailChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'new_email' => ['required', 'email:rfc', 'max:255', 'confirmed'],
            'new_email_confirmation' => ['required', 'email:rfc', 'max:255'],
        ], [
            'new_email.confirmed' => 'The new Gmail confirmation does not match.',
        ]);

        try {
            $this->emailChangeRequests->request(
                $this->staffUser(),
                $validated['new_email']
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['new_email' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('staff.profile')
            ->with('success', 'Gmail change submitted. Keep using your current email until HR approves the request.');
    }

    protected function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }

    protected function databaseSaveErrorMessage(QueryException $e, string $label): string
    {
        if (str_contains($e->getMessage(), "Field 'id' doesn't have a default value")) {
            return "Your {$label} could not be saved because the database id column is missing AUTO_INCREMENT. Run database/repair-auto-increment.sql in phpMyAdmin, then try again.";
        }

        return "Your {$label} could not be saved because of a server problem. Please try again later or contact HR.";
    }
}
