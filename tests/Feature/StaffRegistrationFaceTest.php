<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\AdminUser;
use App\Models\Personnel;
use App\Models\StaffUser;
use App\Services\FaceSampleEnrollmentService;
use App\Services\PythonFaceEnrollmentValidator;
use App\Services\RegistrationSchemaRepair;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class StaffRegistrationFaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_includes_face_capture(): void
    {
        $this->get(route('staff.register'))
            ->assertOk()
            ->assertSee('Facial recognition')
            ->assertSee('Blink three times')
            ->assertSee('Start Camera')
            ->assertSee('Capture your face to register');
    }

    public function test_registration_requires_three_face_captures(): void
    {
        $this->post(route('staff.register'), $this->account())
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('staff_users', 0);
    }

    public function test_registration_saves_face_samples_until_hr_approves(): void
    {
        Mail::fake();
        Storage::fake('faces');
        $this->mockValidator();

        $this->post(route('staff.register'), $this->account([
            'images' => $this->captures(),
            'blink_verifications' => ['1', '1', '1'],
        ]))->assertRedirect(route('staff.verify-email'));

        $staff = StaffUser::query()->where('email', 'juan.face@example.test')->first();
        $this->assertNotNull($staff);
        $this->assertSame('pending', $staff->status);
        $this->assertNull($staff->email_verified_at);
        $this->assertNotNull($staff->personnel_id);

        $personnel = Personnel::find($staff->personnel_id);
        $this->assertFalse($personnel->is_active);
        $this->assertCount(3, Storage::disk('faces')->files((string) $personnel->id));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'face.registered_with_account',
            'subject_id' => $personnel->id,
        ]);

        $code = null;
        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->post(route('staff.verify-email'), [
            'email' => 'juan.face@example.test',
            'code' => $code,
        ])->assertRedirect(route('login'));

        $staff->refresh();
        $this->assertNotNull($staff->email_verified_at);

        $admin = AdminUser::create([
            'username' => 'face-hr',
            'email' => 'face-hr@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.hr.staff.activate', $staff))
            ->assertRedirect();

        $personnel->refresh();
        $staff->refresh();
        $this->assertSame('active', $staff->status);
        $this->assertTrue($personnel->is_active);
        $this->assertCount(3, Storage::disk('faces')->files((string) $personnel->id));
    }

    public function test_rejecting_registration_removes_the_pending_face_samples(): void
    {
        Mail::fake();
        Storage::fake('faces');
        $this->mockValidator();

        $this->post(route('staff.register'), $this->account([
            'email' => 'rejected.face@example.test',
            'images' => $this->captures(),
            'blink_verifications' => ['1', '1', '1'],
        ]));

        $staff = StaffUser::query()->where('email', 'rejected.face@example.test')->firstOrFail();
        $personnelId = $staff->personnel_id;

        $admin = AdminUser::create([
            'username' => 'face-hr',
            'email' => 'face-hr@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.hr.staff.reject', $staff))
            ->assertRedirect();

        $this->assertNull($staff->fresh()->personnel_id);
        $this->assertNull(Personnel::find($personnelId));
        $this->assertSame([], Storage::disk('faces')->allFiles());
    }

    public function test_database_error_during_registration_does_not_show_sql_to_the_user(): void
    {
        Mail::fake();
        Storage::fake('faces');
        $this->mock(FaceSampleEnrollmentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('saveThreeSamples')->once()->andThrow(new QueryException(
                'mysql',
                'insert into `staff_users` (`email`, `password`) values (?, ?)',
                ['juan.face@example.test', '$2y$12$secrethash'],
                new \PDOException("SQLSTATE[HY000]: General error: 1364 Field 'id' doesn't have a default value")
            ));
        });

        $response = $this->postJson(route('staff.register'), $this->account([
            'images' => $this->captures(),
            'blink_verifications' => ['1', '1', '1'],
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Registration could not be saved because of a server problem. Please try again later or contact HR.');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('secrethash', $response->getContent());
        $this->assertDatabaseCount('staff_users', 0);
        $this->assertDatabaseCount('personnel', 0);
    }

    public function test_registration_retries_after_the_id_column_is_repaired(): void
    {
        Storage::fake('faces');

        $this->mock(RegistrationSchemaRepair::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isRepairable')->once()->andReturn(true);
            $mock->shouldReceive('repair')->once()->andReturn(['staff_users', 'personnel']);
        });

        $calls = 0;
        $this->mock(FaceSampleEnrollmentService::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('saveThreeSamples')->twice()->andReturnUsing(function () use (&$calls) {
                if (++$calls === 1) {
                    throw new QueryException(
                        'mysql',
                        'insert into `staff_users` (`email`) values (?)',
                        ['juan.face@example.test'],
                        new \PDOException("SQLSTATE[HY000]: General error: 1364 Field 'id' doesn't have a default value")
                    );
                }

                return [
                    'ok' => true,
                    'message' => 'Three compatible face encodings verified.',
                    'encodings' => 3,
                    'max_distance' => 0.28,
                ];
            });
        });

        $this->postJson(route('staff.register'), $this->account([
            'images' => $this->captures(),
            'blink_verifications' => ['1', '1', '1'],
        ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(2, $calls);
        $this->assertDatabaseCount('staff_users', 1);
        $this->assertDatabaseCount('personnel', 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function account(array $overrides = []): array
    {
        return array_merge([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Perez',
            'email' => 'juan.face@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'department' => 'Science',
        ], $overrides);
    }

    /**
     * @return array<int, UploadedFile>
     */
    protected function captures(): array
    {
        return [
            UploadedFile::fake()->image('capture-1.jpg', 640, 480),
            UploadedFile::fake()->image('capture-2.jpg', 640, 480),
            UploadedFile::fake()->image('capture-3.jpg', 640, 480),
        ];
    }

    protected function mockValidator(): void
    {
        $this->mock(PythonFaceEnrollmentValidator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('validate')->once()->andReturn([
                'ok' => true,
                'message' => 'Three compatible face encodings verified.',
                'encodings' => 3,
                'max_distance' => 0.28,
            ]);
        });
    }
}
