<?php

namespace Tests\Feature;

use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use App\Support\GeoLocationValidator;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Pbi26CoverageValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            CaseStatusSeeder::class,
            IncidentTypeSeeder::class,
            ForestAreaSeeder::class,
        ]);

        Storage::fake('private');
        config()->set('filesystems.evidence_disk', 'private');
    }

    public function test_forest_area_seeder_seeds_valid_west_java_forest_areas_with_boundaries_and_coordinates(): void
    {
        $areas = ForestArea::all();

        $this->assertGreaterThanOrEqual(3, $areas->count());

        foreach ($areas as $area) {
            $this->assertNotEmpty($area->name);
            $this->assertGreaterThan(0, $area->area_ha);
            $this->assertNotNull($area->center_lat);
            $this->assertNotNull($area->center_lng);

            // Koordinat pusat kawasan harus berada dalam cakupan Jawa Barat (TR-17)
            $this->assertTrue(
                GeoLocationValidator::isWithinWestJava((float) $area->center_lat, (float) $area->center_lng),
                "Kawasan {$area->name} memiliki koordinat di luar cakupan Jawa Barat."
            );

            // Boundary GeoJSON harus terstruktur dengan benar
            $boundary = $area->boundary;
            $this->assertIsArray($boundary);
            $this->assertSame('Polygon', $boundary['type'] ?? null);
            $this->assertIsArray($boundary['coordinates'][0] ?? null);
            $this->assertGreaterThanOrEqual(4, count($boundary['coordinates'][0]));
        }
    }

    public function test_geolocation_validator_correctly_evaluates_west_java_bounds(): void
    {
        // Titik-titik di dalam Jawa Barat
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-6.917464, 107.619123)); // Bandung
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-6.893000, 108.407000)); // Gn Ciremai (Kuningan)
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-6.780000, 106.980000)); // Gn Gede Pangrango
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-7.700000, 108.655000)); // Pangandaran
        $this->assertTrue(GeoLocationValidator::isWithinWestJava(-6.710000, 106.550000)); // Halimun Salak
        $this->assertTrue(GeoLocationValidator::isWithinWestJava('-6.856', '107.632'));   // String numeric format

        // Titik-titik di luar Jawa Barat (TR-17)
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-6.966667, 110.416664)); // Semarang (Jawa Tengah)
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-7.2575, 112.7521));     // Surabaya (Jawa Timur)
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-7.7956, 110.3695));     // Yogyakarta
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-5.4500, 105.2667));     // Lampung (Sumatera)
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(-8.4095, 115.1889));     // Bali
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(35.6762, 139.6503));     // Tokyo
        $this->assertFalse(GeoLocationValidator::isWithinWestJava(null, 107.6));           // Null value
        $this->assertFalse(GeoLocationValidator::isWithinWestJava('invalid', 'coord'));    // Non-numeric
    }

    public function test_geolocation_validator_point_in_polygon_ray_casting(): void
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

        // Titik di dalam poligon
        $this->assertTrue(GeoLocationValidator::isPointInPolygon(-6.8, 107.1, $polygon));

        // Titik di luar poligon
        $this->assertFalse(GeoLocationValidator::isPointInPolygon(-7.0, 107.1, $polygon));
        $this->assertFalse(GeoLocationValidator::isPointInPolygon(-6.8, 107.3, $polygon));
    }

    public function test_citizen_incident_report_accepts_coordinates_within_west_java(): void
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

    public function test_citizen_incident_report_rejects_coordinates_outside_west_java_bounds(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();

        // Laporan dengan koordinat di Jawa Tengah / Semarang (-6.966667, 110.416664)
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

    public function test_ranger_incident_report_rejects_coordinates_outside_west_java_bounds(): void
    {
        $ranger = User::factory()->ranger()->create();
        $incidentType = IncidentType::firstOrFail();
        $forestArea = ForestArea::firstOrFail();

        // Laporan ranger di luar cakupan Jawa Barat (koordinat Bali: -8.4095, 115.1889)
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
}
