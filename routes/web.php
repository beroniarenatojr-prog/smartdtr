<?php

use App\Http\Controllers\Admin\AttendanceCorrectionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaceEnrollmentController;
use App\Http\Controllers\Admin\File201Controller;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LeaveCardController;
use App\Http\Controllers\Admin\HrController;
use App\Http\Controllers\Admin\PersonnelManageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RsmiController;
use App\Http\Controllers\Admin\RisController;
use App\Http\Controllers\Admin\SignatorySettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Staff\FaceEnrollmentController as StaffFaceEnrollmentController;
use App\Http\Controllers\Staff\File201Controller as StaffFile201Controller;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    if (auth('staff')->check()) {
        return redirect()->route('staff.dashboard');
    }

    return view('home');
})->name('home');

Route::redirect('/admin', '/admin/dashboard');

// Password reset (guest)
Route::get('/forgot-password', fn () => redirect()->route('password.forgot', 'admin'))->name('password.forgot.redirect');
Route::get('/password/forgot/{guard}', [PasswordController::class, 'showForgot'])->name('password.forgot');
Route::post('/password/forgot/{guard}', [PasswordController::class, 'sendResetLink'])->name('password.email');
Route::get('/password/reset/admin', [PasswordController::class, 'showReset'])->name('password.reset.admin');
Route::get('/staff/password/reset', [PasswordController::class, 'showReset'])->name('password.reset.staff');
Route::post('/password/reset/{guard}', [PasswordController::class, 'reset'])->name('password.update');

// Admin auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Staff auth
Route::get('/staff/login', [StaffAuthController::class, 'showLogin'])->name('staff.login');
Route::post('/staff/login', [StaffAuthController::class, 'login']);
Route::get('/staff/register', [StaffAuthController::class, 'showRegister'])->name('staff.register');
Route::post('/staff/register', [StaffAuthController::class, 'register']);
Route::get('/staff/verify-email', [StaffAuthController::class, 'showVerifyEmail'])->name('staff.verify-email');
Route::post('/staff/verify-email', [StaffAuthController::class, 'verifyEmail']);
Route::post('/staff/verify-email/resend', [StaffAuthController::class, 'resendVerificationCode'])->name('staff.verify-email.resend');
Route::post('/staff/logout', [StaffAuthController::class, 'logout'])->name('staff.logout');

