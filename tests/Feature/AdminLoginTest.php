<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AdminUser::create([
            'username' => 'admin',
            'email' => 'admin@inhs.edu.ph',
            'password' => 'Admin@123',
            'role' => 'super_admin',
        ]);
    }

    public function test_admin_can_log_in_with_username(): void
    {
        $this->post(route('login'), ['username' => 'admin', 'password' => 'Admin@123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated('admin');
    }

    public function test_admin_can_log_in_with_email(): void
    {
        $this->post(route('login'), ['username' => ' admin@inhs.edu.ph ', 'password' => 'Admin@123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated('admin');
    }

    public function test_staff_can_sign_in_from_unified_login(): void
    {
        StaffUser::create([
            'name' => 'Test Employee',
            'last_name' => 'Employee',
            'first_name' => 'Test',
            'middle_name' => 'A',
            'email' => 'employee@gmail.com',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->post(route('login'), ['username' => 'employee@gmail.com', 'password' => 'Password123!'])
            ->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticated('staff');
        $this->assertGuest('admin');
    }

    public function test_pending_staff_cannot_sign_in(): void
    {
        StaffUser::create([
            'name' => 'Pending Staff',
            'last_name' => 'Staff',
            'first_name' => 'Pending',
            'middle_name' => 'A',
            'email' => 'pending@gmail.com',
            'password' => 'Password123!',
            'role' => 'employee',
            'status' => 'pending',
        ]);

        $this->from(route('login'))
            ->post(route('login'), ['username' => 'pending@gmail.com', 'password' => 'Password123!'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username']);

        $this->assertGuest('staff');
    }

    public function test_staff_login_page_redirects_to_unified_sign_in(): void
    {
        $this->get(route('staff.login'))->assertRedirect(route('login'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->from(route('login'))
            ->post(route('login'), ['username' => 'admin@inhs.edu.ph', 'password' => 'wrong'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => 'Invalid credentials.']);

        $this->assertGuest('admin');
    }
}
