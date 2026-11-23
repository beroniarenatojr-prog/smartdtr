<?php

namespace App\Services;

use App\Models\DocumentSignatory;
use App\Models\LeaveRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\Process\Process;

class LeaveApplicationExportService
{
    /**
     * CS Form No. 6 leave-type checkbox rows (column B) on both FORM 6 sheets.
     *
     * @var array<string, int>
     */
    protected array $leaveTypeRows = [
        'Vacation Leave' => 11,
        'Mandatory/Forced Leave' => 13,
        'Sick Leave' => 15,
        'Maternity Leave' => 17,
        'Paternity Leave' => 19,
        'Special Privilege Leave' => 21,
        'Solo Parent Leave' => 23,
        'Study Leave' => 25,
        '10-Day VAWC Leave' => 27,
        'Rehabilitation Privilege' => 29,
        'Special Leave Benefits for Women' => 31,
        'Special Emergency (Calamity) Leave' => 33,
        'Adoption Leave' => 35,
        'Others' => 39,
        // Legacy app values
        'Leave' => 11,
        'Sick' => 15,
        'Emergency' => 33,
        'Travel' => 21,
        'Other' => 39,
    ];

    public function resolveTemplatePath(): string
    {
        foreach ([
            base_path('FORM 6.xlsx'),
            storage_path('app/templates/FORM 6.xlsx'),
            base_path('APPLICATION FOR LEAVE.xlsx'),
            storage_path('app/templates/APPLICATION FOR LEAVE.xlsx'),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('FORM 6.xlsx leave template not found.');
    }

    /**
     * Official FORM 6 sheet: 60+ days uses SDS approval; under 60 uses ASDS approval.
     */
    public function resolveSheetName(LeaveRequest $request): string
    {
        return $request->dayCount() >= 60 ? '60 DAYS ABOVE' : '60 DAYS BELOW';
    }

    public function generateExcel(LeaveRequest $request): string
    {
        $request->loadMissing(['staffUser', 'personnel', 'reviewer']);

        $spreadsheet = IOFactory::load($this->resolveTemplatePath());
        $sheetName = $this->resolveSheetName($request);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (! $sheet) {
            // Fallback if an older template only has the long-leave sheet.
            $sheet = $spreadsheet->getSheetByName('60 DAYS ABOVE')
                ?? $spreadsheet->getSheetByName('60 DAYS BELOW')
                ?? $spreadsheet->getSheet(0);
        }

        $this->fillForm($sheet, $request);

        // Keep only the filled form sheet for print.
        $keep = $sheet->getTitle();
        for ($i = $spreadsheet->getSheetCount() - 1; $i >= 0; $i--) {
            if ($spreadsheet->getSheet($i)->getTitle() !== $keep) {
                $spreadsheet->removeSheetByIndex($i);
            }
        }
        $spreadsheet->setActiveSheetIndex(0);
        $sheet->getPageSetup()->setPrintArea('A1:I69');
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(1);

        $dir = storage_path('app/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $band = $request->dayCount() >= 60 ? '60up' : 'below60';
        $filename = sprintf(
            'Application_for_Leave_%s_%s_%s.xlsx',
            preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->exportName($request)),
            $request->date_from->format('Y-m-d'),
            $band
        );
        $path = $dir.DIRECTORY_SEPARATOR.$filename;
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * Official CS Form 6 as PDF (Excel COM, same filled workbook as Excel download).
     */
    public function generatePdf(LeaveRequest $request): string
    {
        $excelPath = $this->generateExcel($request);
        $pdfPath = preg_replace('/\.xlsx$/i', '.pdf', $excelPath) ?: ($excelPath.'.pdf');

        try {
            return $this->convertExcelToPdf($excelPath, $pdfPath);
        } catch (\Throwable $e) {
            report($e);

            // Hostinger / servers without Excel COM — print-faithful DomPDF Form 6.
            return $this->generateDomPdf($request, $pdfPath);
        }
    }

    /**
     * CS Form No. 6 layout used when Excel-to-PDF is unavailable.
     */
    public function generateDomPdf(LeaveRequest $request, ?string $pdfPath = null): string
    {
        $request->loadMissing(['staffUser', 'personnel', 'reviewer']);
        $staff = $request->staffUser;
        $parts = $staff?->formNameParts() ?? $this->splitName($staff->name ?? '');
        $office = $request->office_department
            ?: ($staff->department ?: 'Isabela National High School');
        $position = $request->position
            ?: ($staff->department ?: ($staff->personnel?->role ?? $staff->roleLabel()));
        $longLeave = $request->dayCount() >= 60;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.leave-form6-pdf', [
            'request' => $request,
            'staff' => $staff,
            'parts' => $parts,
            'office' => $office,
            'position' => $position,
            'salary' => $request->salary ?: '',
            'filingDate' => ($request->created_at ?? now())->format('F d, Y'),
            'details' => trim((string) ($request->leave_details ?: $request->reason)),
            'longLeave' => $longLeave,
            'schoolHead' => DocumentSignatory::resolve('school_head') ?? 'MARIE ROSE P. RAMOS',
            'assistantSds' => DocumentSignatory::resolve('assistant_sds') ?? 'CHERYL R. RAMIRO, PhD, CESO VI',
            'sds' => DocumentSignatory::resolve('schools_division_superintendent') ?? 'EDUARDO C. ESCORPISO, JR., EdD, CESO V',
            'leaveTypeRows' => $this->leaveTypeRows,
        ])->setPaper('legal', 'portrait');

        $dir = storage_path('app/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdfPath ??= $dir.DIRECTORY_SEPARATOR.sprintf(
            'Application_for_Leave_%s.pdf',
            preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->exportName($request))
        );
        $pdf->save($pdfPath);

        if (! is_file($pdfPath)) {
            throw new RuntimeException('Failed to create Application for Leave PDF.');
        }

        return $pdfPath;
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
            '-Orientation', '1',
            '-PaperSize', '5',
            '-PrintArea', 'A1:I69',
        ]);
        $process->setTimeout(180);
        $process->setWorkingDirectory(base_path());
        $process->run();

        $output = trim($process->getOutput().PHP_EOL.$process->getErrorOutput());
        if (! $process->isSuccessful() || ! is_file($pdfPath) || ! str_starts_with($output, 'OK')) {
            throw new RuntimeException(
                'Could not convert Application for Leave to PDF. Make sure Microsoft Excel is installed. '.$output
            );
        }

        return $pdfPath;
    }

    protected function exportName(LeaveRequest $request): string
    {
        $staff = $request->staffUser;

        return $staff?->officialLetterName() ?: ($staff->name ?? 'Staff');
    }

    protected function fillForm(Worksheet $sheet, LeaveRequest $request): void
    {
        $staff = $request->staffUser;
        $office = $request->office_department
            ?: ($staff->department ?: 'Isabela National High School');
        $position = $request->position
            ?: ($staff->department ?: ($staff->personnel?->role ?? $staff->roleLabel()));
        $salary = $request->salary ?: '';
        $filingDate = ($request->created_at ?? now())->format('F d, Y');
        $parts = $staff?->formNameParts() ?? $this->splitName($staff->name ?? '');

        $sheet->setCellValue('B5', $office);
        // Write into (Last) / (First) / (Middle) — never one combined string under "NAME".
        $this->writeNameParts($sheet, $parts);
        $sheet->setCellValue('A6', '3.   DATE OF FILING  '.$filingDate);
        $sheet->setCellValue(
            'E6',
            '4.   POSITION  '.$position.($salary !== '' ? '     5.  SALARY  '.$salary : '     5.  SALARY  _______________')
        );

        // Official FORM 6 / Application for Leave: leave-type boxes are in column C
        // (Others stays on B39). Clear then mark the selected type.
        foreach (array_unique(array_values($this->leaveTypeRows)) as $row) {
            if ($row === 39) {
                $sheet->setCellValue('B39', 'Others:  ');
            } else {
                $sheet->setCellValue('C'.$row, '☐');
            }
        }

        $typeKey = $request->leave_type;
        $row = $this->leaveTypeRows[$typeKey] ?? 11;
        if ($row === 39) {
            $sheet->setCellValue('B39', '☑ Others:  ');
            $sheet->setCellValue('B41', $typeKey === 'Others' || $typeKey === 'Other'
                ? ($request->leave_details ?: $request->reason)
                : $typeKey);
        } else {
            $sheet->setCellValue('C'.$row, '☑');
        }

        $details = trim((string) ($request->leave_details ?: $request->reason));
        $normalized = strtolower($typeKey);

        if (str_contains($normalized, 'sick') || $typeKey === 'Sick') {
            $sheet->setCellValue('I19', 'In Hospital (Specify Illness) '.$details);
        } elseif (str_contains($normalized, 'vacation') || $typeKey === 'Leave' || str_contains($normalized, 'privilege') || $typeKey === 'Travel') {
            $sheet->setCellValue('I13', 'Within the Philippines '.$details);
        } elseif (str_contains($normalized, 'study')) {
            $sheet->setCellValue('H37', 'Other purpose: '.$details);
        } else {
            $sheet->setCellValue('I13', 'Within the Philippines '.$details);
        }

        $sheet->setCellValue('B45', (string) $request->dayCount().' working day(s)');
        $sheet->setCellValue(
            'B48',
            $request->date_from->format('F d, Y').' – '.$request->date_to->format('F d, Y')
        );

        // Commutation
        if (($request->commutation ?? 'not_requested') === 'requested') {
            $sheet->setCellValue('I47', '☑ Requested');
            $sheet->setCellValue('I45', '☐ Not Requested');
        } else {
            $sheet->setCellValue('I45', '☑ Not Requested');
            $sheet->setCellValue('I47', '☐ Requested');
        }

        // Applicant: printed name in the blank space above the signature line.
        $hasNameMerge = false;
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            if (preg_match('/^G48:/i', $range)) {
                $hasNameMerge = true;
                break;
            }
        }
        if (! $hasNameMerge) {
            $sheet->mergeCells('G48:I48');
        }
        $sheet->setCellValue('G48', $staff?->officialLetterName() ?: strtoupper((string) $staff->name));
        $sheet->getStyle('G48')->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        );
        $sheet->getStyle('G48')->getAlignment()->setVertical(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_BOTTOM
        );
        $sheet->getStyle('G48')->getFont()->setBold(true);

        $sheet->setCellValue('G49', 'Signature over Printed Name');
        $sheet->getStyle('G49')->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        );

        // 7.A leave-credit table stays blank for the certifying officer to fill by hand.
        $sheet->setCellValue('C53', 'As of '.now()->format('F d, Y'));
        foreach (['D56', 'E56', 'D57', 'E57', 'D58', 'E58'] as $cell) {
            $sheet->setCellValue($cell, '');
        }

        if ($request->status === 'approved') {
            $sheet->setCellValue('I53', '☑ For approval');
            $sheet->setCellValue('C62', $request->dayCount().' days with pay');
        } elseif ($request->status === 'rejected') {
            $sheet->setCellValue('I55', 'For disapproval due to '.($request->admin_notes ?: '________________________'));
            $sheet->setCellValue('I63', $request->admin_notes ?: '______________________________________________');
        }

        $this->replaceTemplateSignatories($sheet, $request->dayCount() >= 60);
    }

    protected function replaceTemplateSignatories(Worksheet $sheet, bool $longLeave): void
    {
        $replacements = $longLeave
            ? [
                'CHERYL R. RAMIRO' => DocumentSignatory::resolve('assistant_sds') ?? 'CHERYL R. RAMIRO',
                'EDUARDO C. ESCORPISO, JR.' => DocumentSignatory::resolve('schools_division_superintendent') ?? 'EDUARDO C. ESCORPISO, JR.',
            ]
            : [
                'MARIE ROSE P. RAMOS' => DocumentSignatory::resolve('school_head') ?? 'MARIE ROSE P. RAMOS',
                'CHERYL R. RAMIRO' => DocumentSignatory::resolve('assistant_sds') ?? 'CHERYL R. RAMIRO',
            ];

        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            $cell = $sheet->getCell($coordinate);
            $value = $cell->getValue();
            if (! is_string($value) || $value === '') {
                continue;
            }

            $updated = str_ireplace(array_keys($replacements), array_values($replacements), $value);
            if ($updated !== $value) {
                $cell->setValue($updated);
            }
        }
    }

    /**
     * Write Last / First / Middle under the printed E4 labels on FORM 6.
     * Keeps template merge E5:I5 and column widths intact.
     *
     * @param  array{last:string,first:string,middle:string}  $parts
     */
    protected function writeNameParts(Worksheet $sheet, array $parts): void
    {
        // Ensure the official name value area stays merged (template default).
        $hasNameMerge = false;
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            if (preg_match('/^E5:/i', $range)) {
                $hasNameMerge = true;
                break;
            }
        }
        if (! $hasNameMerge) {
            $sheet->mergeCells('E5:I5');
        }

        $labelRow = (string) $sheet->getCell('E4')->getValue();
        $lastAt = strpos($labelRow, '(Last)');
        $firstAt = strpos($labelRow, '(First)');
        $middleAt = strpos($labelRow, '(Middle)');

        // Fallback positions from the stock FORM 6 label text.
        if ($lastAt === false) {
            $lastAt = 22;
        }
        if ($firstAt === false) {
            $firstAt = 59;
        }
        if ($middleAt === false) {
            $middleAt = 91;
        }

        $width = max(strlen($labelRow), $middleAt + 20, 99);
        $slots = array_fill(0, $width, ' ');

        $this->placeNameSegment($slots, strtoupper($parts['last']), $lastAt, $firstAt);
        $this->placeNameSegment($slots, strtoupper($parts['first']), $firstAt, $middleAt);
        $this->placeNameSegment($slots, strtoupper($parts['middle']), $middleAt, $width);

        $sheet->setCellValue('E5', rtrim(implode('', $slots)));
        $sheet->getStyle('E5')->getAlignment()->setHorizontal(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
        );
        $sheet->getStyle('E5')->getAlignment()->setVertical(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        );
        // Match label row font so spacing tracks the (Last)/(First)/(Middle) marks.
        $sheet->getStyle('E5')->getFont()->setName('Arial')->setSize(10);
    }

    /**
     * Overlay a name part into a character buffer without crossing the next label zone.
     *
     * @param  list<string>  $slots
     */
    protected function placeNameSegment(array &$slots, string $text, int $start, int $nextZone): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $maxLen = max(0, $nextZone - $start - 1);
        if ($maxLen > 0 && strlen($text) > $maxLen) {
            $text = substr($text, 0, $maxLen);
        }

        $chars = str_split($text);
        foreach ($chars as $i => $ch) {
            $pos = $start + $i;
            if ($pos >= count($slots)) {
                break;
            }
            $slots[$pos] = $ch;
        }
    }

    /**
     * Split a display name into Last / First / Middle.
     *
     * @return array{last:string,first:string,middle:string}
     */
    protected function splitName(string $fullName): array
    {
        return \App\Models\StaffUser::splitDisplayName($fullName);
    }

    /** @deprecated Use splitName() */
    protected function formatName(string $fullName): string
    {
        $parts = $this->splitName($fullName);
        if ($parts['last'] === '') {
            return '';
        }

        return $parts['last']
            .($parts['first'] !== '' ? ', '.$parts['first'] : '')
            .($parts['middle'] !== '' ? ' '.$parts['middle'] : '');
    }
}