// Staff portal
Route::middleware('auth.staff')->prefix('staff')->name('staff.')->group(function () {
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
    Route::middleware('staff.full')->group(function () {
        Route::get('/leave', [StaffDashboardController::class, 'leave'])->name('leave');
        Route::post('/leave', [StaffDashboardController::class, 'storeLeave'])->name('leave.store');
        Route::get('/leave/{leaveRequest}/print', [StaffDashboardController::class, 'printLeave'])->name('leave.print');
        Route::get('/leave/{leaveRequest}/pdf', [StaffDashboardController::class, 'printLeavePdf'])->name('leave.pdf');
        Route::get('/locator', [StaffDashboardController::class, 'locator'])->name('locator');
        Route::post('/locator', [StaffDashboardController::class, 'storeLocator'])->name('locator.store');
        Route::get('/locator/{locatorRequest}/print', [StaffDashboardController::class, 'printLocator'])->name('locator.print');
        Route::get('/travel-orders', [StaffDashboardController::class, 'travelOrders'])->name('travel-orders');
        Route::post('/travel-orders', [StaffDashboardController::class, 'storeTravelOrder'])->name('travel-orders.store');
        Route::get('/travel-orders/{travelOrder}/print', [StaffDashboardController::class, 'printTravelOrder'])->name('travel-orders.print');
        Route::get('/201-files', [StaffFile201Controller::class, 'index'])->name('files201.index');
        Route::get('/201-files/print', [StaffFile201Controller::class, 'print'])->name('files201.print');
        Route::post('/201-files/clearance', [StaffFile201Controller::class, 'storeClearance'])->name('files201.clearance.store');
        Route::get('/201-files/clearance/{clearance}/print', [StaffFile201Controller::class, 'printClearance'])->name('files201.clearance.print');
        Route::post('/201-files/work-experience', [StaffFile201Controller::class, 'storeWorkExperience'])->name('files201.work-experience.store');
        Route::get('/201-files/work-experience/print', [StaffFile201Controller::class, 'printWorkExperience'])->name('files201.work-experience.print');
        Route::post('/201-files/position-description', [StaffFile201Controller::class, 'storePositionDescription'])->name('files201.position-description.store');
        Route::get('/201-files/position-description/print', [StaffFile201Controller::class, 'printPositionDescription'])->name('files201.position-description.print');
        Route::post('/201-files/medical-certificate', [StaffFile201Controller::class, 'storeMedicalCertificate'])->name('files201.medical-certificate.store');
        Route::get('/201-files/medical-certificate/print', [StaffFile201Controller::class, 'printMedicalCertificate'])->name('files201.medical-certificate.print');
        Route::post('/201-files/personal-data-sheet', [StaffFile201Controller::class, 'storePersonalDataSheet'])->name('files201.personal-data-sheet.store');
        Route::get('/201-files/personal-data-sheet/print', [StaffFile201Controller::class, 'printPersonalDataSheet'])->name('files201.personal-data-sheet.print');
        Route::post('/201-files/service-record', [StaffFile201Controller::class, 'storeServiceRecord'])->name('files201.service-record.store');
        Route::get('/201-files/service-record/print', [StaffFile201Controller::class, 'printServiceRecord'])->name('files201.service-record.print');
        Route::post('/201-files/cto', [StaffFile201Controller::class, 'storeCompensatoryTimeOff'])->name('files201.cto.store');
        Route::get('/201-files/cto/print', [StaffFile201Controller::class, 'printCompensatoryTimeOff'])->name('files201.cto.print');
        Route::post('/201-files', [StaffFile201Controller::class, 'store'])->name('files201.store');
        Route::get('/201-files/{file}/download', [StaffFile201Controller::class, 'download'])->name('files201.download');
        Route::delete('/201-files/{file}', [StaffFile201Controller::class, 'destroy'])->name('files201.destroy');
        Route::get('/schedule', [StaffDashboardController::class, 'schedule'])->name('schedule');
        Route::post('/schedule', [StaffDashboardController::class, 'storeSchedule'])->name('schedule.store');
        Route::delete('/schedule/{schedule}', [StaffDashboardController::class, 'destroySchedule'])->name('schedule.destroy');
        Route::post('/schedule/override', [StaffDashboardController::class, 'storeOverride'])->name('schedule.override');
        Route::delete('/schedule/override/{override}', [StaffDashboardController::class, 'destroyOverride'])->name('schedule.override.destroy');
    });
    Route::get('/attendance', [StaffDashboardController::class, 'attendance'])->name('attendance');
    Route::get('/faces', [StaffFaceEnrollmentController::class, 'show'])->name('faces');
    Route::post('/faces', [StaffFaceEnrollmentController::class, 'store'])->name('faces.store');
    Route::get('/profile', [StaffDashboardController::class, 'profile'])->name('profile');
    Route::post('/profile/email-change', [StaffDashboardController::class, 'requestEmailChange'])->name('profile.email.request');
    Route::post('/avatar', [AvatarController::class, 'upload'])->name('avatar.upload');
    Route::delete('/avatar', [AvatarController::class, 'remove'])->name('avatar.remove');
    Route::get('/password', [PasswordController::class, 'showChangePassword'])->defaults('guard', 'staff')->name('password');
    Route::post('/password', [PasswordController::class, 'changePassword'])->defaults('guard', 'staff')->name('password.change');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

// Admin portal
Route::middleware('auth:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('admin.permission:dashboard')->name('dashboard');
    Route::get('/dashboard/kiosk-status', [DashboardController::class, 'kioskStatus'])->middleware('admin.permission:dashboard')->name('dashboard.kiosk-status');

    Route::get('/personnel', [DashboardController::class, 'personnel'])->middleware('admin.permission:personnel')->name('personnel');
    Route::post('/personnel/{personnel}/faces/clear', [DashboardController::class, 'clearFaces'])->middleware('admin.permission:personnel')->name('personnel.faces.clear');
    Route::get('/personnel/{personnel}/edit', [PersonnelManageController::class, 'edit'])->middleware('admin.permission:personnel')->name('personnel.edit');
    Route::put('/personnel/{personnel}', [PersonnelManageController::class, 'update'])->middleware('admin.permission:personnel')->name('personnel.update');
    Route::post('/personnel/{personnel}/deactivate', [PersonnelManageController::class, 'deactivate'])->middleware('admin.permission:personnel')->name('personnel.deactivate');
    Route::post('/personnel/{personnel}/activate', [PersonnelManageController::class, 'activate'])->middleware('admin.permission:personnel')->name('personnel.activate');

    Route::get('/hr/faces', [FaceEnrollmentController::class, 'index'])->middleware('admin.permission:hr')->name('hr.faces');
    Route::get('/hr/faces/{personnel}/enroll', [FaceEnrollmentController::class, 'enroll'])->middleware('admin.permission:hr')->name('hr.faces.enroll');
    Route::post('/hr/faces/{personnel}/enroll', [FaceEnrollmentController::class, 'store'])->middleware('admin.permission:hr')->name('hr.faces.store');

    Route::get('/hr/staff', [HrController::class, 'staffAccounts'])->middleware('admin.permission:hr')->name('hr.staff');
    Route::post('/hr/staff/{staffUser}/activate', [HrController::class, 'activateStaff'])->middleware('admin.permission:hr')->name('hr.staff.activate');
    Route::post('/hr/staff/{staffUser}/reject', [HrController::class, 'rejectStaff'])->middleware('admin.permission:hr')->name('hr.staff.reject');
    Route::get('/hr/email-changes', [HrController::class, 'emailChangeRequests'])->middleware('admin.permission:hr')->name('hr.email-changes');
    Route::post('/hr/email-changes/{emailChangeRequest}/approve', [HrController::class, 'approveEmailChange'])->middleware('admin.permission:hr')->name('hr.email-changes.approve');
    Route::post('/hr/email-changes/{emailChangeRequest}/reject', [HrController::class, 'rejectEmailChange'])->middleware('admin.permission:hr')->name('hr.email-changes.reject');
    Route::get('/hr/leave-requests', [HrController::class, 'leaveRequests'])->middleware('admin.permission:hr')->name('hr.leave-requests');
    Route::get('/leave-cards', [LeaveCardController::class, 'index'])->middleware('admin.permission:hr')->name('leave-cards.index');
    Route::get('/leave-cards/{personnel}/print', [LeaveCardController::class, 'print'])->middleware('admin.permission:hr')->name('leave-cards.print');
    Route::get('/leave-cards/{personnel}/excel', [LeaveCardController::class, 'excel'])->middleware('admin.permission:hr')->name('leave-cards.excel');
    Route::get('/leave-cards/{personnel}', [LeaveCardController::class, 'show'])->middleware('admin.permission:hr')->name('leave-cards.show');
    Route::put('/leave-cards/{personnel}', [LeaveCardController::class, 'update'])->middleware('admin.permission:hr')->name('leave-cards.update');
    Route::post('/hr/leave-requests/{leaveRequest}/approve', [HrController::class, 'approveLeave'])->middleware('admin.permission:hr')->name('hr.leave.approve');
    Route::post('/hr/leave-requests/{leaveRequest}/division', [HrController::class, 'markLeavePendingDivision'])->middleware('admin.permission:hr')->name('hr.leave.division');
    Route::post('/hr/leave-requests/{leaveRequest}/reject', [HrController::class, 'rejectLeave'])->middleware('admin.permission:hr')->name('hr.leave.reject');
    Route::get('/hr/leave-requests/{leaveRequest}/print', [HrController::class, 'printLeave'])->middleware('admin.permission:hr')->name('hr.leave.print');
    Route::get('/hr/leave-requests/{leaveRequest}/pdf', [HrController::class, 'printLeavePdf'])->middleware('admin.permission:hr')->name('hr.leave.pdf');
    Route::get('/hr/locator-requests', [HrController::class, 'locatorRequests'])->middleware('admin.permission:hr')->name('hr.locator-requests');
    Route::post('/hr/locator-requests/{locatorRequest}/approve', [HrController::class, 'approveLocator'])->middleware('admin.permission:hr')->name('hr.locator.approve');
    Route::post('/hr/locator-requests/{locatorRequest}/reject', [HrController::class, 'rejectLocator'])->middleware('admin.permission:hr')->name('hr.locator.reject');
    Route::get('/hr/locator-requests/{locatorRequest}/print', [HrController::class, 'printLocator'])->middleware('admin.permission:hr')->name('hr.locator.print');
    Route::get('/hr/travel-orders', [HrController::class, 'travelOrders'])->middleware('admin.permission:hr')->name('hr.travel-orders');
    Route::post('/hr/travel-orders/{travelOrder}/approve', [HrController::class, 'approveTravelOrder'])->middleware('admin.permission:hr')->name('hr.travel-orders.approve');
    Route::post('/hr/travel-orders/{travelOrder}/reject', [HrController::class, 'rejectTravelOrder'])->middleware('admin.permission:hr')->name('hr.travel-orders.reject');
    Route::get('/hr/travel-orders/{travelOrder}/print', [HrController::class, 'printTravelOrder'])->middleware('admin.permission:hr')->name('hr.travel-orders.print');

    Route::get('/201-files', [File201Controller::class, 'index'])->middleware('admin.permission:hr')->name('files201.index');
    Route::post('/201-files/categories', [File201Controller::class, 'storeCategory'])->middleware('admin.permission:hr')->name('files201.categories.store');
    Route::get('/201-files/{personnel}/clearance/{clearance}/print', [File201Controller::class, 'printClearance'])->middleware('admin.permission:hr')->name('files201.clearance.print');
    Route::get('/201-files/{personnel}/work-experience/print', [File201Controller::class, 'printWorkExperience'])->middleware('admin.permission:hr')->name('files201.work-experience.print');
    Route::get('/201-files/{personnel}/position-description/print', [File201Controller::class, 'printPositionDescription'])->middleware('admin.permission:hr')->name('files201.position-description.print');
    Route::get('/201-files/{personnel}/medical-certificate/print', [File201Controller::class, 'printMedicalCertificate'])->middleware('admin.permission:hr')->name('files201.medical-certificate.print');
    Route::get('/201-files/{personnel}/personal-data-sheet/print', [File201Controller::class, 'printPersonalDataSheet'])->middleware('admin.permission:hr')->name('files201.personal-data-sheet.print');
    Route::get('/201-files/{personnel}/service-record/print', [File201Controller::class, 'printServiceRecord'])->middleware('admin.permission:hr')->name('files201.service-record.print');
    Route::get('/201-files/{personnel}/cto/print', [File201Controller::class, 'printCompensatoryTimeOff'])->middleware('admin.permission:hr')->name('files201.cto.print');
    Route::get('/201-files/{personnel}/print', [File201Controller::class, 'print'])->middleware('admin.permission:hr')->name('files201.print');
    Route::get('/201-files/{personnel}', [File201Controller::class, 'show'])->middleware('admin.permission:hr')->name('files201.show');
    Route::post('/201-files/{personnel}', [File201Controller::class, 'store'])->middleware('admin.permission:hr')->name('files201.store');
    Route::get('/201-files/{personnel}/{file}/download', [File201Controller::class, 'download'])->middleware('admin.permission:hr')->name('files201.download');
    Route::delete('/201-files/{personnel}/{file}', [File201Controller::class, 'destroy'])->middleware('admin.permission:hr')->name('files201.destroy');

    Route::get('/logs', [DashboardController::class, 'logs'])->middleware('admin.permission:logs')->name('logs');
    Route::get('/leave', [DashboardController::class, 'leave'])->middleware('admin.permission:leave')->name('leave');
    Route::post('/leave', [DashboardController::class, 'storeLeave'])->middleware('admin.permission:leave')->name('leave.store');

    Route::get('/schedule', [DashboardController::class, 'schedule'])->middleware('admin.permission:schedule')->name('schedule');
    Route::post('/schedule', [DashboardController::class, 'storeSchedule'])->middleware('admin.permission:schedule')->name('schedule.store');
    Route::post('/schedule/apply-default', [DashboardController::class, 'applyDefaultSchedule'])->middleware('admin.permission:schedule')->name('schedule.apply-default');
    Route::post('/schedule/copy', [DashboardController::class, 'copySchedule'])->middleware('admin.permission:schedule')->name('schedule.copy');
    Route::post('/schedule/override', [DashboardController::class, 'storeOverride'])->middleware('admin.permission:schedule')->name('schedule.override');

    Route::get('/holidays', [HolidayController::class, 'index'])->middleware('admin.permission:holidays')->name('holidays');
    Route::post('/holidays', [HolidayController::class, 'store'])->middleware('admin.permission:holidays')->name('holidays.store');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->middleware('admin.permission:holidays')->name('holidays.destroy');

    Route::get('/attendance/correction', [AttendanceCorrectionController::class, 'index'])->middleware('admin.permission:attendance.correct')->name('attendance.correction');
    Route::post('/attendance/correction', [AttendanceCorrectionController::class, 'store'])->middleware('admin.permission:attendance.correct')->name('attendance.correction.store');

    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware('admin.permission:audit')->name('audit');

    Route::get('/dtr', [DashboardController::class, 'dtr'])->middleware('admin.permission:dtr')->name('dtr');
    Route::get('/reports/dtr/pdf', [DashboardController::class, 'downloadDtrPdf'])->middleware('admin.permission:dtr')->name('dtr.pdf');
    Route::get('/reports/dtr/bulk', [DashboardController::class, 'downloadBulkDtr'])->middleware('admin.permission:dtr')->name('dtr.bulk');

    Route::get('/reports', [DashboardController::class, 'reports'])->middleware('admin.permission:reports')->name('reports');
    Route::get('/reports/analytics/print', [DashboardController::class, 'printOfficeAnalytics'])->middleware('admin.permission:reports')->name('reports.analytics');
    Route::get('/reports/export/pdf', [DashboardController::class, 'downloadReportPdf'])->middleware('admin.permission:reports')->name('reports.pdf');

    Route::get('/settings/signatories', [SignatorySettingsController::class, 'index'])
        ->middleware('admin.permission:settings')
        ->name('settings.signatories');
    Route::put('/settings/signatories', [SignatorySettingsController::class, 'update'])
        ->middleware('admin.permission:settings')
        ->name('settings.signatories.update');
    Route::delete('/settings/signatories/{signatory}', [SignatorySettingsController::class, 'destroy'])
        ->middleware('admin.permission:settings')
        ->name('settings.signatories.destroy');
    Route::post('/settings/signatories/reset', [SignatorySettingsController::class, 'reset'])
        ->middleware('admin.permission:settings')
        ->name('settings.signatories.reset');

    Route::get('/rsmi', [RsmiController::class, 'index'])->middleware('admin.permission:reports')->name('rsmi.index');
    Route::get('/rsmi/create', [RsmiController::class, 'create'])->middleware('admin.permission:reports')->name('rsmi.create');
    Route::post('/rsmi', [RsmiController::class, 'store'])->middleware('admin.permission:reports')->name('rsmi.store');
    Route::get('/rsmi/{rsmiReport}', [RsmiController::class, 'show'])->middleware('admin.permission:reports')->name('rsmi.show');
    Route::put('/rsmi/{rsmiReport}', [RsmiController::class, 'update'])->middleware('admin.permission:reports')->name('rsmi.update');
    Route::delete('/rsmi/{rsmiReport}', [RsmiController::class, 'destroy'])->middleware('admin.permission:reports')->name('rsmi.destroy');
    Route::post('/rsmi/{rsmiReport}/items', [RsmiController::class, 'storeItem'])->middleware('admin.permission:reports')->name('rsmi.items.store');
    Route::post('/rsmi/{rsmiReport}/import-ris', [RsmiController::class, 'importFromRis'])->middleware('admin.permission:reports')->name('rsmi.import-ris');
    Route::delete('/rsmi/{rsmiReport}/items/{item}', [RsmiController::class, 'destroyItem'])->middleware('admin.permission:reports')->name('rsmi.items.destroy');
    Route::get('/rsmi/{rsmiReport}/export', [RsmiController::class, 'export'])->middleware('admin.permission:reports')->name('rsmi.export');

    Route::get('/ris', [RisController::class, 'index'])->middleware('admin.permission:reports')->name('ris.index');
    Route::get('/ris/create', [RisController::class, 'create'])->middleware('admin.permission:reports')->name('ris.create');
    Route::post('/ris', [RisController::class, 'store'])->middleware('admin.permission:reports')->name('ris.store');
    Route::get('/ris/{ris}', [RisController::class, 'show'])->middleware('admin.permission:reports')->name('ris.show');
    Route::put('/ris/{ris}', [RisController::class, 'update'])->middleware('admin.permission:reports')->name('ris.update');
    Route::delete('/ris/{ris}', [RisController::class, 'destroy'])->middleware('admin.permission:reports')->name('ris.destroy');
    Route::post('/ris/{ris}/items', [RisController::class, 'storeItem'])->middleware('admin.permission:reports')->name('ris.items.store');
    Route::post('/ris/{ris}/send-to-rsmi', [RisController::class, 'sendToRsmi'])->middleware('admin.permission:reports')->name('ris.send-to-rsmi');
    Route::delete('/ris/{ris}/items/{item}', [RisController::class, 'destroyItem'])->middleware('admin.permission:reports')->name('ris.items.destroy');
    Route::get('/ris/{ris}/export', [RisController::class, 'export'])->middleware('admin.permission:reports')->name('ris.export');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/avatar', [AvatarController::class, 'upload'])->name('avatar.upload');
    Route::delete('/avatar', [AvatarController::class, 'remove'])->name('avatar.remove');
    Route::get('/password', [PasswordController::class, 'showChangePassword'])->defaults('guard', 'admin')->name('password');
    Route::post('/password', [PasswordController::class, 'changePassword'])->defaults('guard', 'admin')->name('password.change');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});
