<?php

namespace Tests\Feature;

use App\Models\LocatorRequest;
use App\Models\Personnel;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocatorPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_print_an_approved_locator_slip_as_docx(): void
    {
        $staff = $this->employee('germilyn.locator@example.test', '00-301');
        $approved = $this->locator($staff, 'approved');
        $pending = $this->locator($staff, 'pending');

        $download = $this->actingAs($staff, 'staff')
            ->get(route('staff.locator.print', $approved));

        $download->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $download->headers->get('content-type')
        );
        $this->assertStringContainsString('.docx', (string) $download->headers->get('content-disposition'));

        $file = $download->baseResponse->getFile()->getPathname();
        $this->assertStringStartsWith('PK', (string) file_get_contents($file));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('City of Ilagan', $xml);
        $this->assertStringContainsString('ANGEL', $xml);
        // Duplicate blank slip (second LOCATOR SLIP block) must be removed for print.
        $this->assertSame(1, substr_count($xml, 'LOCATOR SLIP'));
        $zip->close();

        $this->actingAs($staff, 'staff')
            ->get(route('staff.locator.print', $pending))
            ->assertForbidden();
    }

    protected function employee(string $email, string $employeeNumber): StaffUser
    {
        $personnel = Personnel::create([
            'name' => 'Germilyn C Angel',
            'role' => 'TCH III',
            'employee_number' => $employeeNumber,
        ]);

        return StaffUser::create([
            'personnel_id' => $personnel->id,
            'name' => 'Germilyn C Angel',
            'last_name' => 'Angel',
            'first_name' => 'Germilyn',
            'middle_name' => 'C',
            'email' => $email,
            'password' => 'Password123!',
            'role' => 'teacher',
            'status' => 'active',
        ]);
    }

    protected function locator(StaffUser $staff, string $status): LocatorRequest
    {
        return LocatorRequest::create([
            'staff_user_id' => $staff->id,
            'personnel_id' => $staff->personnel_id,
            'position_designation' => 'Teacher III',
            'date' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'time_out' => '08:00:00',
            'time_in_expected' => '17:00:00',
            'destination' => 'City of Ilagan',
            'purpose' => 'Division training',
            'nature' => 'Official Business',
            'status' => $status,
            'reviewed_at' => $status === 'approved' ? now() : null,
        ]);
    }
}
