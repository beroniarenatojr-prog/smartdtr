<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\EmailChangeRequest;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_request_gmail_change(): void
    {
        $staff = $this->makeStaff('old.mail@gmail.com');

        $this->actingAs($staff, 'staff')
            ->post(route('staff.profile.email.request'), [
                'new_email' => 'new.mail@gmail.com',
                'new_email_confirmation' => 'new.mail@gmail.com',
            ])
            ->assertRedirect(route('staff.profile'));

        $this->assertDatabaseHas('email_change_requests', [
            'staff_user_id' => $staff->id,
            'current_email' => 'old.mail@gmail.com',
            'new_email' => 'new.mail@gmail.com',
            'status' => 'pending',
        ]);
        $this->assertSame('old.mail@gmail.com', $staff->fresh()->email);
    }

    public function test_staff_cannot_have_two_pending_email_changes(): void
    {
        $staff = $this->makeStaff('old.mail@gmail.com');

        EmailChangeRequest::create([
            'staff_user_id' => $staff->id,
            'current_email' => 'old.mail@gmail.com',
            'new_email' => 'first@gmail.com',
            'status' => 'pending',
        ]);

        $this->actingAs($staff, 'staff')
            ->post(route('staff.profile.email.request'), [
                'new_email' => 'second@gmail.com',
                'new_email_confirmation' => 'second@gmail.com',
            ])
            ->assertSessionHasErrors('new_email');

        $this->assertSame(1, EmailChangeRequest::where('staff_user_id', $staff->id)->count());
    }

    public function test_admin_can_approve_email_change(): void
    {
        $staff = $this->makeStaff('old.mail@gmail.com');
        $request = EmailChangeRequest::create([
            'staff_user_id' => $staff->id,
            'current_email' => 'old.mail@gmail.com',
            'new_email' => 'approved@gmail.com',
            'status' => 'pending',
        ]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.hr.email-changes.approve', $request))
            ->assertRedirect();

        $this->assertSame('approved@gmail.com', $staff->fresh()->email);
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_admin_can_reject_email_change(): void
    {
        $staff = $this->makeStaff('old.mail@gmail.com');
        $request = EmailChangeRequest::create([
            'staff_user_id' => $staff->id,
            'current_email' => 'old.mail@gmail.com',
            'new_email' => 'rejected@gmail.com',
            'status' => 'pending',
        ]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.hr.email-changes.reject', $request), [
                'admin_notes' => 'Use school Gmail only.',
            ])
            ->assertRedirect();

        $this->assertSame('old.mail@gmail.com', $staff->fresh()->email);
        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_new_email_must_be_unique(): void
    {
        $this->makeStaff('taken@gmail.com');
        $staff = $this->makeStaff('mine@gmail.com', 'Other Person');

        $this->actingAs($staff, 'staff')
            ->post(route('staff.profile.email.request'), [
                'new_email' => 'taken@gmail.com',
                'new_email_confirmation' => 'taken@gmail.com',
            ])
            ->assertSessionHasErrors('new_email');
    }

    protected function makeStaff(string $email, string $name = 'Test Staff'): StaffUser
    {
        return StaffUser::create([
            'name' => $name,
            'last_name' => 'Staff',
            'first_name' => 'Test',
            'middle_name' => 'A',
            'email' => $email,
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    protected function makeAdmin(): AdminUser
    {
        return AdminUser::create([
            'username' => 'hr-email',
            'email' => 'hr.email@example.test',
            'password' => 'Password123!',
            'role' => 'super_admin',
        ]);
    }
}
