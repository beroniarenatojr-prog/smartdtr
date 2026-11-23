<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\DocumentSignatory;
use App\Models\Holiday;
use App\Models\LocatorRequest;
use App\Models\Personnel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use setasign\Fpdi\Fpdi;
use DOMDocument;
use DOMXPath;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\Process\Process;

class DtrExportService
{
    /**
     * Left two Form 48 copies on landscape Folio (long bond). Copies 3–4 stay on the
     * sheet but are wiped so print uses only the left half of the page at original size.
     * Day 1 is row 12 (row = 11 + day).
     *
     * @var list<array<string, string>>
     */
    protected array $formCopies = [
        // Blank template: name on row 3, "(Name)" stays on row 4.
        [
            'name' => 'A3',
            'name_label' => 'A4',
            'month' => 'A5',
            'hours_line1' => 'A6',
            'hours_line2' => 'A7',
            'day' => 'A',
            'am_arrival' => 'B',
            'am_departure' => 'C',
            'pm_arrival' => 'D',
            'pm_departure' => 'E',
            'ot_arrival' => 'F',
            'ot_departure' => 'G',
            'signature_name' => 'A48',
            'verifier_name' => 'A50',
        ],
        [
            'name' => 'I3',
            'name_label' => 'I4',
            'month' => 'I5',
            'hours_line1' => 'I6',
            'hours_line2' => 'I7',
            'day' => 'I',
            'am_arrival' => 'J',
            'am_departure' => 'K',
            'pm_arrival' => 'L',
            'pm_departure' => 'M',
            'ot_arrival' => 'N',
            'ot_departure' => 'O',
            'signature_name' => 'I48',
            'verifier_name' => 'I50',
        ],
    ];

    protected int $dayRowOffset = 11;

    /**
     * Map the day's scans in order onto Civil Service Form 48.
     * 1 arrival AM, 2 departure AM, 3 arrival PM, 4 departure PM,
     * 5 arrival overtime, and the last scan is departure overtime.
     *
     * @param  array<int, string>  $times  H:i:s format
     * @return array{am_arrival:?string,am_departure:?string,pm_arrival:?string,pm_departure:?string,ot_arrival:?string,ot_departure:?string}
     */
    public function mapScansToDtr(array $times): array
    {
        $slots = ['am_arrival', 'am_departure', 'pm_arrival', 'pm_departure', 'ot_arrival', 'ot_departure'];
        $result = array_fill_keys($slots, null);
        $times = array_values($times);
        $count = count($times);

        if ($count === 0) {
            return $result;
        }

        $filled = min($count, 5);
        for ($i = 0; $i < $filled; $i++) {
            $result[$slots[$i]] = $times[$i];
        }

        if ($count >= 6) {
            $result['ot_departure'] = $times[$count - 1];
        }

        return $result;
    }

    public function formatDtrTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        $carbon = Carbon::parse($time);

        if ($carbon->format('H:i') === '12:00') {
            return '12:00 NN';
        }

