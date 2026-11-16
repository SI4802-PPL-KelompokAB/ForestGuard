<?php

namespace Tests\Browser;

use App\Models\ForestArea;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * PBI-10: Pelaporan Insiden oleh Citizen & Ranger — Laravel Dusk
 *
 * Prasyarat seed: RoleSeeder, CaseStatusSeeder, IncidentTypeSeeder, ForestAreaSeeder, UserSeeder
 */
class Pbi10IncidentReportingTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            CaseStatusSeeder::class,
            IncidentTypeSeeder::class,
            ForestAreaSeeder::class,
        ]);
    }

    /**
     * TC-PBI10-01: Citizen dapat mengakses halaman form laporan insiden.
     */
    public function test_citizen_can_access_incident_report_form(): void
    {
        $citizen = User::factory()->citizen()->create();

        $this->browse(function (Browser $browser) use ($citizen): void {
            $browser->loginAs($citizen)
                ->visitRoute('citizen.incidents.create')
                ->waitForText('Buat Laporan Insiden')
                ->assertSee('Buat Laporan Insiden')
                ->assertPresent('input[name=incident_type_id]')
                ->assertPresent('#forest_area_id')
                ->assertPresent('#occurred_at')
                ->assertPresent('#description')
                ->assertPresent('#photo');
        });
    }

    /**
     * TC-PBI10-02: Guest diarahkan ke halaman login saat mengakses form laporan.
     */
    public function test_guest_is_redirected_to_login_when_accessing_incident_form(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();
            $browser->visitRoute('citizen.incidents.create')
                ->waitForRoute('login')
                ->assertRouteIs('login');
        });
    }

    /**
     * TC-PBI10-03: Citizen berhasil mengirim laporan insiden lengkap dan melihat kode laporan.
     */
    public function test_citizen_can_submit_incident_report_and_see_incident_code(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();
        $testImagePath = base_path('tests/Browser/fixtures/test-photo.jpg');

        $this->browse(function (Browser $browser) use ($citizen, $incidentType, $forestArea, $testImagePath): void {
            $browser->loginAs($citizen)
                ->visitRoute('citizen.incidents.create')
                ->waitForText('Buat Laporan Insiden');

            // Pilih jenis insiden (radio button pertama)
            $browser->radio('incident_type_id', (string) $incidentType->id);

            // Pilih kawasan hutan
            $browser->select('forest_area_id', (string) $forestArea->id);

            // Isi deskripsi (min 15 karakter)
            $browser->type('description', 'Ditemukan penebangan pohon liar di kawasan hutan lindung ini.');

            // Upload foto
            $browser->attach('photo', $testImagePath);

            // Set koordinat GPS via JS (dalam batas Jawa Barat)
            $browser->script(
                'document.querySelector(\'input[name="latitude"]\').value = \'-6.9000\';'
                .'document.querySelector(\'input[name="longitude"]\').value = \'107.6500\';'
            );

            // Kirim laporan
            $browser->press('Kirim Laporan')
                ->waitForText('Laporan berhasil terkirim', 15)
                ->assertSee('Laporan berhasil terkirim');

            // Pastikan kode laporan INC- tampil
            $browser->assertSeeIn('.font-mono', 'INC-');
        });
    }

    /**
     * TC-PBI10-04: Validasi — deskripsi terlalu pendek menampilkan pesan error.
     */
    public function test_validation_error_shown_for_short_description(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();
        $testImagePath = base_path('tests/Browser/fixtures/test-photo.jpg');

        $this->browse(function (Browser $browser) use ($citizen, $incidentType, $forestArea, $testImagePath): void {
            $browser->loginAs($citizen)
                ->visitRoute('citizen.incidents.create')
                ->waitForText('Buat Laporan Insiden');

            $browser->radio('incident_type_id', (string) $incidentType->id);
            $browser->select('forest_area_id', (string) $forestArea->id);

            // Deskripsi kurang dari 15 karakter
            $browser->type('description', 'Singkat');

            $browser->attach('photo', $testImagePath);

            $browser->script(
                'document.querySelector(\'input[name="latitude"]\').value = \'-6.9000\';'
                .'document.querySelector(\'input[name="longitude"]\').value = \'107.6500\';'
            );

            $browser->press('Kirim Laporan')
                ->waitForText('Terdapat kesalahan dalam pengisian formulir', 15)
                ->assertSee('Terdapat kesalahan dalam pengisian formulir');
        });
    }

    /**
     * TC-PBI10-05: Ranger dapat mengakses halaman form laporan insiden ranger.
     */
    public function test_ranger_can_access_ranger_incident_report_form(): void
    {
        $ranger = User::factory()->ranger()->create();

        $this->browse(function (Browser $browser) use ($ranger): void {
            $browser->loginAs($ranger)
                ->visitRoute('ranger.incidents.create')
                ->waitForText('Buat Laporan Insiden')
                ->assertSee('Buat Laporan Insiden')
                ->assertPresent('input[name=incident_type_id]')
                ->assertPresent('#forest_area_id');
        });
    }
}
