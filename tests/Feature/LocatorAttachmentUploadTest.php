<?php

namespace Tests\Feature;

use App\Models\LocatorRequest;
use App\Models\Personnel;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocatorAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_submit_locator_slip_with_docx_attachment(): void
    {
        Storage::fake('local');

        $personnel = Personnel::create([
            'name' => 'Locator Tester',
            'role' => 'Teacher',
            'employee_number' => '99-901',
        ]);

        $staff = StaffUser::create([
            'personnel_id' => $personnel->id,
            'name' => 'Locator Tester',
            'last_name' => 'Tester',
            'first_name' => 'Locator',
            'email' => 'locator.attach@example.test',
            'password' => 'Password123!',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create(
            'Web_UX_Design.docx',
            120,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $this->actingAs($staff, 'staff')
            ->post(route('staff.locator.store'), [
                'position_designation' => 'Teacher II',
                'purpose' => 'Official business in Ilagan',
                'nature' => 'Official Business',
                'date' => now()->toDateString(),
                'date_to' => now()->toDateString(),
                'time_out' => '12:00',
                'time_in_expected' => '17:00:00',
                'destination' => 'ilagan',
                'attachment' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $request = LocatorRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame('ilagan', $request->destination);
        $this->assertSame('pending', $request->status);
        $this->assertNotNull($request->attachment_path);
        Storage::disk('local')->assertExists($request->attachment_path);
    }
}
