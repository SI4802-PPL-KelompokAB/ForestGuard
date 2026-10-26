<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi04MasterDataTest extends DuskTestCase
{
    /**
     * D-04-01: Admin login -> Buka Kawasan -> Tambah satu baris -> Muncul di daftar.
     */
    public function test_admin_can_add_forest_area(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/master/kawasan')
                ->waitForText('Manajemen Data Master')
                ->assertSee('Kawasan Hutan')
                ->clickLink('Tambah kawasan')
                ->waitForRoute('admin.master.forest-areas.create')
                ->type('name', 'Hutan Lindung Dusk Test')
                ->type('description', 'Kawasan hutan yang dibuat dari pengujian otomatis Dusk')
                ->type('area_ha', '2500.50')
                ->type('center_lat', '-5.4321000')
                ->type('center_lng', '105.1234000')
                ->press('Simpan kawasan')
                ->waitForRoute('admin.master.forest-areas.index')
                ->assertSee('Kawasan hutan berhasil ditambahkan.')
                ->assertSee('Hutan Lindung Dusk Test');
        });
    }

    /**
     * D-04-02: Hapus lunak kawasan -> Pulihkan.
     */
    public function test_admin_can_soft_delete_and_restore_forest_area(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/master/kawasan')
                ->waitForText('Hutan Lindung Dusk Test')
                ->press('Hapus')
                ->acceptDialog()
                ->waitForText('Kawasan hutan berhasil dihapus.')
                ->assertDontSee('Hutan Lindung Dusk Test')
                // Filter terhapus
                ->select('status', 'terhapus')
                ->press('Terapkan')
                ->waitForText('Hutan Lindung Dusk Test')
                // Pulihkan
                ->press('Pulihkan')
                ->waitForText('Kawasan hutan berhasil dipulihkan.');
        });
    }

    /**
     * D-04-03: Halaman Status tidak punya tombol tambah/ubah/hapus (Read-Only).
     */
    public function test_case_statuses_page_is_strictly_read_only(): void
    {
        $this->browse(function (Browser $browser) {
            $this->loginAsAdmin($browser)
                ->visit('/admin/master/status')
                ->waitForText('Status Kasus (Read-Only)')
                ->assertSee('Data Referensi Sistem Tetap')
                ->assertDontSee('Tambah')
                ->assertDontSee('Ubah')
                ->assertDontSee('Hapus');
        });
    }
}
