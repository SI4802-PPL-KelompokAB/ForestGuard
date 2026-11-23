<?php

// PBI-04: Bagian kode yang mendukung master data dan proteksi akses.

namespace Tests\Browser;

use App\Models\Checkpoint;
use App\Models\ForestArea;
use App\Models\IncidentType;
use App\Models\Patrol;
use App\Models\PatrolCheckpoint;
use App\Models\RiskLevel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi04MasterDataTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanPbi04Fixtures();
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->cleanPbi04Fixtures();
        parent::tearDown();
    }

    /** Remove only records created specifically by this PBI's tests. */
    private function cleanPbi04Fixtures(): void
    {
        $areaNames = array_merge(
            ['Kawasan PBI04 Dusk', 'Kawasan PBI04 Dusk Diperbarui', 'Kawasan Hapus PBI04'],
            array_map(fn (int $number): string => "PBI04 Pagination {$number}", range(1, 16)),
        );
        $areaIds = ForestArea::withTrashed()->whereIn('name', $areaNames)->pluck('id');
        $checkpointIds = Checkpoint::withTrashed()->whereIn('code', [
            'CP-PBI04-CRUD',
            'CP-PBI04-HISTORY',
            'CP-PBI04-INVALID-COORD',
            'CP-PBI04-INVALID-AREA',
        ])->pluck('id');
        $patrolIds = Patrol::query()->whereIn('forest_area_id', $areaIds)->pluck('id');

        PatrolCheckpoint::query()
            ->whereIn('patrol_id', $patrolIds)
            ->orWhereIn('checkpoint_id', $checkpointIds)
            ->delete();
        Patrol::query()->whereIn('id', $patrolIds)->delete();
        Checkpoint::withTrashed()->whereIn('id', $checkpointIds)->forceDelete();
        ForestArea::withTrashed()->whereIn('id', $areaIds)->forceDelete();
        IncidentType::withTrashed()->whereIn('name', [
            'Tipe Insiden PBI04',
            'Tipe Insiden PBI04 Diperbarui',
        ])->forceDelete();
    }

    /** TC-PBI04-01: seluruh index master, paginasi, dan status read-only. */
    public function test_admin_can_browse_all_master_indexes_and_case_status_is_read_only(): void
    {
        foreach (range(1, 16) as $number) {
            ForestArea::factory()->create(['name' => "PBI04 Pagination {$number}"]);
        }

        $this->browse(function (Browser $browser): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.forest-areas.index')
                ->waitForText('Manajemen Data Master')
                ->assertSee('Kawasan Hutan')
                ->assertPresent('a[rel="next"]')
                ->visitRoute('admin.master.checkpoints.index')
                ->waitForText('Checkpoint')
                ->assertSee('CP-GC-01')
                ->visitRoute('admin.master.incident-types.index')
                ->waitForText('Tipe Insiden')
                ->assertSee('Kebakaran hutan')
                ->visitRoute('admin.master.risk-levels.index')
                ->waitForText('Tingkat Risiko')
                ->assertSee('Rendah')
                ->visitRoute('admin.master.case-statuses.index')
                ->waitForText('Status Kasus (Read-Only)')
                ->assertSee('Data Referensi Sistem Tetap')
                ->assertDontSee('Tambah')
                ->assertDontSee('Ubah')
                ->assertDontSee('Hapus');
        });
    }

    /** TC-PBI04-02: create, update, dan parsing boundary GeoJSON kawasan. */
    public function test_admin_can_create_update_and_parse_forest_area(): void
    {
        $boundary = '{"type":"Polygon","coordinates":[[[105.1,-5.1],[105.2,-5.1],[105.2,-5.2],[105.1,-5.2],[105.1,-5.1]]]}';

        $this->browse(function (Browser $browser) use ($boundary): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.forest-areas.create')
                ->type('name', 'Kawasan PBI04 Dusk')
                ->type('description', 'Kawasan yang dibuat untuk pengujian PBI-04.')
                ->type('area_ha', '1250.75')
                ->click('[data-forest-area-map]')
                ->type('boundary', $boundary)
                ->press('Simpan kawasan')
                ->press('Ya, lanjutkan')
                ->waitForRoute('admin.master.checkpoints.index')
                ->waitForText('Kawasan hutan berhasil ditambahkan.')
                ->visitRoute('admin.master.forest-areas.index')
                ->waitForText('Kawasan PBI04 Dusk');
        });

        $area = ForestArea::where('name', 'Kawasan PBI04 Dusk')->firstOrFail();

        $this->assertSame('Polygon', $area->fresh()->boundary['type']);

        $this->browse(function (Browser $browser) use ($area): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.forest-areas.edit', $area)
                ->clear('name')
                ->type('name', 'Kawasan PBI04 Dusk Diperbarui')
                ->clear('area_ha')
                ->type('area_ha', '2222.50')
                ->press('Simpan perubahan')
                ->press('Ya, lanjutkan')
                ->waitForRoute('admin.master.forest-areas.index')
                ->waitForText('Kawasan hutan berhasil diperbarui.')
                ->assertSee('Kawasan PBI04 Dusk Diperbarui');
        });

        $this->assertDatabaseHas('forest_areas', [
            'id' => $area->id,
            'name' => 'Kawasan PBI04 Dusk Diperbarui',
            'area_ha' => 2222.50,
        ]);
    }

    /** TC-PBI04-03: validasi nama, luas, koordinat, dan GeoJSON. */
    public function test_forest_area_invalid_inputs_show_validation_errors(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.forest-areas.create');

            $this->disableNativeValidation($browser);

            $browser->type('name', 'Ab')
                ->type('area_ha', '-5')
                ->type('boundary', 'invalid-json-text');

            $browser->script("document.querySelector('[name=center_lat]').value = '100'; document.querySelector('[name=center_lng]').value = '-200';");

            $browser->press('Simpan kawasan')
                ->press('Ya, lanjutkan')
                ->waitForText('Nama kawasan minimal 3 karakter.')
                ->assertSee('Luas kawasan harus lebih besar dari 0 hektar.')
                ->assertSee('Latitude pusat harus berada di antara -90 dan 90.')
                ->assertSee('Longitude pusat harus berada di antara -180 dan 180.')
                ->assertSee('Format batas kawasan harus berupa JSON GeoJSON yang valid.');
        });
    }

    /** TC-PBI04-04: soft delete dan restore kawasan hutan. */
    public function test_admin_can_soft_delete_and_restore_forest_area(): void
    {
        $area = ForestArea::factory()->create(['name' => 'Kawasan Hapus PBI04']);

        $this->browse(function (Browser $browser) use ($area): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.forest-areas.index')
                ->waitForText('Kawasan Hapus PBI04')
                ->click('form[action="'.route('admin.master.forest-areas.destroy', $area).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Kawasan hutan berhasil dihapus.')
                ->assertDontSee('Kawasan Hapus PBI04')
                ->click('[data-toast-close]')
                ->select('status', 'terhapus')
                ->press('Terapkan')
                ->waitForText('Kawasan Hapus PBI04')
                ->click('form[action="'.route('admin.master.forest-areas.restore', $area).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Kawasan hutan berhasil dipulihkan.');
        });

        $this->assertNotSoftDeleted($area);
    }

    /** TC-PBI04-05: CRUD checkpoint serta validasi kode, koordinat, dan kawasan. */
    public function test_admin_can_crud_checkpoint_and_validation_rejects_invalid_data(): void
    {
        $area = ForestArea::firstOrFail();
        $code = 'CP-PBI04-CRUD';

        $this->browse(function (Browser $browser) use ($area, $code): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.checkpoints.create')
                ->select('forest_area_id', (string) $area->id)
                ->type('code', $code)
                ->type('name', 'Checkpoint PBI04 Baru')
                ->type('latitude', '-5.1234567')
                ->type('longitude', '105.1234567')
                ->press('Simpan checkpoint')
                ->press('Ya, lanjutkan')
                ->waitForRoute('admin.master.checkpoints.index')
                ->waitForText('Checkpoint berhasil ditambahkan.')
                ->assertSee($code);
        });

        $checkpoint = Checkpoint::where('code', $code)->firstOrFail();

        $this->browse(function (Browser $browser) use ($checkpoint): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.checkpoints.edit', $checkpoint)
                ->clear('name')
                ->type('name', 'Checkpoint PBI04 Diperbarui')
                ->press('Simpan perubahan')
                ->press('Ya, lanjutkan')
                ->waitForRoute('admin.master.checkpoints.index')
                ->waitForText('Checkpoint berhasil diperbarui.')
                ->assertSee('Checkpoint PBI04 Diperbarui');
        });

        $duplicate = Checkpoint::firstOrFail();

        $this->browse(function (Browser $browser) use ($area, $duplicate): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.checkpoints.create')
                ->select('forest_area_id', (string) $area->id)
                ->type('code', $duplicate->code)
                ->type('name', 'Checkpoint Kode Duplikat')
                ->type('latitude', '-5.1')
                ->type('longitude', '105.1')
                ->press('Simpan checkpoint')
                ->press('Ya, lanjutkan')
                ->waitForText('Kode checkpoint sudah digunakan.')
                ->visitRoute('admin.master.checkpoints.create')
                ->select('forest_area_id', (string) $area->id)
                ->type('code', 'CP-PBI04-INVALID-COORD')
                ->type('name', 'Checkpoint Koordinat Salah')
                ->type('latitude', '100')
                ->type('longitude', '200')
                ->press('Simpan checkpoint')
                ->press('Ya, lanjutkan')
                ->waitForText('Latitude harus berada di antara -90 dan 90.')
                ->assertSee('Longitude harus berada di antara -180 dan 180.');

            $browser->visitRoute('admin.master.checkpoints.create')
                ->script("const select = document.querySelector('select[name=forest_area_id]'); const option = new Option('Kawasan fiktif', '999999', true, true); select.add(option);");

            $browser->type('code', 'CP-PBI04-INVALID-AREA')
                ->type('name', 'Checkpoint Kawasan Fiktif')
                ->type('latitude', '-5.1')
                ->type('longitude', '105.1')
                ->press('Simpan checkpoint')
                ->press('Ya, lanjutkan')
                ->waitForText('Kawasan hutan yang dipilih tidak valid atau sudah dihapus.');
        });
    }

    /** TC-PBI04-06: soft delete checkpoint tanpa menghilangkan histori patroli. */
    public function test_admin_can_soft_delete_checkpoint_without_losing_history(): void
    {
        $area = ForestArea::firstOrFail();
        $checkpoint = Checkpoint::factory()->for($area)->create(['code' => 'CP-PBI04-HISTORY']);
        $patrol = Patrol::factory()->for($area)->create();
        $history = PatrolCheckpoint::create([
            'client_uuid' => (string) Str::uuid(),
            'patrol_id' => $patrol->id,
            'checkpoint_id' => $checkpoint->id,
            'sequence' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($area, $checkpoint): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.checkpoints.index')
                ->type('q', $checkpoint->code)
                ->press('Terapkan')
                ->waitForText($checkpoint->code)
                ->click('form[action="'.route('admin.master.checkpoints.destroy', $checkpoint).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Checkpoint berhasil dihapus.')
                ->assertDontSee($checkpoint->code)
                ->visitRoute('admin.patrols.index')
                ->select('forest_area_id', (string) $area->id)
                ->assertMissing('input[value="'.$checkpoint->id.'"]');
        });

        $this->assertSoftDeleted($checkpoint);
        $this->assertNotNull(PatrolCheckpoint::find($history->id));
    }

    /** TC-PBI04-07: CRUD dan restore tipe insiden. */
    public function test_admin_can_crud_and_restore_incident_type(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.incident-types.create')
                ->type('name', 'Tipe Insiden PBI04')
                ->press('Simpan tipe insiden')
                ->press('Ya, lanjutkan')
                ->waitForRoute('admin.master.incident-types.index')
                ->waitForText('Tipe insiden berhasil ditambahkan.')
                ->assertSee('Tipe Insiden PBI04');
        });

        $incidentType = IncidentType::where('name', 'Tipe Insiden PBI04')->firstOrFail();

        $this->browse(function (Browser $browser) use ($incidentType): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.incident-types.edit', $incidentType)
                ->clear('name')
                ->type('name', 'Tipe Insiden PBI04 Diperbarui')
                ->press('Simpan perubahan')
                ->press('Ya, lanjutkan')
                ->waitForRoute('admin.master.incident-types.index')
                ->waitForText('Tipe insiden berhasil diperbarui.')
                ->assertSee('Tipe Insiden PBI04 Diperbarui')
                ->click('[data-toast-close]')
                ->click('form[action="'.route('admin.master.incident-types.destroy', $incidentType).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Tipe insiden berhasil dihapus.')
                ->click('[data-toast-close]')
                ->select('status', 'terhapus')
                ->press('Terapkan')
                ->waitForText('Tipe Insiden PBI04 Diperbarui')
                ->click('form[action="'.route('admin.master.incident-types.restore', $incidentType).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Tipe insiden berhasil dipulihkan.');
        });

        $this->assertNotSoftDeleted($incidentType);
    }

    /** TC-PBI04-08: toggle status risiko dan perlindungan risiko aktif terakhir. */
    public function test_admin_can_toggle_risk_level_and_preserve_last_active_level(): void
    {
        $risk = RiskLevel::where('name', 'Rendah')->firstOrFail();

        $this->browse(function (Browser $browser) use ($risk): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.risk-levels.index')
                ->click('form[action="'.route('admin.master.risk-levels.toggle', $risk).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Tingkat risiko berhasil dinonaktifkan.')
                ->assertSee('Nonaktif');
        });

        $this->assertFalse($risk->fresh()->is_active);

        $this->browse(function (Browser $browser) use ($risk): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.risk-levels.index')
                ->click('form[action="'.route('admin.master.risk-levels.toggle', $risk).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Tingkat risiko berhasil diaktifkan.');
        });

        RiskLevel::query()->where('id', '!=', $risk->id)->update(['is_active' => false]);

        $this->browse(function (Browser $browser) use ($risk): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.master.risk-levels.index')
                ->click('form[action="'.route('admin.master.risk-levels.toggle', $risk).'"] button')
                ->press('Ya, lanjutkan')
                ->waitForText('Tidak dapat menonaktifkan tingkat risiko ini karena minimal harus ada satu tingkat risiko yang aktif.');
        });

        $this->assertTrue($risk->fresh()->is_active);
    }

    /** TC-PBI04-09: rute status kasus menolak method write dengan HTTP 405. */
    public function test_case_statuses_are_read_only_and_reject_write_requests(): void
    {
        $admin = $this->admin();
        $route = route('admin.master.case-statuses.index');

        $this->actingAs($admin)->get($route)
            ->assertOk()
            ->assertSee('Status Kasus')
            ->assertSee('Reported')
            ->assertSee('Resolved');

        $this->actingAs($admin)->post($route)->assertStatus(405);
        $this->actingAs($admin)->put($route)->assertStatus(405);
        $this->actingAs($admin)->delete($route)->assertStatus(405);
    }

    /** TC-PBI04-10: guest redirect serta Ranger/Citizen forbidden dari master data. */
    public function test_guest_and_non_admin_users_cannot_access_master_data(): void
    {
        $masterRoutes = [
            'admin.master.forest-areas.index',
            'admin.master.checkpoints.index',
            'admin.master.incident-types.index',
            'admin.master.risk-levels.index',
            'admin.master.case-statuses.index',
        ];

        $this->browse(function (Browser $browser) use ($masterRoutes): void {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();

            foreach ($masterRoutes as $route) {
                $browser->visitRoute($route)
                    ->waitFor('input#email')
                    ->assertPathIs('/login');
            }

            $browser->loginAsRanger();
            foreach ($masterRoutes as $route) {
                $browser->visitRoute($route)->waitForText('403')->assertSee('403');
            }

            $browser->loginAsCitizen();
            foreach ($masterRoutes as $route) {
                $browser->visitRoute($route)->waitForText('403')->assertSee('403');
            }
        });
    }

    private function disableNativeValidation(Browser $browser): void
    {
        $browser->script("document.querySelectorAll('form').forEach((form) => form.noValidate = true);");
    }

    private function admin(): User
    {
        return User::where('email', 'admin@forestguard.test')->firstOrFail();
    }
}
