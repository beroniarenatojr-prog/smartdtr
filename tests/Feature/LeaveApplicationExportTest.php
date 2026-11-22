<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Personnel;
use App\Models\StaffUser;
use App\Services\LeaveApplicationExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LeaveApplicationExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('leave_requests') && ! Schema::hasTable('leave_requests_data')) {
            Schema::rename('leave_requests', 'leave_requests_data');
        }
    }

    public function test_leave_type_checkmark_is_written_in_column_c(): void
    {
        if (! is_file(base_path('FORM 6.xlsx'))) {
            $this->markTestSkipped('FORM 6.xlsx template missing');
        }

        $personnel = Personnel::create([
            'name' => 'Juan Perez Dela Cruz',
            'role' => 'Teacher',
            'employee_number' => 'EMP-LV-1',
            'is_active' => true,
        ]);

        $staff = StaffUser::create([
            'personnel_id' => $personnel->id,
            'name' => 'Juan Perez Dela Cruz',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Perez',
            'email' => 'leave.form@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::create([
            'staff_user_id' => $staff->id,
            'personnel_id' => $personnel->id,
            'leave_type' => 'Sick Leave',
            'reason' => 'Fever',
            'leave_details' => 'Fever',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $path = app(LeaveApplicationExportService::class)->generateExcel($leave);
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('☑', (string) $sheet->getCell('C15')->getValue());
        $this->assertSame('☐', (string) $sheet->getCell('C11')->getValue());
        $this->assertStringContainsString((string) $leave->dayCount(), (string) $sheet->getCell('B45')->getValue());
    }

    public function test_leave_dom_pdf_fallback_renders_form_6(): void
    {
        $personnel = Personnel::create([
            'name' => 'Juan Perez Dela Cruz',
            'role' => 'Teacher',
            'employee_number' => 'EMP-LV-2',
            'is_active' => true,
        ]);

        $staff = StaffUser::create([
            'personnel_id' => $personnel->id,
            'name' => 'Juan Perez Dela Cruz',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Perez',
            'email' => 'leave.pdf@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $leave = LeaveRequest::create([
            'staff_user_id' => $staff->id,
            'personnel_id' => $personnel->id,
            'leave_type' => 'Vacation Leave',
            'reason' => 'Family trip',
            'leave_details' => 'Family trip',
            'date_from' => now()->toDateString(),
            'date_to' => now()->addDays(4)->toDateString(),
            'status' => 'approved',
        ]);

        $path = app(LeaveApplicationExportService::class)->generateDomPdf(
            $leave,
            storage_path('app/exports/test-leave-form6.pdf')
        );

        $this->assertFileExists($path);
        $this->assertGreaterThan(1500, filesize($path));
    }
}
