<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Services\DtrExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ])->render();

        $this->assertStringContainsString('CIVIL SERVICE FORM NO. 48', $html);
        $this->assertStringContainsString('DAILY TIME RECORD', $html);
        $this->assertStringContainsString('OVERTIME', $html);
        $this->assertStringContainsString('TOTAL :', $html);
        $this->assertStringContainsString('Aiza T Donor', $html);
        $this->assertStringContainsString('(In Charge)', $html);
        $this->assertStringContainsString('D<br>A<br>Y', $html);
        $this->assertEquals(2, substr_count($html, 'td class="form"'));
    }
}
