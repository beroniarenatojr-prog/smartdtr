<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailChangeRequest;
use App\Models\LeaveRequest;
use App\Models\LocatorRequest;
use App\Models\Personnel;
use App\Models\StaffUser;
use App\Models\TravelOrder;
use App\Services\AuditLogService;
use App\Services\EmailChangeRequestService;
use App\Services\FaceSampleEnrollmentService;
use App\Services\LeaveApplicationExportService;
use App\Services\LeaveRequestService;
use App\Services\LocatorRequestService;
use App\Services\NotificationService;
use App\Services\TravelAuthorityWordService;
use App\Services\TravelOrderService;
use App\Services\TravelOrderWordService;
use RuntimeException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HrController extends Controller
{
    public function __construct(
        protected LeaveRequestService $leaveRequestService,
        protected LocatorRequestService $locatorRequestService,
        protected TravelOrderService $travelOrderService,
        protected TravelAuthorityWordService $travelAuthorityWord,
        protected TravelOrderWordService $travelOrderWord,
        protected LeaveApplicationExportService $leaveApplicationExport,
        protected AuditLogService $auditLog,
        protected NotificationService $notifications,
        protected FaceSampleEnrollmentService $faceSamples,
        protected EmailChangeRequestService $emailChangeRequests
    ) {}

    /**
     * Flash the outcome of an HR decision together with whether the staff email went out.
     *
     * @param  array<string, string>  $extra  extra session keys, e.g. auto_download_word
     */
    private function backWithEmailStatus(string $message, array $extra = []): RedirectResponse
    {
        $response = back();

        foreach ($extra as $key => $value) {
            $response->with($key, $value);
        }

        // Approval/rejection already succeeded — never flash email problems as a blocking error.
        if ($error = $this->notifications->lastEmailError()) {
            $message .= ' (In-app notice saved; email not sent: '.$error.')';
        } elseif ($recipient = $this->notifications->lastEmailRecipient()) {
            $message .= " Email sent to {$recipient}.";
        }

        return $response->with('success', $message);
    }

    public function staffAccounts(): View
    {
        $staff = StaffUser::with('personnel')->latest()->get();
        $personnel = Personnel::active()->orderBy('name')->get();

        return view('admin.hr.staff', compact('staff', 'personnel'));
    }

    public function activateStaff(Request $request, StaffUser $staffUser): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if (! $staffUser->hasVerifiedEmail()) {
            return back()->with('error', "{$staffUser->name} has not verified their email yet. Ask them to enter the code sent during Sign Up.");
        }

        $validated = $request->validate([
            'personnel_id' => 'nullable|exists:personnel,id',
        ]);

        $staffUser = $this->leaveRequestService->activateStaff(
            $staffUser,
            $validated['personnel_id'] ?? null
        );

        $this->auditLog->log('staff.approved', "Approved staff account: {$staffUser->name} ({$staffUser->employee_number})", $staffUser);

        return $this->backWithEmailStatus("Account for {$staffUser->name} is now active. Employee No.: {$staffUser->employee_number}.");
    }

    public function rejectStaff(StaffUser $staffUser): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if ($staffUser->status === 'pending' && $staffUser->personnel && ! $staffUser->personnel->is_active) {
            $this->faceSamples->discardPendingRegistration($staffUser->personnel);
            $staffUser->refresh();
        }

        $staffUser->update(['status' => 'rejected']);
        $this->auditLog->log('staff.rejected', "Rejected staff account: {$staffUser->name}", $staffUser);
        $this->notifications->notifyStaff(
            $staffUser,
            'Registration Rejected',
            'Your SMART-DTR staff account registration was not approved. Please contact HR.',
            route('home')
        );

        return $this->backWithEmailStatus("Registration for {$staffUser->name} was rejected.");
    }

    public function leaveRequests(): View
    {
        $requests = LeaveRequest::with(['staffUser', 'personnel', 'reviewer'])
            ->latest()
            ->get();

        return view('admin.hr.leave-requests', compact('requests'));
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if (! $leaveRequest->awaitsDecision()) {
            return back()->with('error', 'This request was already processed.');
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $this->leaveRequestService->approve(
            $leaveRequest,
            auth('admin')->id(),
            $validated['admin_notes'] ?? null
        );

        return $this->backWithEmailStatus(
            'Leave request approved. Opening Application for Leave Excel…',
            ['auto_download_excel' => route('admin.hr.leave.print', $leaveRequest)]
        );
    }

    public function printLeave(LeaveRequest $leaveRequest): BinaryFileResponse
    {
        abort_unless($leaveRequest->status === 'approved', 403);

        $path = $this->leaveApplicationExport->generateExcel($leaveRequest);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function printLeavePdf(LeaveRequest $leaveRequest): BinaryFileResponse|RedirectResponse
    {
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

    public function rejectLeave(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if (! $leaveRequest->awaitsDecision()) {
            return back()->with('error', 'This request was already processed.');
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $this->leaveRequestService->reject(
            $leaveRequest,
            auth('admin')->id(),
            $validated['admin_notes'] ?? null
        );

        return $this->backWithEmailStatus('Leave request rejected.');
    }

    public function markLeavePendingDivision(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Only a request pending at the school can be endorsed to the Division Office.');
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $this->leaveRequestService->markPendingDivision(
            $leaveRequest,
            auth('admin')->id(),
            $validated['admin_notes'] ?? null
        );

        return $this->backWithEmailStatus('Leave request is pending approval of the Division Office.');
    }

    public function locatorRequests(): View
    {
        $requests = LocatorRequest::with(['staffUser', 'personnel', 'reviewer'])
            ->latest()
            ->get();

        return view('admin.hr.locator-requests', compact('requests'));
    }

    public function printLocator(LocatorRequest $locatorRequest): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($locatorRequest->status === 'approved', 403);

        try {
            return $this->travelAuthorityWord->download($locatorRequest);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.hr.locator-requests')
                ->with('error', 'Could not print Locator Slip: '.$e->getMessage());
        }
    }

    public function approveLocator(Request $request, LocatorRequest $locatorRequest): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if ($locatorRequest->status !== 'pending') {
            return back()->with('error', 'This locator slip was already processed.');
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $this->locatorRequestService->approve(
            $locatorRequest,
            auth('admin')->id(),
            $validated['admin_notes'] ?? null
        );

        return $this->backWithEmailStatus(
            'Locator slip approved. Opening MS Word for printing…',
            ['auto_download_word' => route('admin.hr.locator.print', $locatorRequest)]
        );
    }

    public function rejectLocator(Request $request, LocatorRequest $locatorRequest): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if ($locatorRequest->status !== 'pending') {
            return back()->with('error', 'This locator slip was already processed.');
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $this->locatorRequestService->reject(
            $locatorRequest,
            auth('admin')->id(),
            $validated['admin_notes'] ?? null
        );

        return $this->backWithEmailStatus('Locator slip rejected.');
    }

    public function travelOrders(): View
    {
        $orders = TravelOrder::with(['staffUser', 'personnel', 'reviewer'])
            ->latest()
            ->get();

        return view('admin.hr.travel-orders', compact('orders'));
    }

    public function printTravelOrder(TravelOrder $travelOrder)
    {
        abort_unless($travelOrder->status === 'approved', 403);

        try {
            return $this->travelOrderWord->download($travelOrder);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.hr.travel-orders')
                ->with('error', 'Could not print Travel Order: '.$e->getMessage());
        }
    }

    public function approveTravelOrder(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if ($travelOrder->status !== 'pending') {
            return back()->with('error', 'This travel order was already processed.');
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $this->travelOrderService->approve(
            $travelOrder,
            auth('admin')->id(),
            $validated['admin_notes'] ?? null
        );

        return $this->backWithEmailStatus(
            'Travel order approved. Opening MS Word for printing…',
            ['auto_download_word' => route('admin.hr.travel-orders.print', $travelOrder)]
        );
    }

    public function rejectTravelOrder(Request $request, TravelOrder $travelOrder): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        if ($travelOrder->status !== 'pending') {
            return back()->with('error', 'This travel order was already processed.');
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $this->travelOrderService->reject(
            $travelOrder,
            auth('admin')->id(),
            $validated['admin_notes'] ?? null
        );

        return $this->backWithEmailStatus('Travel order rejected.');
    }

    public function emailChangeRequests(): View
    {
        $requests = EmailChangeRequest::with(['staffUser', 'reviewer'])
            ->latest()
            ->get();

        return view('admin.hr.email-changes', compact('requests'));
    }

    public function approveEmailChange(Request $request, EmailChangeRequest $emailChangeRequest): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->emailChangeRequests->approve(
                $emailChangeRequest,
                auth('admin')->id(),
                $validated['admin_notes'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->backWithEmailStatus('Gmail change approved. Employee login email was updated.');
    }

    public function rejectEmailChange(Request $request, EmailChangeRequest $emailChangeRequest): RedirectResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->emailChangeRequests->reject(
                $emailChangeRequest,
                auth('admin')->id(),
                $validated['admin_notes'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->backWithEmailStatus('Gmail change rejected.');
    }
}
