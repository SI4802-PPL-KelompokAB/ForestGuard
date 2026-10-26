<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Pbi03UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_view_user_index_with_seeded_accounts(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Manajemen pengguna');
        $response->assertSee('Admin Hutan');
        $response->assertSee('Ranger Satu');
        $response->assertSee('Warga Satu');
        $response->assertSee('Tambah akun');
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.users.index', ['peran' => Role::RANGER]));

        $response->assertOk();
        $response->assertSee('Ranger Satu');
        $response->assertDontSee('Warga Satu');
        $response->assertDontSee('Admin Hutan');
    }

    public function test_admin_can_create_ranger_who_can_then_login(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin())->post(route('admin.users.store'), $this->validPayload([
            'name' => 'Ranger Baru',
            'email' => 'rangerbaru@forestguard.test',
            'role' => Role::RANGER,
        ]));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', 'Akun berhasil dibuat. Kata sandi sementara telah dikirim ke email.');

        $ranger = User::where('email', 'rangerbaru@forestguard.test')->firstOrFail();
        $this->assertTrue($ranger->isRanger());
        $this->assertTrue($ranger->is_active);

        $sentPassword = null;
        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail) use ($ranger, &$sentPassword): bool {
            $sentPassword = $mail->temporaryPassword;

            return $mail->hasTo('rangerbaru@forestguard.test')
                && $mail->user->is($ranger)
                && strlen($sentPassword) >= 8;
        });

        $this->assertTrue(Hash::check($sentPassword, $ranger->password));

        $this->post(route('logout'));

        $login = $this->post(route('login'), [
            'email' => 'rangerbaru@forestguard.test',
            'password' => $sentPassword,
        ]);
        $login->assertRedirect(route('ranger.home'));
    }

    public function test_admin_can_create_another_admin(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->validPayload([
            'name' => 'Admin Dua',
            'email' => 'admin2@forestguard.test',
            'role' => Role::ADMIN,
        ]))->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'admin2@forestguard.test')->firstOrFail();
        $this->assertTrue($created->isAdmin());
        Mail::assertSent(TemporaryPasswordMail::class);
    }

    public function test_account_is_still_created_when_email_fails_to_send(): void
    {
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new \Exception('SMTP connection timed out'));

        $response = $this->actingAs($this->admin())->post(route('admin.users.store'), $this->validPayload([
            'name' => 'Ranger Email Gagal',
            'email' => 'gagalemail@forestguard.test',
            'role' => Role::RANGER,
        ]));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('status', 'Akun berhasil dibuat.');
        $response->assertSessionHas('warning', 'Email kata sandi gagal dikirim. Silakan tetapkan kata sandi manual melalui menu Ubah.');

        $user = User::where('email', 'gagalemail@forestguard.test')->firstOrFail();
        $this->assertTrue($user->is_active);
    }

    public function test_store_ignores_injected_is_active_and_role_id(): void
    {
        Mail::fake();

        $citizenRoleId = Role::query()->where('name', Role::CITIZEN)->value('id');

        $this->actingAs($this->admin())->post(route('admin.users.store'), $this->validPayload([
            'name' => 'Ranger Injeksi',
            'email' => 'injeksi@forestguard.test',
            'role' => Role::RANGER,
            'is_active' => false,
            'role_id' => $citizenRoleId,
        ]))->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'injeksi@forestguard.test')->firstOrFail();
        $this->assertTrue($user->isRanger());
        $this->assertTrue($user->is_active);
    }

    public function test_store_rejects_duplicate_email_including_soft_deleted(): void
    {
        $existing = User::where('email', 'ranger1@forestguard.test')->firstOrFail();
        $existing->delete();

        $response = $this->actingAs($this->admin())->from(route('admin.users.create'))->post(route('admin.users.store'), $this->validPayload([
            'email' => 'ranger1@forestguard.test',
            'role' => Role::RANGER,
        ]));

        $response->assertRedirect(route('admin.users.create'));
        $response->assertSessionHasErrors(['email' => 'Email sudah terdaftar.']);
    }

    public function test_store_validation_rejects_invalid_payload(): void
    {
        $response = $this->actingAs($this->admin())->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'name' => 'Ab',
            'email' => 'bukan-email',
            'role' => 'superadmin',
        ]);

        $response->assertRedirect(route('admin.users.create'));
        $response->assertSessionHasErrors(['name', 'email', 'role']);
        $response->assertSessionDoesntHaveErrors(['password']);
    }

    public function test_admin_can_view_edit_user_page(): void
    {
        $ranger = User::where('email', 'ranger1@forestguard.test')->firstOrFail();

        $response = $this->actingAs($this->admin())->get(route('admin.users.edit', $ranger));
        $response->assertOk();
        $response->assertSee('Ubah pengguna');
        $response->assertSee($ranger->email);

        $directResponse = $this->actingAs($this->admin())->get("/admin/pengguna/{$ranger->id}");
        $directResponse->assertOk();
        $directResponse->assertSee('Ubah pengguna');
    }

    public function test_admin_can_update_user_without_changing_email(): void
    {
        $ranger = User::where('email', 'ranger1@forestguard.test')->firstOrFail();

        $this->actingAs($this->admin())->put(route('admin.users.update', $ranger), [
            'name' => 'Ranger Satu Diedit',
            'email' => 'ranger1-hacked@forestguard.test',
            'role' => Role::RANGER,
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $ranger->refresh();
        $this->assertEquals('Ranger Satu Diedit', $ranger->name);
        $this->assertEquals('ranger1@forestguard.test', $ranger->email);
    }

    public function test_admin_can_deactivate_user_so_login_is_blocked(): void
    {
        $ranger = User::where('email', 'ranger1@forestguard.test')->firstOrFail();

        $this->actingAs($this->admin())->put(route('admin.users.update', $ranger), [
            'name' => $ranger->name,
            'role' => Role::RANGER,
            'is_active' => '0',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($ranger->fresh()->is_active);

        $this->post(route('logout'));

        $login = $this->from(route('login'))->post(route('login'), [
            'email' => 'ranger1@forestguard.test',
            'password' => UserSeeder::DEV_PASSWORD,
        ]);
        $login->assertRedirect(route('login'));
        $login->assertSessionHasErrors([
            'email' => 'Akun Anda tidak aktif. Hubungi Admin untuk mengaktifkan kembali.',
        ]);
    }

    public function test_admin_cannot_deactivate_or_change_own_role(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->from(route('admin.users.edit', $admin))->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'role' => Role::CITIZEN,
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.users.edit', $admin));
        $response->assertSessionHasErrors(['is_active', 'role']);
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_soft_delete_own_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->from(route('admin.users.index'))->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors(['user']);
        $this->assertFalse($admin->fresh()->trashed());
    }

    public function test_admin_can_soft_delete_and_restore_user(): void
    {
        $ranger = User::where('email', 'ranger1@forestguard.test')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.users.destroy', $ranger))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted($ranger);
        $this->actingAs($this->admin())->get(route('admin.users.index'))->assertDontSee('ranger1@forestguard.test');
        $this->actingAs($this->admin())->get(route('admin.users.index', ['status' => 'terhapus']))->assertSee('ranger1@forestguard.test');

        $this->post(route('logout'));
        $this->from(route('login'))->post(route('login'), [
            'email' => 'ranger1@forestguard.test',
            'password' => UserSeeder::DEV_PASSWORD,
        ])->assertSessionHasErrors(['email']);

        $this->actingAs($this->admin())
            ->post(route('admin.users.restore', $ranger))
            ->assertRedirect(route('admin.users.index', ['status' => 'terhapus']));

        $this->assertFalse($ranger->fresh()->trashed());
        $this->actingAs($this->admin())->get(route('admin.users.index'))->assertSee('ranger1@forestguard.test');
    }

    public function test_guest_is_redirected_from_user_management(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->post(route('admin.users.store'), $this->validPayload())->assertRedirect(route('login'));
    }

    #[DataProvider('nonAdminEmails')]
    public function test_non_admin_is_forbidden_from_user_management(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();

        $ranger = User::where('email', 'ranger2@forestguard.test')->firstOrFail();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->put(route('admin.users.update', $ranger), [
            'name' => 'Hack',
            'role' => Role::ADMIN,
            'is_active' => '1',
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.users.destroy', $ranger))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.restore', $ranger))->assertForbidden();
    }

    public function test_user_index_escapes_dangerous_names(): void
    {
        User::factory()->ranger()->create([
            'name' => '<script>alert(1)</script>',
            'email' => 'xss@forestguard.test',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.users.index'));
        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminEmails(): array
    {
        return [
            'ranger' => ['ranger1@forestguard.test'],
            'citizen' => ['citizen1@forestguard.test'],
        ];
    }

    private function admin(): User
    {
        return User::where('email', 'admin@forestguard.test')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pengguna Baru',
            'email' => 'baru@forestguard.test',
            'role' => Role::RANGER,
        ], $overrides);
    }
}
