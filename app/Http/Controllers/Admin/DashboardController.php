<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\DeviceHeartbeat;
use App\Models\LeaveRecord;
use App\Models\Personnel;
use App\Models\Schedule;
use App\Models\ScheduleOverride;
use App\Services\DailyStatusService;
use App\Services\DashboardAnalyticsService;
use App\Services\DtrExportService;
use App\Services\ReportExportService;
use App\Services\ScheduleWeekService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected ReportExportService $reportService,
        protected DailyStatusService $dailyStatusService,
        protected DtrExportService $dtrService,
        protected DashboardAnalyticsService $analyticsService,
        protected ScheduleWeekService $scheduleWeek
    ) {}

    public function index(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $report = $this->reportService->buildDailyReport($date);
        $device = DeviceHeartbeat::firstOrCreate(['device_name' => 'raspberry-pi-kiosk']);

        return view('admin.dashboard', [
            'date' => $date,
            'counts' => $report['counts'],
            'records' => $report['records'],
            'device' => $device,
            'trend' => $this->analyticsService->monthlyTrend(),
            'monthSummary' => $this->analyticsService->monthSummary(),
        ]);
    }

    public function kioskStatus(): \Illuminate\Http\JsonResponse
    {
        $device = DeviceHeartbeat::firstOrCreate(['device_name' => 'raspberry-pi-kiosk']);

        return response()->json($device->dashboardStatus());
    }

    public function personnel(): View
    {
        $personnel = Personnel::orderBy('name')->get();

        return view('admin.personnel.index', compact('personnel'));
    }

    public function uploadFaces(Request $request, Personnel $personnel): RedirectResponse
    {
        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'file|mimes:jpeg,jpg,png|max:5120',
        ]);

        $dir = (string) $personnel->id;
        Storage::disk('faces')->makeDirectory($dir);

        $saved = 0;
        foreach ($request->file('images') as $index => $file) {
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'_'.time()."_{$index}.".$file->getClientOriginalExtension();
            $file->storeAs($dir, $filename, 'faces');
            $saved++;
        }

        $personnel->update(['face_dataset_dir' => storage_path('app/known_faces/'.$personnel->id)]);

        return back()->with('success', $saved.' face photo(s) saved for '.$personnel->name.'. The Pi will pick them up shortly.');
    }

    public function clearFaces(Personnel $personnel): RedirectResponse
    {
        $dir = (string) $personnel->id;
        if (Storage::disk('faces')->exists($dir)) {
            Storage::disk('faces')->deleteDirectory($dir);
        }
        Storage::disk('faces')->makeDirectory($dir);
        $personnel->update(['face_dataset_dir' => storage_path('app/known_faces/'.$personnel->id)]);

        return back()->with('success', 'Face photos removed for '.$personnel->name.'. Upload new photos, then wait about a minute for the Pi.');
    }

    public function logs(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $logs = AttendanceLog::with('personnel')
            ->whereDate('date', $date)
            ->orderBy('time')
            ->get();

        return view('admin.logs', compact('date', 'logs'));
    }

    public function leave(): View
    {
        $personnel = Personnel::orderBy('name')->get();
        $records = LeaveRecord::with('personnel')->latest()->limit(50)->get();

        return view('admin.leave', compact('personnel', 'records'));
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'personnel_id' => 'required|exists:personnel,id',
            'date' => 'required|date',
            'leave_type' => 'required|in:Leave,Travel',
            'reason' => 'nullable|string|max:500',
        ]);

        LeaveRecord::updateOrCreate(
            [
                'personnel_id' => $validated['personnel_id'],
                'date' => $validated['date'],
                'leave_type' => $validated['leave_type'],
            ],
            [
                'reason' => $validated['reason'] ?? null,
                'created_by' => Auth::guard('admin')->id(),
            ]
        );

        $this->dailyStatusService->computeAndPersist((int) $validated['personnel_id'], $validated['date']);

        return back()->with('success', 'Leave/travel record saved.');
    }

    public function schedule(): View
    {
        $personnel = Personnel::orderBy('name')->get();
        $schedules = Schedule::with('personnel')->orderBy('personnel_id')->orderBy('day_of_week')->get();
        $overrides = ScheduleOverride::with('personnel')->latest()->limit(50)->get();
        $unscheduledCount = count($this->scheduleWeek->unscheduledPersonnelIds());
        $personnelWithSchedules = Personnel::query()
            ->whereHas('schedules')
            ->orderBy('name')
            ->get();

        return view('admin.schedule', compact(
            'personnel',
            'schedules',
            'overrides',
            'unscheduledCount',
            'personnelWithSchedules'
        ));
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'personnel_ids' => 'required|array|min:1',
            'personnel_ids.*' => 'integer|exists:personnel,id',
            'days' => 'required|array|min:1',
            'days.*' => 'integer|min:0|max:6',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'subject' => 'required|string|max:255',
        ]);

        $saved = $this->scheduleWeek->assignWeek(
            $validated['personnel_ids'],
            $validated['days'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['subject']
        );

        $people = count($validated['personnel_ids']);
        $days = count($validated['days']);

        return back()->with('success', "Saved {$days} weekday".($days === 1 ? '' : 's')." for {$people} employee".($people === 1 ? '' : 's')." ({$saved} rows).");
    }

    public function applyDefaultSchedule(): RedirectResponse
    {
        $saved = $this->scheduleWeek->applySchoolDefaultToUnscheduled();

        if ($saved === 0) {
            return back()->with('success', 'Everyone already has a weekly schedule.');
        }

        return back()->with('success', 'Applied Mon–Fri 7:30 AM–5:00 PM Regular duty to employees who had no schedule.');
    }

    public function copySchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_personnel_id' => 'required|exists:personnel,id',
            'copy_to' => 'required|in:unscheduled,selected',
            'personnel_ids' => 'required_if:copy_to,selected|array',
            'personnel_ids.*' => 'integer|exists:personnel,id',
        ]);

        $targetIds = $validated['copy_to'] === 'unscheduled'
            ? $this->scheduleWeek->unscheduledPersonnelIds()
            : ($validated['personnel_ids'] ?? []);

        try {
            $saved = $this->scheduleWeek->copyWeek((int) $validated['source_personnel_id'], $targetIds);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($saved === 0) {
            return back()->with('error', 'Choose at least one other employee to copy to.');
        }

        return back()->with('success', 'Copied the weekly schedule to the selected employees.');
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'personnel_id' => 'required|exists:personnel,id',
            'date' => 'required|date',
            'override_type' => 'required|in:Class,Off-duty',
            'start_time' => 'nullable|required_if:override_type,Class',
            'end_time' => 'nullable|required_if:override_type,Class',
            'subject' => 'nullable|string|max:255',
        ]);

        ScheduleOverride::updateOrCreate(
            ['personnel_id' => $validated['personnel_id'], 'date' => $validated['date']],
            [
                'override_type' => $validated['override_type'],
                'start_time' => isset($validated['start_time']) ? date('H:i:s', strtotime($validated['start_time'])) : null,
                'end_time' => isset($validated['end_time']) ? date('H:i:s', strtotime($validated['end_time'])) : null,
                'subject' => $validated['subject'] ?? null,
            ]
        );

        $this->dailyStatusService->computeAndPersist((int) $validated['personnel_id'], $validated['date']);

        return back()->with('success', 'Schedule override saved.');
    }

    public function dtr(): View
    {
        $personnel = Personnel::orderBy('name')->get();

        return view('admin.dtr', compact('personnel'));
    }

    public function reports(Request $request): View
    {
        [$from, $to] = $this->analyticsRange($request, false);

        return view('admin.reports', [
            'from' => $from,
            'to' => $to,
            'analytics' => $this->analyticsService->officeReport($from, $to),
        ]);
    }

    public function printOfficeAnalytics(Request $request): View
    {
        [$from, $to] = $this->analyticsRange($request, true);
        $report = (string) $request->query('report', 'summary');
        $format = (string) $request->query('format', 'table');
        $reports = [
            'summary' => 'Attendance summary',
            'trend' => 'Daily attendance trend',
            'personnel' => 'Personnel attendance',
            'groups' => 'Teaching and non-teaching',
        ];

        if (! isset($reports[$report]) || ! in_array($format, ['graph', 'table'], true)) {
            throw ValidationException::withMessages([
                'report' => 'Choose a report and print it as a graph or a table.',
            ]);
        }

        return view('admin.analytics-print', [
            'analytics' => $this->analyticsService->officeReport($from, $to),
            'report' => $report,
            'reportTitle' => $reports[$report],
            'format' => $format,
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function analyticsRange(Request $request, bool $required): array
    {
        $from = (string) $request->input('from', now()->startOfMonth()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        if ($required || $request->has('from') || $request->has('to')) {
            $validated = $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);
            $from = $validated['from'];
            $to = $validated['to'];
        }

        $days = Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()) + 1;
        $max = (int) config('smartdtr.max_report_range_days', 31);
        if ($days > $max) {
            throw ValidationException::withMessages([
                'to' => "Date range cannot exceed {$max} days.",
            ]);
        }

        return [$from, $to];
    }

    public function downloadDtrPdf(Request $request)
    {
        $validated = $request->validate([
            'personnel_id' => 'required|exists:personnel,id',
            'month' => 'required|date_format:Y-m',
        ]);

        $personnel = Personnel::findOrFail($validated['personnel_id']);
        $month = $validated['month'];
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $personnel->name);

        try {
            $path = $this->dtrService->generateOfficialPdf($personnel, $month);

            return response()->download($path, 'DTR_'.$safeName.'_'.$month.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.dtr')
                ->with('error', 'DTR PDF failed: '.$e->getMessage());
        }
    }

    public function downloadBulkDtr(Request $request)
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit', '512M');

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $month = $validated['month'];

        try {
            $path = $this->dtrService->generateBulkPdf($month);

            return response()->download($path, 'DTR_ALL_'.$month.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.dtr')
                ->with('error', 'DTR download failed: '.$e->getMessage());
        }
    }

    public function downloadReportPdf(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $matrix = $this->reportService->buildWideAttendanceMatrix($validated['from'], $validated['to']);
        $layout = $this->reportService->buildPrintableAttendanceLayout(
            $matrix,
            $validated['from'],
            $validated['to']
        );
        $pdf = Pdf::loadView('reports.range-pdf', [
            'layout' => $layout,
            'from' => $validated['from'],
            'to' => $validated['to'],
        ])->setPaper('legal', 'landscape');

        return $pdf->download('report_'.$validated['from'].'_'.$validated['to'].'.pdf');
    }
}
