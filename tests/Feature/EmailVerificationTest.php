<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\StaffUser;
use App\Services\EmailVerificationService;
use App\Services\PythonFaceEnrollmentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_code_and_requires_verification_before_hr(): void
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
            'email' => 'verify.me@example.test',
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

        $staff = StaffUser::query()->where('email', 'verify.me@example.test')->firstOrFail();
        $this->assertNull($staff->email_verified_at);
        $this->assertSame(0, StaffUser::awaitingHrApproval()->count());

        $code = null;
        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->assertNotNull($code);

        $this->post(route('staff.verify-email'), [
            'email' => 'verify.me@example.test',
            'code' => $code,
        ])->assertRedirect(route('login'));

        $staff->refresh();
        $this->assertNotNull($staff->email_verified_at);
        $this->assertSame(1, StaffUser::awaitingHrApproval()->count());
    }

    public function test_wrong_code_is_rejected(): void
    {
        Mail::fake();
        $staff = StaffUser::create([
            'name' => 'Test Staff',
            'last_name' => 'Staff',
            'first_name' => 'Test',
            'middle_name' => 'A',
            'email' => 'wrong.code@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'pending',
        ]);

        app(EmailVerificationService::class)->sendCode($staff);

        $this->from(route('staff.verify-email'))
            ->post(route('staff.verify-email'), [
                'email' => 'wrong.code@example.test',
                'code' => '000000',
            ])
            ->assertRedirect(route('staff.verify-email'))
            ->assertSessionHasErrors('code');

        $this->assertNull($staff->fresh()->email_verified_at);
    }

    public function test_unverified_staff_cannot_sign_in(): void
    {
        StaffUser::create([
            'name' => 'Unverified Staff',
            'last_name' => 'Staff',
            'first_name' => 'Unverified',
            'middle_name' => 'A',
            'email' => 'unverified@example.test',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $this->post(route('login'), [
            'username' => 'unverified@example.test',
            'password' => 'Password123!',
        ])->assertRedirect(route('staff.verify-email'));

        $this->assertGuest('staff');
    }
}
