<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Services\FaceSampleEnrollmentService;
use App\Services\PythonFaceEnrollmentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class FaceEnrollmentWithoutPythonTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_saves_faces_when_python_environment_is_missing(): void
    {
        Mail::fake();
        Storage::fake('faces');

        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(false);
            $mock->shouldReceive('validate')->never();
        });

        $this->post(route('staff.register'), [
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Perez',
            'email' => 'no.python@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'department' => 'Science',
            'images' => [
                UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                UploadedFile::fake()->image('capture-3.jpg', 640, 480),
            ],
            'blink_verifications' => ['1', '1', '1'],
        ])->assertRedirect(route('staff.verify-email'));

        $personnel = Personnel::query()->where('name', 'Juan Perez Dela Cruz')->first();
        $this->assertNotNull($personnel);
        $this->assertCount(3, Storage::disk('faces')->files((string) $personnel->id));
    }

    public function test_save_three_samples_skips_python_when_unavailable(): void
    {
        Storage::fake('faces');

        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->once()->andReturn(false);
            $mock->shouldReceive('validate')->never();
        });

        $personnel = Personnel::create([
            'name' => 'Skip Python',
            'role' => 'Employee',
            'employee_number' => 'EMP-SKIP-1',
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        $result = app(FaceSampleEnrollmentService::class)->saveThreeSamples(
            $personnel,
            [
                UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                UploadedFile::fake()->image('capture-3.jpg', 640, 480),
            ],
            'face.registered_with_account',
            'saved without python',
            false
        );

        $this->assertTrue($result['ok']);
        $this->assertCount(3, Storage::disk('faces')->files((string) $personnel->id));
    }
}
