<?php

namespace Tests\Browser;

use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use App\Support\GeoLocationValidator;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\TestDox;
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

        Storage::fake('public');
    }

    #[TestDox('TC26-01: ForestAreaSeeder seeds valid West Java forest areas with boundary polygons and coordinates')]
    public function test_tc26_01_forest_area_seeder_seeds_valid_west_java_forest_areas(): void
    {
        $areas = ForestArea::all();

        $this->assertGreaterThanOrEqual(3, $areas->count());

        foreach ($areas as $area) {
            $this->assertNotEmpty($area->name);
            $this->assertGreaterThan(0, $area->area_ha);
            $this->assertNotNull($area->center_lat);
            $this->assertNotNull($area->center_lng);

            $this->assertTrue(
                GeoLocationValidator::isWithinWestJava((float) $area->center_lat, (float) $area->center_lng),
                "Kawasan {$area->name} memiliki koordinat di luar cakupan Jawa Barat."
            );

            $boundary = $area->boundary;
            $this->assertIsArray($boundary);
            $this->assertSame('Polygon', $boundary['type'] ?? null);
            $this->assertIsArray($boundary['coordinates'][0] ?? null);
            $this->assertGreaterThanOrEqual(4, count($boundary['coordinates'][0]));
        }
    }

    #[TestDox('TC26-02: GeoLocationValidator evaluates coordinates inside West Java bounding box')]
    public function test_tc26_02_geolocation_validator_correctly_evaluates_west_java_bounds(): void
    {
        // Titik-titik di dalam Jawa Barat
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-6.917464, 107.619123)); // Bandung
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-6.893000, 108.407000)); // Gn Ciremai
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-6.780000, 106.980000)); // Gn Gede Pangrango
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-7.700000, 108.655000)); // Pangandaran
        $this->assertTrue(GeoLocationValidator::isWithinWestJava('-6.856', '107.632'));

        // Titik-titik di luar Jawa Barat (TR-17)
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-6.966667, 110.416664)); // Semarang
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-7.2575, 112.7521));     // Surabaya
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-8.4095, 115.1889));     // Bali
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(null, 107.6));
        $this->assertFalse(GeoLocationValidator::isWithinWestJava('invalid', 'coord'));
    }

    #[TestDox('TC26-03: GeoLocationValidator evaluates point in polygon via Ray-Casting algorithm')]
    public function test_tc26_03_geolocation_validator_point_in_polygon_ray_casting(): void
    {
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [[
                [107.0, -6.9],
                [107.2, -6.9],
                [107.2, -6.7],
                [107.0, -6.7],
                [107.0, -6.9],
            ]],
        ];

        $this->assertTrue(GeoLocationValidator::isPointInPolygon(-6.8, 107.1, $polygon));
        $this->assertFalse(GeoLocationValidator::isPointInPolygon(-7.0, 107.1, $polygon));
        $this->assertFalse(GeoLocationValidator::isPointInPolygon(-6.8, 107.3, $polygon));
    }

    #[TestDox('TC26-04: Citizen incident report accepts coordinates within West Java (HTTP 302 Redirect)')]
    public function test_tc26_04_citizen_incident_report_accepts_coordinates_within_west_java(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::where('name', 'Taman Hutan Raya Ir. H. Djuanda')->firstOrFail();

        $response = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $incidentType->id,
            'forest_area_id' => $forestArea->id,
            'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'description' => 'Terjadi kebakaran semak belukar di lereng tebing dekat jalur patroli.',
            'latitude' => -6.8560000,
            'longitude' => 107.6320000,
            'photo' => UploadedFile::fake()->image('bukti_kebakaran.jpg', 640, 480),
        ]);

        $response->assertSessionHasNoErrors();
        $incident = Incident::sole();
        $response->assertRedirect(route('citizen.incidents.show', $incident));

        $this->assertSame($citizen->id, $incident->reporter_id);
        $this->assertSame($forestArea->id, $incident->forest_area_id);
        $this->assertEqualsWithDelta(-6.8560000, (float) $incident->latitude, 0.0001);
        $this->assertEqualsWithDelta(107.6320000, (float) $incident->longitude, 0.0001);
    }

    #[TestDox('TC26-05: Citizen incident report rejects coordinates outside West Java bounds (TR-17)')]
    public function test_tc26_05_citizen_incident_report_rejects_coordinates_outside_west_java_bounds(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();

        $response = $this->actingAs($citizen)->from(route('citizen.incidents.create'))->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $incidentType->id,
            'forest_area_id' => $forestArea->id,
            'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'description' => 'Percobaan laporan dengan titik koordinat di luar wilayah Jawa Barat.',
            'latitude' => -6.966667,
            'longitude' => 110.416664,
            'photo' => UploadedFile::fake()->image('bukti.jpg', 640, 480),
        ]);

        $response->assertRedirect(route('citizen.incidents.create'));
        $response->assertSessionHasErrors(['latitude']);

        $errors = session('errors')->get('latitude');
        $this->assertContains('Titik koordinat kejadian berada di luar wilayah cakupan Jawa Barat.', $errors);
        $this->assertSame(0, Incident::count());
    }

    #[TestDox('TC26-06: Ranger incident report rejects coordinates outside West Java bounds (TR-17)')]
    public function test_tc26_06_ranger_incident_report_rejects_coordinates_outside_west_java_bounds(): void
    {
        $ranger = User::factory()->ranger()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();

        $response = $this->actingAs($ranger)->from(route('ranger.incidents.create'))->post(route('ranger.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $incidentType->id,
            'forest_area_id' => $forestArea->id,
            'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'description' => 'Patroli menemukan pohon tumbang di koordinat yang salah input.',
            'latitude' => -8.409500,
            'longitude' => 115.188900,
            'photo' => UploadedFile::fake()->image('bukti.jpg', 640, 480),
        ]);

        $response->assertRedirect(route('ranger.incidents.create'));
        $response->assertSessionHasErrors(['latitude']);

        $errors = session('errors')->get('latitude');
        $this->assertContains('Titik koordinat kejadian berada di luar wilayah cakupan Jawa Barat.', $errors);
        $this->assertSame(0, Incident::count());
    }

    #[TestDox('TC26-07: Incident reporting page displays seeded West Java forest areas in browser')]
    public function test_tc26_07_incident_reporting_page_displays_seeded_west_java_forest_areas(): void
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

    #[TestDox('TC26-08: Submitting coordinates outside West Java shows validation error in browser (TR-17)')]
    public function test_tc26_08_submitting_coordinates_outside_west_java_shows_validation_error(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();

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
                ->type('latitude', '-6.966667')
                ->type('longitude', '110.416664')
                ->press('Kirim Laporan')
                ->waitForText('Terdapat kesalahan dalam pengisian formulir')
                ->assertSee('Titik koordinat kejadian berada di luar wilayah cakupan Jawa Barat.');
        });
    }
}