        return $carbon->format('g:i A');
    }

    /**
     * Use official attendance times when they differ from raw scans (manual correction).
     *
     * @param  \Illuminate\Support\Collection<int, AttendanceLog>  $dayLogs
     * @return list<string>
     */
    protected function timesForDtrDay($dayLogs, ?Attendance $attendance): array
    {
        $logTimes = $dayLogs->isNotEmpty() ? AttendanceService::dedupeScanTimes($dayLogs) : [];
        $attIn = $attendance?->time_in ? Carbon::parse($attendance->time_in)->format('H:i:s') : null;
        $attOut = $attendance?->time_out ? Carbon::parse($attendance->time_out)->format('H:i:s') : null;

        if (! $attIn && ! $attOut) {
            return $logTimes;
        }

        $logFirst = $logTimes[0] ?? null;
        $logLast = $logTimes === [] ? null : $logTimes[count($logTimes) - 1];

        if ($logTimes !== [] && $logFirst === $attIn && $logLast === $attOut) {
            return $logTimes;
        }

        $times = array_values(array_filter([$attIn, $attOut]));
        if (count($times) === 2 && $times[0] === $times[1]) {
            return [$times[0]];
        }

        return $times;
    }

    /**
     * @return array<int, array{am_arrival:?string,am_departure:?string,pm_arrival:?string,pm_departure:?string,label:?string}>
     */
    public function buildMonthlyDayMap(int $personnelId, string $month): array
    {
        return $this->buildMonthlyDayMaps([['id' => $personnelId]], $month)[$personnelId];
    }

    /**
     * One set of queries for every person, so a full-staff DTR finishes before the host times out.
     *
     * @param  iterable<int, mixed>  $people
     * @return array<int, array<int, array{am_arrival:?string,am_departure:?string,pm_arrival:?string,pm_departure:?string,ot_arrival:?string,ot_departure:?string,label:?string}>>
     */
    public function buildMonthlyDayMaps(iterable $people, string $month): array
    {
        $ids = [];
        foreach ($people as $person) {
            $id = (int) (is_array($person) ? $person['id'] : $person->id);
            $ids[$id] = $id;
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return [];
        }

        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;
        $from = $start->toDateString();
        $to = $end->toDateString();

        $holidays = [];
        if (class_exists(Holiday::class)) {
            try {
                $holidays = Holiday::query()
                    ->whereBetween('date', [$from, $to])
                    ->get()
                    ->keyBy(fn ($h) => Carbon::parse($h->date)->day)
                    ->all();
            } catch (\Throwable) {
                $holidays = [];
            }
        }

        $logsByPerson = AttendanceLog::whereIn('personnel_id', $ids)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->groupBy('personnel_id')
            ->map(fn ($rows) => $rows->groupBy(fn ($log) => Carbon::parse($log->date)->day));

        $attendanceByPersonDate = Attendance::whereIn('personnel_id', $ids)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->groupBy(fn ($attendance) => $attendance->personnel_id.'|'.Carbon::parse($attendance->date)->toDateString());

        $locatorGroups = [];
        $locators = LocatorRequest::query()
            ->with('staffUser')
            ->where('status', 'approved')
            ->whereDate('date', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereDate('date_to', '>=', $from)
                    ->orWhere(function ($query) use ($from) {
                        $query->whereNull('date_to')->whereDate('date', '>=', $from);
                    });
            })
            ->where(function ($query) use ($ids) {
                $query->whereIn('personnel_id', $ids)
                    ->orWhereHas('staffUser', fn ($staff) => $staff->whereIn('personnel_id', $ids));
            })
            ->get();

        foreach ($locators as $locator) {
            $coveredIds = array_filter([
                (int) $locator->personnel_id,
                (int) ($locator->staffUser->personnel_id ?? 0),
            ]);
            foreach (array_unique($coveredIds) as $coveredId) {
                if (! in_array($coveredId, $ids, true)) {
                    continue;
                }
                $locatorGroups[$coveredId][] = $locator;
            }
        }

        $maps = [];
        foreach ($ids as $personnelId) {
            $dayMap = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $label = null;
                if (isset($holidays[$d])) {
                    $name = strtoupper((string) ($holidays[$d]->name ?? 'HOLIDAY'));
                    $label = str_contains($name, 'HOLIDAY') ? $name : $name.' (HOLIDAY)';
                }

                $dayMap[$d] = [
                    'am_arrival' => null,
                    'am_departure' => null,
                    'pm_arrival' => null,
                    'pm_departure' => null,
                    'ot_arrival' => null,
                    'ot_departure' => null,
                    'label' => $label,
                ];
            }

            $logs = $logsByPerson->get($personnelId, collect());
            foreach ($dayMap as $day => $_) {
                $date = $start->copy()->day($day)->toDateString();
                $dayLogs = $logs->get($day, collect());
                $attendance = $attendanceByPersonDate->get($personnelId.'|'.$date)?->first();

                $times = $this->timesForDtrDay($dayLogs, $attendance);
                if ($times === []) {
                    continue;
                }

                $mapped = $this->mapScansToDtr($times);
                $dayMap[$day] = array_merge(
                    $dayMap[$day],
                    array_map(fn ($t) => $this->formatDtrTime($t), $mapped)
                );
                if ($dayMap[$day]['am_arrival'] || $dayMap[$day]['am_departure']
                    || $dayMap[$day]['pm_arrival'] || $dayMap[$day]['pm_departure']
                    || $dayMap[$day]['ot_arrival'] || $dayMap[$day]['ot_departure']) {
                    $dayMap[$day]['label'] = null;
                }
            }

            $this->applyApprovedLocatorMarks(
                $dayMap,
                $personnelId,
                $from,
                $to,
                collect($locatorGroups[$personnelId] ?? [])
            );
            $maps[$personnelId] = $dayMap;
        }

        return $maps;
    }

    /**
     * Official Business: keep existing AM or PM punches and mark the empty half as OB.
     *
     * @param  array<int, array{am_arrival:?string,am_departure:?string,pm_arrival:?string,pm_departure:?string,label:?string}>  $dayMap
     */
    protected function applyApprovedLocatorMarks(array &$dayMap, int $personnelId, string $from, string $to, $locators = null): void
    {
        if ($locators === null) {
            $locators = LocatorRequest::query()
                ->approvedCovering($personnelId, $from, $to)
                ->get();
        }

        if ($locators->isEmpty()) {
            return;
        }

        $covered = [];
        foreach ($locators as $locator) {
            foreach ($locator->inclusiveDateStrings() as $date) {
                if ($date >= $from && $date <= $to) {
                    $covered[$date] = true;
                }
            }
        }

        foreach ($dayMap as $day => $data) {
            $date = Carbon::parse($from)->day($day)->toDateString();
            if (! isset($covered[$date])) {
                continue;
            }

            $hasAm = $this->dayHasMorningPresence($data);
            $hasPm = $this->dayHasAfternoonPresence($data);

            if (! $hasAm && ! $hasPm) {
                if (empty($dayMap[$day]['label'])) {
                    $dayMap[$day]['label'] = 'OB';
                }

                continue;
            }

            if ($hasAm && ! $hasPm) {
                $dayMap[$day]['pm_arrival'] = $dayMap[$day]['pm_arrival'] ?: 'OB';

                continue;
            }

            if ($hasPm && ! $hasAm) {
                foreach (['am_arrival', 'am_departure'] as $key) {
                    $value = $dayMap[$day][$key] ?? null;
                    if (! $this->isAfternoonLabel($value)) {
                        continue;
                    }

                    if (empty($dayMap[$day]['pm_arrival'])) {
                        $dayMap[$day]['pm_arrival'] = $value;
                    } elseif (empty($dayMap[$day]['pm_departure'])) {
                        $dayMap[$day]['pm_departure'] = $value;
                    }

                    $dayMap[$day][$key] = null;
                }

                $dayMap[$day]['am_arrival'] = 'OB';
            }
        }
    }

    /**
     * @param  array{am_arrival:?string,am_departure:?string,pm_arrival:?string,pm_departure:?string,label:?string}  $data
     */
    protected function dayHasMorningPresence(array $data): bool
    {
        foreach (['am_arrival', 'am_departure', 'pm_arrival', 'pm_departure'] as $key) {
            if ($this->isMorningLabel($data[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{am_arrival:?string,am_departure:?string,pm_arrival:?string,pm_departure:?string,label:?string}  $data
     */
    protected function dayHasAfternoonPresence(array $data): bool
    {
        foreach (['am_arrival', 'am_departure', 'pm_arrival', 'pm_departure'] as $key) {
            if ($this->isAfternoonLabel($data[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    protected function isMorningLabel(?string $value): bool
    {
        if (! $value || $value === 'OB') {
            return false;
        }

        $upper = strtoupper($value);

        return str_contains($upper, 'AM') || str_contains($upper, 'NN');
    }

    protected function isAfternoonLabel(?string $value): bool
    {
        if (! $value || $value === 'OB') {
            return false;
        }

        return str_contains(strtoupper($value), 'PM');
    }

    public function resolveTemplatePath(): string
    {
        $configured = config('smartdtr.dtr_template_path', 'templates/dtr-blank-forms.xlsx');
        $candidates = [
            base_path('dtr-blank forms.xlsx'),
            base_path('dtr-blank-forms.xlsx'),
            storage_path('app/templates/dtr-blank-forms.xlsx'),
            storage_path('app/templates/dtr-blank forms.xlsx'),
            storage_path('app/'.$configured),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && filesize($path) > 10000) {
                return $path;
            }
        }

        throw new RuntimeException('DTR template not found. Place dtr-blank forms.xlsx in the project root.');
    }

    public function generateExcel(Personnel $personnel, string $month): string
    {
        $templatePath = $this->resolveTemplatePath();
        $reader = IOFactory::createReader('Xlsx');
        if ($reader instanceof XlsxReader) {
            $reader->setEnableDrawingPassThrough(true);
        }
        $spreadsheet = $reader->load($templatePath);
        $monthCarbon = Carbon::parse($month.'-01');

        $sheet = $spreadsheet->getSheet(0);
        $sheet->setTitle('DTR');
        $this->fillBlankTemplateSheet($sheet, $personnel, $monthCarbon);
        $this->clearRightHalfOfBlankTemplate($sheet);
        $this->applyBlankTemplatePrintSetup($sheet);

        for ($i = $spreadsheet->getSheetCount() - 1; $i >= 1; $i--) {
            $spreadsheet->removeSheetByIndex($i);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $outputDir = storage_path('app/exports');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = sprintf(
            'DTR_%s_%s.xlsx',
            preg_replace('/[^A-Za-z0-9_\-]/', '_', $personnel->name),
            $monthCarbon->format('Y-m')
        );
        $outputPath = $outputDir.DIRECTORY_SEPARATOR.$filename;

        (new Xlsx($spreadsheet))->save($outputPath);

        return $outputPath;
    }

    /**
     * Combined PDF: two Form 48 copies on the left half of landscape long bond.
     */
    public function generateBulkPdf(string $month): string
    {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        $monthCarbon = Carbon::parse($month.'-01');
        $outputDir = storage_path('app/exports');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $stamp = $monthCarbon->format('Y-m').'_'.date('Ymd_His');
        $pdfPath = $outputDir.DIRECTORY_SEPARATOR.sprintf('DTR_ALL_%s.pdf', $stamp);
        $finalPdf = $outputDir.DIRECTORY_SEPARATOR.sprintf('DTR_ALL_%s.pdf', $monthCarbon->format('Y-m'));

        // Hostinger has no Microsoft Excel. Building one workbook per employee
        // runs past nginx and comes back as 504. Write the PDF directly.
        if (! $this->excelPdfConversionAvailable()) {
            return $this->generateBlankLayoutPdf($month, $finalPdf);
        }

        try {
            // Unique workbook path avoids "Resource temporarily unavailable" locks on DTR_ALL_YYYY-MM.xlsx
            $excelPath = $this->generateBulkExcel($month, $outputDir.DIRECTORY_SEPARATOR.sprintf('DTR_ALL_%s.xlsx', $stamp));
            $converted = $this->convertExcelToPdf($excelPath, $pdfPath);

            if (@copy($converted, $finalPdf)) {
                return $finalPdf;
            }

            return $converted;
        } catch (\Throwable $e) {
            report($e);

            // Last resort: DomPDF layout that mirrors blank template columns (incl. OVERTIME).
            return $this->generateBlankLayoutPdf($month, $finalPdf);
        }
    }

    /**
     * Single-employee PDF from blank template (Excel COM), else landscape 2-copy DomPDF.
     */
    public function generateOfficialPdf(Personnel $personnel, string $month): string
    {
        $excelPath = $this->generateExcel($personnel, $month);
        $pdfPath = preg_replace('/\.xlsx$/i', '.pdf', $excelPath) ?: ($excelPath.'.pdf');

        try {
            return $this->convertExcelToPdf($excelPath, $pdfPath);
        } catch (\Throwable $e) {
            report($e);

            return $this->generateBlankLayoutPdf($month, $pdfPath, collect([$personnel]));
        }
    }

    /**
     * DomPDF layout: 2 Form 48 copies on the left half of landscape Folio (13" × 8.5").
     *
     * @param  \Illuminate\Support\Collection<int, Personnel>|null  $people
     */
    public function generateBlankLayoutPdf(string $month, ?string $pdfPath = null, $people = null): string
    {
        $people = $people ?? Personnel::active()->orderBy('name')->get();
        if ($people->isEmpty()) {
            throw new RuntimeException('No active personnel found to export.');
        }

        $monthCarbon = Carbon::parse($month.'-01');
        $monthLabel = 'For the month of '.strtoupper($monthCarbon->format('F Y'));
        $daysInMonth = $monthCarbon->daysInMonth;
        $verifier = DocumentSignatory::resolve('dtr_verifier')
            ?? config('smartdtr.dtr_verifier_name', 'MARIE ROSE P. RAMOS');

        $dayMaps = $this->buildMonthlyDayMaps($people, $month);

        $outputDir = storage_path('app/exports');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $pdfPath ??= $outputDir.DIRECTORY_SEPARATOR.sprintf('DTR_ALL_%s.pdf', $monthCarbon->format('Y-m'));

        // One giant HTML document for every employee blows DomPDF's memory and
        // comes back as a 500. Render a few people at a time, then join the pages.
        $chunkPaths = [];
        $stamp = uniqid('dtr_', true);
        foreach ($people->values()->chunk(4) as $index => $chunk) {
            $records = [];
            foreach ($chunk as $person) {
                $records[] = [
                    'personnel' => $person,
                    'dayMap' => $dayMaps[$person->id] ?? $dayMaps[(int) $person->id] ?? [],
                    'regular' => $person->official_hours_regular ?: '7:30-11:30 / 1:00-5:00',
                    'saturday' => $person->official_hours_saturday ?: 'as required',
                ];
            }

            $chunkPath = $outputDir.DIRECTORY_SEPARATOR.sprintf('DTR_PART_%s_%d.pdf', $stamp, $index);
            $this->writeBlankLayoutChunk($records, $monthLabel, $daysInMonth, $verifier, $chunkPath);
            $chunkPaths[] = $chunkPath;
        }

        try {
            if (count($chunkPaths) === 1) {
                if (is_file($pdfPath)) {
                    @unlink($pdfPath);
                }
                if (! @rename($chunkPaths[0], $pdfPath)) {
                    if (! @copy($chunkPaths[0], $pdfPath)) {
                        throw new RuntimeException('Failed to create DTR PDF from blank template layout.');
                    }
                    @unlink($chunkPaths[0]);
                }
            } else {
                $this->mergePdfFiles($chunkPaths, $pdfPath);
            }
        } finally {
            foreach ($chunkPaths as $chunkPath) {
                if (is_file($chunkPath) && $chunkPath !== $pdfPath) {
                    @unlink($chunkPath);
                }
            }
        }

        if (! is_file($pdfPath)) {
            throw new RuntimeException('Failed to create DTR PDF from blank template layout.');
        }

        return $pdfPath;
    }

    /**
     * @param  list<array{personnel:mixed,dayMap:array,regular:string,saturday:string}>  $records
     */
    protected function writeBlankLayoutChunk(array $records, string $monthLabel, int $daysInMonth, string $verifier, string $chunkPath): void
    {
        $pdf = Pdf::loadView('reports.dtr-blank-pdf', [
            'records' => $records,
            'monthLabel' => $monthLabel,
            'daysInMonth' => $daysInMonth,
            'verifier' => $verifier,
        ])->setPaper([0, 0, 936, 612], 'landscape');

        $pdf->save($chunkPath);
        unset($pdf);

        if (! is_file($chunkPath)) {
            throw new RuntimeException('Failed to create DTR PDF from blank template layout.');
        }
    }

    /**
     * @param  list<string>  $files
     */
    protected function mergePdfFiles(array $files, string $outputPath): void
    {
        $pdf = new Fpdi();
        foreach ($files as $file) {
            $pageCount = $pdf->setSourceFile($file);
            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }

        $pdf->Output('F', $outputPath);

        if (! is_file($outputPath)) {
            throw new RuntimeException('Failed to join the DTR pages into one PDF.');
        }
    }

    /**
     * @deprecated Use generateBlankLayoutPdf()
     */
    protected function generateBulkDomPdf(string $month, ?string $pdfPath = null): string
    {
        return $this->generateBlankLayoutPdf($month, $pdfPath);
    }

    /**
     * Build one workbook with a filled blank-template sheet per active employee.
     */
    public function generateBulkExcel(string $month, ?string $outputPath = null): string
    {
        $people = Personnel::active()->orderBy('name')->get();
        if ($people->isEmpty()) {
            throw new RuntimeException('No active personnel found to export.');
        }

        @set_time_limit(600);

        $master = null;
        $usedTitles = [];

        foreach ($people as $person) {
            $filledPath = $this->generateExcel($person, $month);
            $book = IOFactory::load($filledPath);
            $sheet = $book->getSheet(0);

            $title = $this->uniqueSheetTitle($person, $usedTitles);
            $usedTitles[] = mb_strtolower($title);
            $sheet->setTitle($title);

            if ($master === null) {
                $master = $book;

                continue;
            }

            $master->addExternalSheet($sheet);
            unset($book);
        }

        if ($master === null) {
            throw new RuntimeException('Could not build bulk DTR workbook.');
        }

        $master->setActiveSheetIndex(0);

        $outputDir = storage_path('app/exports');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath ??= $outputDir.DIRECTORY_SEPARATOR.sprintf(
            'DTR_ALL_%s.xlsx',
            Carbon::parse($month.'-01')->format('Y-m')
        );

        // Write via temp file then rename — avoids lock conflicts on a stable download name.
        $tempPath = $outputPath.'.'.uniqid('tmp_', true).'.xlsx';
        (new Xlsx($master))->save($tempPath);
        $master->disconnectWorksheets();

        if (is_file($outputPath)) {
            @unlink($outputPath);
        }
        if (! @rename($tempPath, $outputPath)) {
            @copy($tempPath, $outputPath);
            @unlink($tempPath);
        }

        if (! is_file($outputPath)) {
            throw new RuntimeException('Could not save bulk DTR workbook.');
        }

        return $outputPath;
    }

    /**
     * ZIP of individual official Excel DTRs (always works without Excel COM).
     */
    public function generateBulkExcelZip(string $month): string
    {
        $people = Personnel::active()->orderBy('name')->get();
        if ($people->isEmpty()) {
            throw new RuntimeException('No active personnel found to export.');
        }

        @set_time_limit(600);

        $outputDir = storage_path('app/exports');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $zipPath = $outputDir.DIRECTORY_SEPARATOR.sprintf('DTR_ALL_%s.zip', Carbon::parse($month.'-01')->format('Y-m'));
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create DTR ZIP archive.');
        }

        foreach ($people as $person) {
            $path = $this->generateExcel($person, $month);
            $zip->addFile($path, basename($path));
        }

        $zip->close();

        return $zipPath;
    }

    protected function excelPdfConversionAvailable(): bool
    {
        return PHP_OS_FAMILY === 'Windows' && is_file(base_path('scripts/excel-to-pdf.ps1'));
    }

    public function convertExcelToPdf(string $excelPath, ?string $pdfPath = null): string
    {
        $excelPath = realpath($excelPath) ?: $excelPath;
        if (! is_file($excelPath)) {
            throw new RuntimeException('Excel file not found for PDF conversion.');
        }

        $script = base_path('scripts/excel-to-pdf.ps1');
        if (! is_file($script)) {
            throw new RuntimeException('Excel-to-PDF script missing: '.$script);
        }

        $pdfPath ??= preg_replace('/\.xlsx$/i', '.pdf', $excelPath) ?: ($excelPath.'.pdf');
        $pdfDir = dirname($pdfPath);
        if (! is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-File', $script,
            '-Source', $excelPath,
            '-Output', $pdfPath,
        ]);
        $process->setTimeout(600);
        $process->setWorkingDirectory(base_path());
        $process->run();

        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());
        if (! $process->isSuccessful() || ! is_file($pdfPath) || ! str_starts_with($output, 'OK')) {
            throw new RuntimeException(
                'Could not convert DTR Excel to PDF. Make sure Microsoft Excel is installed. '.$output
            );
        }

        return $pdfPath;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPdfData(Personnel $personnel, string $month): array
    {
        $monthCarbon = Carbon::parse($month.'-01');

        return [
            'personnel' => $personnel,
            'monthLabel' => 'For the month of '.strtoupper($monthCarbon->format('F Y')),
            'dayMap' => $this->buildMonthlyDayMap($personnel->id, $month),
            'daysInMonth' => $monthCarbon->daysInMonth,
            'verifier' => DocumentSignatory::resolve('dtr_verifier')
                ?? config('smartdtr.dtr_verifier_name', 'MARIE ROSE P. RAMOS'),
        ];
    }

    /**
     * @param  list<string>  $usedTitles
     */
    protected function uniqueSheetTitle(Personnel $personnel, array $usedTitles): string
    {
        $base = preg_replace('/[\\\\\/\?\*\[\]]+/', '', $personnel->name) ?: ('ID'.$personnel->id);
        $base = trim((string) $base);
        if ($base === '') {
            $base = 'ID'.$personnel->id;
        }
        $base = mb_substr($base, 0, 28);

        $title = $base;
        $n = 2;
        while (in_array(mb_strtolower($title), $usedTitles, true)) {
            $suffix = '_'.$n;
            $title = mb_substr($base, 0, 31 - strlen($suffix)).$suffix;
            $n++;
        }

        return $title;
    }

    protected function fillBlankTemplateSheet(Worksheet $sheet, Personnel $personnel, Carbon $monthCarbon): void
    {
        $monthLabel = 'For the month of '.strtoupper($monthCarbon->format('F Y'));

        // Match blank Form 48 header lines: title on row 6, schedule detail on row 7.
        $hoursLine1 = 'Official hours of arrival and departure';
        $regular = $personnel->official_hours_regular ?: '7:30-11:30 / 1:00-5:00';
        $saturday = $personnel->official_hours_saturday ?: 'as required';
        $hoursLine2 = 'Regular days: '.$regular.'     Saturdays: '.$saturday;

        $verifier = DocumentSignatory::resolve('dtr_verifier')
            ?? config('smartdtr.dtr_verifier_name', 'MARIE ROSE P. RAMOS');
        $dayMap = $this->buildMonthlyDayMap($personnel->id, $monthCarbon->format('Y-m'));
        $daysInMonth = $monthCarbon->daysInMonth;

        $templateLabels = [];
        $first = $this->formCopies[0];
        for ($day = 1; $day <= 31; $day++) {
            $row = $this->dayRowOffset + $day;
            $am = trim((string) $sheet->getCell($first['am_arrival'].$row)->getValue());
            $dep = trim((string) $sheet->getCell($first['am_departure'].$row)->getValue());
            if ($this->looksLikeDayLabel($am)) {
                $templateLabels[$day] = [
                    'am_arrival' => $am,
                    'am_departure' => $dep,
                ];
            }
        }

        foreach ($this->formCopies as $cols) {
            $sheet->setCellValue($cols['name'], $personnel->name);
            $sheet->getStyle($cols['name'])->getAlignment()->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );
            $sheet->setCellValue($cols['name_label'], '(Name)');
            $sheet->setCellValue($cols['month'], $monthLabel);
            $sheet->setCellValue($cols['hours_line1'], $hoursLine1);
            $sheet->setCellValue($cols['hours_line2'], $hoursLine2);
            $sheet->setCellValue($cols['signature_name'], $personnel->name);
            $sheet->setCellValue($cols['verifier_name'], $verifier);

            for ($day = 1; $day <= 31; $day++) {
                $row = $this->dayRowOffset + $day;
                $sheet->setCellValue($cols['day'].$row, $day <= $daysInMonth ? $day : '');

                foreach (['am_arrival', 'am_departure', 'pm_arrival', 'pm_departure', 'ot_arrival', 'ot_departure'] as $key) {
                    $sheet->setCellValue($cols[$key].$row, '');
                }

                if ($day > $daysInMonth) {
                    continue;
                }

                $data = $dayMap[$day];
                $hasPunches = ! empty($data['am_arrival']) || ! empty($data['am_departure'])
                    || ! empty($data['pm_arrival']) || ! empty($data['pm_departure'])
                    || ! empty($data['ot_arrival']) || ! empty($data['ot_departure']);

                if ($hasPunches) {
                    $sheet->setCellValue($cols['am_arrival'].$row, $data['am_arrival'] ?? '');
                    $sheet->setCellValue($cols['am_departure'].$row, $data['am_departure'] ?? '');
                    $sheet->setCellValue($cols['pm_arrival'].$row, $data['pm_arrival'] ?? '');
                    $sheet->setCellValue($cols['pm_departure'].$row, $data['pm_departure'] ?? '');
                    $sheet->setCellValue($cols['ot_arrival'].$row, $data['ot_arrival'] ?? '');
                    $sheet->setCellValue($cols['ot_departure'].$row, $data['ot_departure'] ?? '');

                    continue;
                }

                if (isset($templateLabels[$day])) {
                    $sheet->setCellValue($cols['am_arrival'].$row, $templateLabels[$day]['am_arrival']);
                    $sheet->setCellValue($cols['am_departure'].$row, $templateLabels[$day]['am_departure']);

                    continue;
                }

                // Holidays only — never auto-write Saturday/Sunday labels.
                if (! empty($data['label'])) {
                    $sheet->setCellValue($cols['am_arrival'].$row, $data['label']);
                }
            }
        }
    }

    /**
     * Wipe copies 3–4 so they do not print. Keep those columns so Fit-to-page
     * stays at the original 4-up scale (left half used, right half blank).
     */
    protected function clearRightHalfOfBlankTemplate(Worksheet $sheet): void
    {
        $firstRightCol = 17; // Q
        $lastCol = 32; // AF

        foreach (array_keys($sheet->getMergeCells()) as $merged) {
            [$start, $end] = Coordinate::rangeBoundaries($merged);
            if ($end[0] < $firstRightCol) {
                continue;
            }

            $sheet->unmergeCells($merged);

            // Keep any merge that started on the left two copies.
            if ($start[0] < $firstRightCol) {
                $left = Coordinate::stringFromColumnIndex($start[0]).$start[1]
                    .':'.Coordinate::stringFromColumnIndex($firstRightCol - 1).$end[1];
                $sheet->mergeCells($left);
            }
        }

        for ($col = $firstRightCol; $col <= $lastCol; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            for ($row = 1; $row <= 51; $row++) {
                $cell = $sheet->getCell($letter.$row);
                $cell->setValue(null);
                $cell->setXfIndex(0);
            }
        }

        $sheet->getStyle('Q1:AF51')->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_NONE],
                'outline' => ['borderStyle' => Border::BORDER_NONE],
                'inside' => ['borderStyle' => Border::BORDER_NONE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFFFF'],
            ],
            'font' => [
                'color' => ['rgb' => 'FFFFFF'],
                'underline' => false,
            ],
        ]);

        $this->stripRightHalfDrawings($sheet);
    }

    /**
     * Certification text and underline strokes live in drawingML textboxes/lines,
     * not cells. Drop any anchor that starts on copy 3 or 4 (column Q onward).
     */
    protected function stripRightHalfDrawings(Worksheet $sheet): void
    {
        $spreadsheet = $sheet->getParent();
        if ($spreadsheet === null) {
            return;
        }

        $data = $spreadsheet->getUnparsedLoadedData();
        $code = $sheet->getCodeName();
        $drawings = $data['sheets'][$code]['Drawings'] ?? [];
        if ($drawings === []) {
            return;
        }

        foreach ($drawings as $relId => $xml) {
            if (! is_string($xml) || $xml === '') {
                continue;
            }
            $data['sheets'][$code]['Drawings'][$relId] = $this->filterRightHalfDrawingXml($xml);
        }

        $spreadsheet->setUnparsedLoadedData($data);
    }

    /**
     * Drawing columns are 0-based (A=0). Q = 16 is the start of copy 3.
     */
    protected function filterRightHalfDrawingXml(string $xml): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        if (! @$dom->loadXML($xml)) {
            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');

        $anchors = $xpath->query('//xdr:twoCellAnchor | //xdr:oneCellAnchor | //xdr:absoluteAnchor');
        if ($anchors === false) {
            return $xml;
        }

        $remove = [];
        foreach ($anchors as $anchor) {
            $fromCol = $xpath->evaluate('number(xdr:from/xdr:col)', $anchor);
            if (is_numeric($fromCol) && (float) $fromCol >= 16) {
                $remove[] = $anchor;
            }
        }

        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }

        $out = $dom->saveXML();

        return is_string($out) ? $out : $xml;
    }

    protected function applyBlankTemplatePrintSetup(Worksheet $sheet): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $setup->setPaperSize(PageSetup::PAPERSIZE_FOLIO);
        $setup->setPrintArea('A1:AF51');
        $setup->setFitToPage(true);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight(1);
        $setup->setHorizontalCentered(true);
        $setup->setVerticalCentered(true);

        $margins = $sheet->getPageMargins();
        $margins->setTop(0.25);
        $margins->setBottom(0.25);
        $margins->setLeft(0.25);
        $margins->setRight(0.25);
        $margins->setHeader(0);
        $margins->setFooter(0);
    }

    protected function looksLikeDayLabel(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $upper = strtoupper($value);

        // Ignore weekend placeholders from the blank template — leave those cells empty for punches.
        if ($upper === 'SATURDAY'
            || $upper === 'SUNDAY'
            || str_starts_with($upper, 'SATURDAY ')
            || str_contains($upper, 'AS REQUIRED')) {
            return false;
        }

        return str_contains($upper, 'HOLIDAY')
            || preg_match('/^[A-Z0-9 \'\-]+\(.*\)$/', $upper) === 1;
    }

    protected function clearDayCells(Worksheet $sheet): void
    {
        foreach ($this->formCopies as $cols) {
            for ($day = 1; $day <= 31; $day++) {
                $row = $this->dayRowOffset + $day;
                foreach (['am_arrival', 'am_departure', 'pm_arrival', 'pm_departure', 'ot_arrival', 'ot_departure'] as $key) {
                    $sheet->setCellValue($cols[$key].$row, '');
                }
            }
        }
    }

    protected function writeMonthHeaderPlaceholders(Worksheet $sheet, Carbon $monthCarbon): void
    {
        $label = 'For the month of '.strtoupper($monthCarbon->format('F Y'));
        foreach ($this->formCopies as $cols) {
            $sheet->setCellValue($cols['month'], $label);
        }
    }

    protected function isBetween(string $time, string $start, string $end): bool
    {
        $t = Carbon::parse('2000-01-01 '.$time);
        $s = Carbon::parse('2000-01-01 '.$start);
        $e = Carbon::parse('2000-01-01 '.$end);

        return $t->betweenIncluded($s, $e);
    }
}
