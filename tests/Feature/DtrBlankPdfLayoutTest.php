<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Services\DtrExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class DtrBlankPdfLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_layout_pdf_matches_form_48_structure(): void
    {
        $person = Personnel::create([
            'name' => 'Aiza T Donor',
            'role' => 'Teacher',
            'employee_number' => 'EMP-DTR-1',
            'is_active' => true,
        ]);

        $path = app(DtrExportService::class)->generateBlankLayoutPdf(
            now()->format('Y-m'),
            storage_path('app/exports/test-dtr-blank.pdf'),
            collect([$person])
        );

        $this->assertFileExists($path);
        $this->assertGreaterThan(2000, filesize($path));

        $html = view('reports.dtr-blank-pdf', [
            'records' => [[
                'personnel' => $person,
                'dayMap' => app(DtrExportService::class)->buildMonthlyDayMap($person->id, now()->format('Y-m')),
                'regular' => '7:30-11:30 / 1:00-5:00',
                'saturday' => 'as required',
            ]],
            'monthLabel' => 'For the month of '.strtoupper(now()->format('F Y')),
            'daysInMonth' => now()->daysInMonth,
            'verifier' => 'MARIE ROSE P. RAMOS',
            'layout' => 'copies',
        ])->render();

        $this->assertStringContainsString('CIVIL SERVICE FORM NO. 48', $html);
        $this->assertStringContainsString('DAILY TIME RECORD', $html);
        $this->assertStringContainsString('OVERTIME', $html);
        $this->assertStringContainsString('TOTAL :', $html);
        $this->assertStringContainsString('Aiza T Donor', $html);
        $this->assertStringContainsString('(In Charge)', $html);
        $this->assertStringContainsString('D<br>A<br>Y', $html);
        $this->assertStringContainsString('Official hours of arrival', $html);
        $this->assertEquals(4, substr_count($html, 'class="copy"'));
    }

    public function test_bulk_pdf_is_written_without_excel_when_conversion_is_unavailable(): void
    {
        Personnel::create([
            'name' => 'Aiza T Donor',
            'role' => 'Teacher',
            'employee_number' => 'EMP-DTR-2',
            'is_active' => true,
        ]);

        $service = new class extends DtrExportService
        {
            protected function excelPdfConversionAvailable(): bool
            {
                return false;
            }
        };

        $path = $service->generateBulkPdf(now()->format('Y-m'));

        $this->assertFileExists($path);
        $this->assertGreaterThan(2000, filesize($path));
        $this->assertStringEndsWith('.pdf', $path);
    }

    public function test_bulk_pdf_joins_more_employees_than_one_chunk(): void
    {
        foreach (range(1, 5) as $n) {
            Personnel::create([
                'name' => 'Employee '.$n,
                'role' => 'Teacher',
                'employee_number' => 'EMP-DTR-CHUNK-'.$n,
                'is_active' => true,
            ]);
        }

        $service = new class extends DtrExportService
        {
            protected function excelPdfConversionAvailable(): bool
            {
                return false;
            }
        };

        $path = $service->generateBulkPdf(now()->format('Y-m'));

        $this->assertFileExists($path);
        $this->assertGreaterThan(5000, filesize($path));
    }

    public function test_excel_export_uses_the_blank_form_48_workbook(): void
    {
        $person = Personnel::create([
            'name' => 'Aiza T Donor',
            'role' => 'Teacher',
            'employee_number' => 'EMP-DTR-XLSX',
            'is_active' => true,
        ]);

        $path = app(DtrExportService::class)->generateExcel($person, '2026-11');
        $sheet = IOFactory::load($path)->getSheet(0);

        $this->assertSame('Aiza T Donor', $sheet->getCell('A3')->getValue());
        $this->assertSame('Aiza T Donor', $sheet->getCell('Q3')->getValue());
        $this->assertSame('Aiza T Donor', $sheet->getCell('Y3')->getValue());
        $this->assertSame('For the month of NOVEMBER 2026', $sheet->getCell('A5')->getValue());
        $this->assertSame('Official hours of arrival', trim((string) $sheet->getCell('A6')->getValue()));
        $this->assertSame('and departure', trim((string) $sheet->getCell('A7')->getValue()));
        $this->assertSame('CIVIL SERVICE FORM NO. 48', $sheet->getCell('Q1')->getValue());
        $this->assertSame('(In Charge)', $sheet->getCell('A51')->getValue());
    }

    public function test_bulk_workbook_can_include_more_than_one_employee(): void
    {
        foreach (['Ana Cruz', 'Ben Cruz'] as $index => $name) {
            Personnel::create([
                'name' => $name,
                'role' => 'Teacher',
                'employee_number' => 'EMP-DTR-BULK-'.$index,
                'is_active' => true,
            ]);
        }

        $path = app(DtrExportService::class)->generateBulkExcel('2026-11');
        $workbook = IOFactory::load($path);

        $this->assertSame(2, $workbook->getSheetCount());
        $this->assertSame('Ana Cruz', $workbook->getSheet(0)->getCell('A3')->getValue());
        $this->assertSame('Ben Cruz', $workbook->getSheet(1)->getCell('A3')->getValue());
    }

    public function test_download_all_prints_each_employee_on_the_blank_form_48_page(): void
    {
        foreach (['Ana Cruz', 'Ben Cruz', 'Cara Cruz', 'Dina Cruz', 'Eva Cruz'] as $index => $name) {
            Personnel::create([
                'name' => $name,
                'role' => 'Teacher',
                'employee_number' => 'EMP-DTR-PAGE-'.$index,
                'is_active' => true,
            ]);
        }

        $path = app(DtrExportService::class)->generateBulkPdf('2026-11');
        $pdf = new Fpdi();
        $pages = $pdf->setSourceFile($path);
        $size = $pdf->getTemplateSize($pdf->importPage(1));
        $last = $pdf->getTemplateSize($pdf->importPage($pages));

        $this->assertSame(5, $pages);
        foreach ([$size, $last] as $pageSize) {
            $this->assertGreaterThan($pageSize['height'], $pageSize['width']);
            $this->assertEqualsWithDelta(330.2, $pageSize['width'], 2);
            $this->assertEqualsWithDelta(215.9, $pageSize['height'], 2);
        }
    }
}
