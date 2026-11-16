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

class Pbi26CoverageValidationTest extends DuskTestCase
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
     * D-26-01: Memastikan form laporan insiden menampilkan kawasan hutan Jawa Barat hasil seeding.
     */
    public function test_incident_reporting_page_displays_seeded_west_java_forest_areas(): void
    {
        $citizen = User::factory()->citizen()->create();

        $this->browse(function (Browser $browser) use ($citizen): void {
            $browser->loginAs($citizen)
                ->visitRoute('citizen.incidents.create')
                ->waitForText('Buat Laporan Insiden')
                ->assertSee('Perkiraan Kawasan Hutan')
                ->assertSee('Taman Nasional Gunung Ciremai')
                ->assertSee('Taman Hutan Raya Ir. H. Djuanda')
                ->assertSee('Taman Nasional Gunung Gede Pangrango');
        });
    }

    /**
     * D-26-02: Memastikan laporan dengan koordinat di luar Jawa Barat ditolak dan menampilkan pesan error (TR-17).
     */
    public function test_submitting_coordinates_outside_west_java_shows_validation_error(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();

        // Buat file dummy gambar untuk lampiran bukti
        $testImagePath = storage_path('framework/testing/test_evidence.jpg');
        if (! is_dir(dirname($testImagePath))) {
            mkdir(dirname($testImagePath), 0755, true);
        }
        if (! file_exists($testImagePath)) {
            $img = imagecreatetruecolor(200, 200);
            imagejpeg($img, $testImagePath);
            imagedestroy($img);
        }

        $this->browse(function (Browser $browser) use ($citizen, $incidentType, $forestArea, $testImagePath): void {
            $browser->loginAs($citizen)
                ->visitRoute('citizen.incidents.create')
                ->waitForText('Buat Laporan Insiden')
                ->radio('incident_type_id', (string) $incidentType->id)
                ->select('forest_area_id', (string) $forestArea->id)
                ->type('description', 'Percobaan laporan insiden warga dengan titik koordinat di luar wilayah Jawa Barat.')
                ->attach('photo', $testImagePath)
                // Koordinat di Semarang, Jawa Tengah (di luar Bounding Box Jawa Barat)
                ->type('latitude', '-6.966667')
                ->type('longitude', '110.416664')
                ->press('Kirim Laporan')
                ->waitForText('Terdapat kesalahan dalam pengisian formulir')
                ->assertSee('Titik koordinat kejadian berada di luar wilayah cakupan Jawa Barat.');
        });
    }
}
