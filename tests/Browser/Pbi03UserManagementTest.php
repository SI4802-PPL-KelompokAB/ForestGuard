<?php

namespace Tests\Browser;

use App\Models\Role;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi03UserManagementTest extends DuskTestCase
{
    /**
     * D-03-01: Admin membuka halaman manajemen pengguna dan melihat data.
     */
    public function test_admin_can_view_user_management_index(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                ->assertSee('Kelola akun admin, ranger, dan warga.')
                ->assertSee('Tambah akun')
                ->assertSee('Admin Hutan')
                ->assertSee('Ranger Satu')
                ->assertSee('Warga Satu');
        });
    }

    /**
     * D-03-02: Admin mendaftarkan akun baru (Ranger) dan sistem otomatis membuat kata sandi via email.
     */
    public function test_admin_can_create_new_user(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->clickLink('Tambah akun')
                ->waitForRoute('admin.users.create')
                ->assertSee('Tambah akun')
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
     * D-03-03: Admin menyaring (filter) dan mencari data pengguna.
     */
    public function test_admin_can_filter_and_search_users(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Manajemen pengguna')
                // Filter berdasarkan peran Ranger
                ->select('peran', Role::RANGER)
                ->press('Terapkan')
                ->waitForText('Ranger Satu')
                ->assertDontSee('Warga Satu')
                // Pencarian berdasarkan nama
                ->type('q', 'Ranger Dusk Baru')
                ->press('Terapkan')
                ->waitForText('Ranger Dusk Baru');
        });
    }

    /**
     * D-03-04: Admin mengubah data akun pengguna (Update).
     */
    public function test_admin_can_update_user(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Ranger Dusk Baru')
                ->click('tr:contains("Ranger Dusk Baru") a:contains("Ubah")')
                ->waitForText('Ubah pengguna')
                ->type('name', 'Ranger Dusk Updated')
                ->press('Simpan perubahan')
                ->waitForRoute('admin.users.index')
                ->assertSee('Perubahan akun berhasil disimpan.')
                ->assertSee('Ranger Dusk Updated');
        });
    }

    /**
     * D-03-05: Admin melakukan soft delete dan restore akun pengguna.
     */
    public function test_admin_can_soft_delete_and_restore_user(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/pengguna')
                ->waitForText('Ranger Dusk Updated')
                // Klik tombol Hapus pada baris user
                ->click('tr:contains("Ranger Dusk Updated") button:contains("Hapus")')
                ->acceptDialog()
                ->waitForText('Pengguna berhasil dihapus.')
                ->assertDontSee('Ranger Dusk Updated')
                // Filter status terhapus
                ->select('status', 'terhapus')
                ->press('Terapkan')
                ->waitForText('Ranger Dusk Updated')
                // Pulihkan akun
                ->press('Pulihkan')
                ->waitForText('Pengguna berhasil dipulihkan.');
        });
    }
}

