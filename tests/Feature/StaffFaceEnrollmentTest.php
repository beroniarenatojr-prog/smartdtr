<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\StaffUser;
use App\Services\PythonFaceEnrollmentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class StaffFaceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_staff_can_open_self_enrollment_page(): void
    {
        $personnel = Personnel::create([
            'name' => 'Phone Employee',
            'role' => 'Teacher I',
            'is_active' => true,
        ]);
        $staff = StaffUser::create([
            'personnel_id' => $personnel->id,
            'name' => 'Phone Employee',
            'email' => 'phone-employee@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.faces'))
            ->assertOk()
            ->assertSee('Register My Face')
            ->assertSee('Phone Employee')
            ->assertSee('0 / 3 saved');
    }

    public function test_unlinked_staff_sees_link_required_message(): void
    {
        $staff = StaffUser::create([
            'name' => 'Unlinked Employee',
            'email' => 'unlinked@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.faces'))
            ->assertOk()
            ->assertSee('not linked to a personnel record');
    }

    public function test_staff_can_self_enroll_three_blink_captures(): void
    {
        Storage::fake('faces');
        $personnel = Personnel::create([
            'name' => 'Phone Employee',
            'role' => 'Teacher I',
            'is_active' => true,
        ]);
        $staff = StaffUser::create([
            'personnel_id' => $personnel->id,
            'name' => 'Phone Employee',
            'email' => 'phone-employee@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('validate')->once()->andReturn([
                'ok' => true,
                'message' => 'Three compatible face encodings verified.',
                'encodings' => 3,
                'max_distance' => 0.28,
            ]);
        });

        $this->actingAs($staff, 'staff')
            ->postJson(route('staff.faces.store'), [
                'images' => [
                    UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-3.jpg', 640, 480),
                ],
                'blink_verifications' => ['1', '1', '1'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', route('staff.faces'));

        $this->assertCount(3, Storage::disk('faces')->files((string) $personnel->id));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'face.staff_self_enrolled',
            'subject_type' => 'Personnel',
            'subject_id' => $personnel->id,
        ]);
    }

    public function test_unlinked_staff_cannot_store_face_samples(): void
    {
        Storage::fake('faces');
        $staff = StaffUser::create([
            'name' => 'Unlinked Employee',
            'email' => 'unlinked2@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'staff')
            ->postJson(route('staff.faces.store'), [
                'images' => [
                    UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-3.jpg', 640, 480),
                ],
                'blink_verifications' => ['1', '1', '1'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame([], Storage::disk('faces')->allFiles());
    }
}
