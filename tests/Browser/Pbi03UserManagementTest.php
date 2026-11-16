<?php

namespace Tests\Browser;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi03UserManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * D-03-01: Admin login -> Buka Pengguna -> Tambah satu akun Ranger -> Muncul di daftar.
     */
    public function test_admin_can_create_new_user(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                ->assertSee('Kelola akun admin, ranger, dan warga.')
                ->clickLink('Tambah akun')
                ->waitForRoute('admin.users.create')
                ->type('name', 'Ranger Dusk Baru')
                ->type('email', 'ranger.dusk@forestguard.test')
                ->select('role', Role::RANGER)
                ->press('Tambah akun')
                ->waitForRoute('admin.users.index')
                ->assertSee('Akun berhasil dibuat. Kata sandi sementara telah dikirim ke email.')
                ->assertSee('Ranger Dusk Baru')
                ->assertSee('ranger.dusk@forestguard.test');
        });
    }

    /**
     * D-03-02: Admin menyaring berdasarkan peran dan mencari nama pengguna.
     */
    public function test_admin_can_filter_and_search_users(): void
    {
        User::factory()->ranger()->create(['name' => 'Ranger Dusk Baru']);
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                // Filter peran Ranger
                ->select('peran', Role::RANGER)
                ->press('Terapkan')
                ->waitForText('Ranger Satu')
                ->assertDontSee('Warga Satu')
                // Pencarian berdasarkan kata kunci nama
                ->type('q', 'Ranger Dusk Baru')
                ->press('Terapkan')
                ->waitForText('Ranger Dusk Baru');
        });
    }

    /**
     * D-03-03: Admin mengubah profil pengguna (Update).
     */
    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->ranger()->create(['name' => 'Ranger Dusk Baru']);
        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Ranger Dusk Baru')
                ->click('a[href="'.route('admin.users.edit', $user).'"]')
                ->waitForText('Ubah pengguna')
                ->type('name', 'Ranger Dusk Updated')
                ->press('Simpan perubahan')
                ->waitForRoute('admin.users.index')
                ->assertSee('Pengguna berhasil diperbarui.')
                ->assertSee('Ranger Dusk Updated');
        });
    }

    /**
     * D-03-04: Hapus lunak pengguna (Soft Delete) -> Pulihkan (Restore).
     */
    public function test_admin_can_soft_delete_and_restore_user(): void
    {
        $user = User::factory()->ranger()->create(['name' => 'Ranger Dusk Updated']);
        $this->browse(function (Browser $browser) use ($user) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Ranger Dusk Updated')
                // Klik tombol Hapus pada akun
                ->click('form[action="'.route('admin.users.destroy', $user).'"] button')
                ->acceptDialog()
                ->waitForText('Pengguna berhasil dihapus.')
                ->assertDontSee('Ranger Dusk Updated')
                // Filter status terhapus
                ->select('status', 'terhapus')
                ->press('Terapkan')
                ->waitForText('Ranger Dusk Updated')
                // Pulihkan akun
                ->click('form[action="'.route('admin.users.restore', $user).'"] button')
                ->waitForText('Pengguna berhasil dipulihkan.');
        });
    }

    /**
     * D-03-05: Admin tidak dapat menghapus akun miliknya sendiri (Self-Deletion Protection).
     */
    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::where('email', 'admin@forestguard.test')->firstOrFail();
        $this->browse(function (Browser $browser) use ($admin) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                // Pada baris Admin Hutan (akun yang sedang login), tombol Hapus tidak boleh ada
                ->assertPresent('a[href="'.route('admin.users.edit', $admin).'"]')
                ->assertMissing('form[action="'.route('admin.users.destroy', $admin).'"]');
        });
    }
}
