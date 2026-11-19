<?php

namespace Tests\Feature;

use App\Enums\ObservationCategory;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Observation;
use App\Models\Patrol;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Pbi25LocationPickerTest extends TestCase
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

        Storage::fake('private');
        config()->set('filesystems.evidence_disk', 'private');
    }

    public function test_citizen_can_submit_report_with_selected_coordinates(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();
        $photo = UploadedFile::fake()->image('bukti_lokasi.jpg', 800, 600)->size(2000);

        $response = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Laporan dengan lokasi yang dipilih dari peta dan disimpan sesuai koordinat nyata.',
            'latitude' => -6.75740,
            'longitude' => 107.61992,
            'gps_accuracy' => 18.5,
            'photo' => $photo,
        ]);

        $response->assertSessionHasNoErrors();

        $incident = Incident::sole();
        $this->assertEquals(-6.7574, (float) $incident->latitude);
        $this->assertEquals(107.61992, (float) $incident->longitude);
        $this->assertEquals(18.5, (float) $incident->gps_accuracy);
    }

    public function test_location_coordinates_and_accuracy_are_rejected_when_missing_or_invalid(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();
        $photo = UploadedFile::fake()->image('bukti_lokasi_invalid.jpg', 800, 600)->size(1200);

        $missingLocationResponse = $this->actingAs($citizen)->from(route('citizen.incidents.create'))->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Laporan tanpa lokasi harus ditolak oleh validasi server.',
            'photo' => $photo,
        ]);

        $missingLocationResponse->assertRedirect(route('citizen.incidents.create'));
        $missingLocationResponse->assertSessionHasErrors(['latitude', 'longitude']);
        $this->assertStringContainsString(
            'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            $missingLocationResponse->getSession()->get('errors')->first('latitude')
        );
        $this->assertDatabaseEmpty('incidents');

        $nonNumericLocationResponse = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Koordinat nonnumerik tidak boleh diterima.',
            'latitude' => 'bukan-angka',
            'longitude' => 'bukan-angka',
            'gps_accuracy' => 5,
            'photo' => $photo,
        ]);

        $nonNumericLocationResponse->assertSessionHasErrors(['latitude', 'longitude']);
        $this->assertStringContainsString(
            'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.',
            $nonNumericLocationResponse->getSession()->get('errors')->first('latitude')
        );
        $this->assertDatabaseEmpty('incidents');

        $outOfRangeResponse = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Koordinat di luar area valid tidak boleh diterima.',
            'latitude' => 91.0,
            'longitude' => 181.0,
            'gps_accuracy' => 5,
            'photo' => $photo,
        ]);

        $outOfRangeResponse->assertSessionHasErrors(['latitude', 'longitude']);
        $this->assertDatabaseEmpty('incidents');

        $negativeAccuracyResponse = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Akurasi GPS negatif tidak boleh diterima.',
            'latitude' => -6.75740,
            'longitude' => 107.61992,
            'gps_accuracy' => -1.0,
            'photo' => $photo,
        ]);

        $negativeAccuracyResponse->assertSessionHasErrors(['gps_accuracy']);
        $this->assertStringContainsString('Akurasi GPS tidak boleh bernilai negatif.', $negativeAccuracyResponse->getSession()->get('errors')->first('gps_accuracy'));
        $this->assertDatabaseEmpty('incidents');
    }

    public function test_ranger_can_store_observation_with_selected_coordinates(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $coordinates = [
            'client_uuid' => (string) Str::uuid(),
            'category' => ObservationCategory::ForestCondition->value,
            'description' => 'Pohon tumbang ditemukan di jalur patroli.',
            'latitude' => '-6.75740',
            'longitude' => '107.61992',
            'location_recorded_at' => now()->toISOString(),
        ];

        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.observations.store', $patrol), $coordinates)
            ->assertCreated();

        $observation = Observation::sole();
        $this->assertSame('-6.7574000', $observation->latitude);
        $this->assertSame('107.6199200', $observation->longitude);
    }

    public function test_observation_without_or_with_invalid_coordinates_is_rejected_without_saving(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $data = [
            'client_uuid' => (string) Str::uuid(),
            'category' => ObservationCategory::ForestCondition->value,
            'description' => 'Pohon tumbang ditemukan di jalur patroli.',
            'location_recorded_at' => now()->toISOString(),
        ];

        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.observations.store', $patrol), $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude'])
            ->assertJsonPath('errors.latitude.0', 'Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.');
        $this->assertDatabaseCount('observations', 0);

        foreach ([
            [
                'coordinates' => ['latitude' => 'bukan-angka', 'longitude' => '107.61992'],
                'errors' => ['latitude'],
            ],
            [
                'coordinates' => ['latitude' => '91', 'longitude' => '181'],
                'errors' => ['latitude', 'longitude'],
            ],
        ] as $invalidCoordinates) {
            $this->postJson(route('ranger.patrols.observations.store', $patrol), array_merge(
                $data,
                ['client_uuid' => (string) Str::uuid()],
                $invalidCoordinates['coordinates'],
            ))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($invalidCoordinates['errors']);

            $this->assertDatabaseCount('observations', 0);
        }
    }
}
