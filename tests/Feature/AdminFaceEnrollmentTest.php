<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Personnel;
use App\Services\PythonFaceEnrollmentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminFaceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_camera_enrollment_page(): void
    {
        $admin = AdminUser::create([
            'username' => 'camera-admin',
            'email' => 'camera-admin@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);
        $personnel = Personnel::create([
            'name' => 'Camera Employee',
            'role' => 'Employee',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.hr.faces.enroll', $personnel))
            ->assertOk()
            ->assertSee('three separate blink-verified captures')
            ->assertSee('Enrolling Camera Employee')
            ->assertSee('The person in this camera must be')
            ->assertSee('0 / 3 saved');
    }

    public function test_enrollment_requires_exactly_three_verified_images(): void
    {
        Storage::fake('faces');
        $admin = AdminUser::create([
            'username' => 'camera-admin',
            'email' => 'camera-admin@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);
        $personnel = Personnel::create([
            'name' => 'Camera Employee',
            'role' => 'Employee',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.hr.faces.store', $personnel), [
                'images' => [
                    UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                ],
                'blink_verifications' => ['1', '1'],
            ])
            ->assertSessionHasErrors(['images', 'blink_verifications']);

        $this->assertSame([], Storage::disk('faces')->allFiles((string) $personnel->id));
    }

    public function test_three_verified_captures_replace_the_old_face_dataset(): void
    {
        Storage::fake('faces');
        $admin = AdminUser::create([
            'username' => 'camera-admin',
            'email' => 'camera-admin@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);
        $personnel = Personnel::create([
            'name' => 'Camera Employee',
            'role' => 'Employee',
            'is_active' => true,
        ]);
        Storage::disk('faces')->put($personnel->id.'/old.jpg', 'old face');
        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('validate')->once()->andReturn([
                'ok' => true,
                'message' => 'Three compatible face encodings verified.',
                'encodings' => 3,
                'max_distance' => 0.31,
            ]);
        });

        $this->actingAs($admin, 'admin')
            ->post(route('admin.hr.faces.store', $personnel), [
                'images' => [
                    UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-3.jpg', 640, 480),
                ],
                'blink_verifications' => ['1', '1', '1'],
            ])
            ->assertRedirect(route('admin.hr.faces'));

        $this->assertFalse(Storage::disk('faces')->exists($personnel->id.'/old.jpg'));
        $this->assertCount(3, Storage::disk('faces')->files((string) $personnel->id));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'face.camera_enrolled',
            'subject_type' => 'Personnel',
            'subject_id' => $personnel->id,
        ]);
    }

    public function test_json_enrollment_returns_the_faces_list_redirect(): void
    {
        Storage::fake('faces');
        $admin = AdminUser::create([
            'username' => 'camera-admin',
            'email' => 'camera-admin@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);
        $personnel = Personnel::create([
            'name' => 'Camera Employee',
            'role' => 'Employee',
            'is_active' => true,
        ]);
        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('validate')->once()->andReturn([
                'ok' => true,
                'message' => 'Three compatible face encodings verified.',
                'encodings' => 3,
                'max_distance' => 0.31,
            ]);
        });

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.hr.faces.store', $personnel), [
                'images' => [
                    UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-3.jpg', 640, 480),
                ],
                'blink_verifications' => ['1', '1', '1'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', route('admin.hr.faces'));

        $this->assertCount(3, Storage::disk('faces')->files((string) $personnel->id));
    }

    public function test_json_enrollment_keeps_the_python_error_on_the_page(): void
    {
        Storage::fake('faces');
        $admin = AdminUser::create([
            'username' => 'camera-admin',
            'email' => 'camera-admin@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);
        $personnel = Personnel::create([
            'name' => 'Camera Employee',
            'role' => 'Employee',
            'is_active' => true,
        ]);
        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('validate')->once()->andThrow(
                new \RuntimeException('Capture 1 is too dark. Improve the lighting and try again.')
            );
        });

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.hr.faces.store', $personnel), [
                'images' => [
                    UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-3.jpg', 640, 480),
                ],
                'blink_verifications' => ['1', '1', '1'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Capture 1 is too dark. Improve the lighting and try again.');

        $this->assertSame([], Storage::disk('faces')->allFiles((string) $personnel->id));
    }

    public function test_html_enrollment_shows_the_python_error_after_redirect_back(): void
    {
        Storage::fake('faces');
        $admin = AdminUser::create([
            'username' => 'camera-admin',
            'email' => 'camera-admin@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);
        $personnel = Personnel::create([
            'name' => 'Camera Employee',
            'role' => 'Employee',
            'is_active' => true,
        ]);
        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('validate')->once()->andThrow(
                new \RuntimeException('This face is already enrolled as Another Employee.')
            );
        });

        $this->actingAs($admin, 'admin')
            ->from(route('admin.hr.faces.enroll', $personnel))
            ->post(route('admin.hr.faces.store', $personnel), [
                'images' => [
                    UploadedFile::fake()->image('capture-1.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-2.jpg', 640, 480),
                    UploadedFile::fake()->image('capture-3.jpg', 640, 480),
                ],
                'blink_verifications' => ['1', '1', '1'],
            ])
            ->assertRedirect(route('admin.hr.faces.enroll', $personnel))
            ->assertSessionHas('error', 'This face is already enrolled as Another Employee.');
    }
}
