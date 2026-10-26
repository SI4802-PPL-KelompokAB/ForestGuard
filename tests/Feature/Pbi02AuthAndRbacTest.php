<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Pbi02AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // --- REGISTRATION TESTS (UC-02) ---

    public function test_registration_page_can_be_rendered_with_tab_toggle(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Daftar sebagai warga');
        $response->assertSee('Masuk');
    }

    public function test_citizen_can_register_successfully_and_is_auto_logged_in(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Warga Baru',
            'email' => 'wargabaru@forestguard.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('citizen.home'));
        $this->assertAuthenticated();

        $user = User::where('email', 'wargabaru@forestguard.test')->firstOrFail();
        $this->assertEquals('Warga Baru', $user->name);
        $this->assertTrue($user->isCitizen());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    public function test_registration_validation_fails_for_invalid_data_and_preserves_old_input(): void
    {
        // 1. Name < 3 characters
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Ab',
            'email' => 'valid@forestguard.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['name']);

        // 2. Duplicate email
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Warga Duplikat',
            'email' => 'citizen1@forestguard.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['email']);

        // 3. Password < 8 characters
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Warga Pendek',
            'email' => 'pendek@forestguard.test',
            'password' => 'Pass1!',
            'password_confirmation' => 'Pass1!',
        ]);
        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['password']);

        // 4. Password confirmation mismatch
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Warga Beda',
            'email' => 'beda@forestguard.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Different123!',
        ]);
        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors(['password']);
    }

    public function test_registration_ignores_injected_role_id(): void
    {
        $adminRoleId = Role::where('name', Role::ADMIN)->value('id');

        $response = $this->post(route('register'), [
            'name' => 'Hacker Wannabe',
            'email' => 'hacker@forestguard.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_id' => $adminRoleId,
        ]);

        $response->assertRedirect(route('citizen.home'));

        $user = User::where('email', 'hacker@forestguard.test')->firstOrFail();
        $this->assertTrue($user->isCitizen(), 'Registered user must always have citizen role regardless of input.');
        $this->assertFalse($user->isAdmin());
    }

    // --- LOGIN & LOGOUT TESTS (UC-01) ---

    public function test_login_successful_redirects_per_role(): void
    {
        // Citizen
        $response = $this->post(route('login'), [
            'email' => 'citizen1@forestguard.test',
            'password' => UserSeeder::DEV_PASSWORD,
        ]);
        $response->assertRedirect(route('citizen.home'));
        $this->assertAuthenticatedAs(User::where('email', 'citizen1@forestguard.test')->first());

        $this->post(route('logout'));
        $this->assertGuest();

        // Ranger
        $response = $this->post(route('login'), [
            'email' => 'ranger1@forestguard.test',
            'password' => UserSeeder::DEV_PASSWORD,
        ]);
        $response->assertRedirect(route('ranger.home'));
        $this->assertAuthenticatedAs(User::where('email', 'ranger1@forestguard.test')->first());

        $this->post(route('logout'));
        $this->assertGuest();

        // Admin
        $response = $this->post(route('login'), [
            'email' => 'admin@forestguard.test',
            'password' => UserSeeder::DEV_PASSWORD,
        ]);
        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'admin@forestguard.test')->first());
    }

    public function test_login_fails_with_identical_generic_error_to_prevent_user_enumeration(): void
    {
        // Wrong password for existing user
        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'citizen1@forestguard.test',
            'password' => 'WrongPassword123!',
        ]);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Email atau kata sandi salah',
        ]);

        // Non-existent email
        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'nonexistent@forestguard.test',
            'password' => 'AnyPassword123!',
        ]);
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Email atau kata sandi salah',
        ]);
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = User::where('email', 'citizen1@forestguard.test')->firstOrFail();
        $user->is_active = false;
        $user->save();

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'citizen1@forestguard.test',
            'password' => UserSeeder::DEV_PASSWORD,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Akun Anda tidak aktif. Hubungi Admin untuk mengaktifkan kembali.',
        ]);
        $this->assertGuest();
    }

    public function test_active_session_is_terminated_if_account_is_subsequently_deactivated(): void
    {
        $user = User::where('email', 'citizen1@forestguard.test')->firstOrFail();
        $this->actingAs($user);

        // Accessing protected route while active works
        $this->get(route('citizen.home'))->assertOk();

        // Deactivate user in database
        $user->is_active = false;
        $user->save();

        // Next request gets logged out by active middleware
        $response = $this->get(route('citizen.home'));
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Akun Anda tidak aktif. Hubungi Admin untuk mengaktifkan kembali.',
        ]);
        $this->assertGuest();
    }

    public function test_logout_terminates_session_and_redirects_to_login(): void
    {
        $user = User::where('email', 'citizen1@forestguard.test')->firstOrFail();
        $this->actingAs($user);

        $response = $this->post(route('logout'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();

        // Visiting protected page redirects to login
        $this->get(route('citizen.home'))->assertRedirect(route('login'));
    }

    // --- RBAC & GUEST REDIRECTION (NFR-04) ---

    public function test_rbac_prevents_unauthorized_cross_role_access(): void
    {
        $citizen = User::where('email', 'citizen1@forestguard.test')->firstOrFail();
        $ranger = User::where('email', 'ranger1@forestguard.test')->firstOrFail();

        // Citizen accessing admin or ranger area -> 403
        $this->actingAs($citizen)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($citizen)->get(route('ranger.home'))->assertStatus(403);

        // Ranger accessing admin area -> 403
        $this->actingAs($ranger)->get(route('admin.dashboard'))->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('ranger.home'))->assertRedirect(route('login'));
        $this->get(route('citizen.home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_opening_guest_pages_is_redirected_to_role_home(): void
    {
        $admin = User::where('email', 'admin@forestguard.test')->firstOrFail();
        $ranger = User::where('email', 'ranger1@forestguard.test')->firstOrFail();
        $citizen = User::where('email', 'citizen1@forestguard.test')->firstOrFail();

        $this->actingAs($admin)->get(route('login'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('register'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));

        $this->actingAs($ranger)->get(route('login'))->assertRedirect(route('ranger.home'));
        $this->actingAs($citizen)->get(route('login'))->assertRedirect(route('citizen.home'));
    }

    public function test_login_rate_limiting_blocks_after_5_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => 'citizen1@forestguard.test',
                'password' => 'WrongPass!',
            ]);
        }

        // 6th attempt should be blocked
        $response = $this->post(route('login'), [
            'email' => 'citizen1@forestguard.test',
            'password' => 'WrongPass!',
        ]);

        $response->assertSessionHasErrors('email');
        $error = session('errors')->first('email');
        $this->assertStringContainsString('Terlalu banyak percobaan masuk', $error);
    }
}
