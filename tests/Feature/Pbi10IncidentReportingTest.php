<?php

namespace Tests\Feature;

use App\Enums\PatrolStatus;
use App\Models\Evidence;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Patrol;
use App\Models\StatusHistory;
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

class Pbi10IncidentReportingTest extends TestCase
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

    public function test_guest_cannot_access_or_submit_incident_report(): void
    {
        $this->get(route('citizen.incidents.create'))
            ->assertRedirect(route('login'));

        $this->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'description' => 'Contoh laporan tanpa autentikasi pengguna.',
        ])->assertRedirect(route('login'));
    }

    public function test_citizen_can_view_incident_reporting_form(): void
    {
        $citizen = User::factory()->citizen()->create();

        $response = $this->actingAs($citizen)->get(route('citizen.incidents.create'));

        $response->assertOk();
        $response->assertSee('Buat Laporan Insiden');
        $response->assertSee('Kebakaran hutan');
        $response->assertSee('Taman Nasional Gunung Ciremai');
    }

    public function test_citizen_can_submit_valid_incident_report_and_assert_database_records(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();
        $clientUuid = (string) Str::uuid();

        $photo = UploadedFile::fake()->image('bukti_lapangan.jpg', 800, 600)->size(1500);

        $response = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => $clientUuid,
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Ditemukan tumpukan kayu jati hasil tebangan liar di petak 4.',
            'latitude' => -6.7574000,
            'longitude' => 107.6199200,
            'gps_accuracy' => 12.5,
            'photo' => $photo,
        ]);

        $incident = Incident::sole();

        $response->assertRedirect(route('citizen.incidents.show', $incident));
        $response->assertSessionHas('incident_code', $incident->code);

        // 1. Assert Incidents Table
        $this->assertSame($clientUuid, $incident->client_uuid);
        $this->assertSame($citizen->id, $incident->reporter_id);
        $this->assertSame($type->id, $incident->incident_type_id);
        $this->assertSame($area->id, $incident->forest_area_id);
        $this->assertNull($incident->patrol_id);
        $this->assertStringStartsWith('INC-', $incident->code);
        $this->assertSame('Reported', $incident->caseStatus->name);
        $this->assertSame('Ditemukan tumpukan kayu jati hasil tebangan liar di petak 4.', $incident->description);
        $this->assertEquals(-6.7574, (float) $incident->latitude);
        $this->assertEquals(107.61992, (float) $incident->longitude);

        // 2. Assert Evidences Table & Storage
        $evidence = Evidence::sole();
        $this->assertSame($incident->id, $evidence->incident_id);
        $this->assertSame($citizen->id, $evidence->uploaded_by);
        $this->assertNull($evidence->observation_id);
        $this->assertNull($evidence->update_id);
        $this->assertEquals(-6.7574, (float) $evidence->latitude);
        Storage::disk('public')->assertExists($evidence->file_path);

        // 3. Assert Status Histories Table
        $history = StatusHistory::sole();
        $this->assertSame($incident->id, $history->incident_id);
        $this->assertNull($history->from_status_id);
        $this->assertSame($incident->case_status_id, $history->to_status_id);
        $this->assertSame($citizen->id, $history->changed_by);
        $this->assertNotNull($history->changed_at);

        // 4. Assert Detail View can be viewed by Reporter
        $this->actingAs($citizen)->get(route('citizen.incidents.show', $incident))
            ->assertOk()
            ->assertSee($incident->code)
            ->assertSee('Ditemukan tumpukan kayu jati');
    }

    public function test_ranger_can_submit_report_with_associated_patrol(): void
    {
        $ranger = User::factory()->ranger()->create();
        $area = ForestArea::firstOrFail();
        $patrol = Patrol::factory()->create([
            'ranger_id' => $ranger->id,
            'forest_area_id' => $area->id,
            'status' => PatrolStatus::InProgress,
        ]);
        $type = IncidentType::firstOrFail();
        $photo = UploadedFile::fake()->image('temuan_ranger.png', 400, 300)->size(500);

        $response = $this->actingAs($ranger)->post(route('ranger.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Ditemukan bekas perapian dan tenda liar mencurigakan di lereng barat.',
            'latitude' => -6.7890000,
            'longitude' => 107.6540000,
            'patrol_id' => $patrol->id,
            'photo' => $photo,
        ]);

        $incident = Incident::sole();
        $response->assertRedirect(route('ranger.incidents.show', $incident));
        $this->assertSame($patrol->id, $incident->patrol_id);
    }

    public function test_description_length_boundary_validation(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();
        $photo = UploadedFile::fake()->image('bukti.jpg')->size(200);

        // 14 characters -> must fail
        $response = $this->actingAs($citizen)->from(route('citizen.incidents.create'))->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Ada api kecil!', // 14 chars
            'latitude' => -6.75,
            'longitude' => 107.61,
            'photo' => $photo,
        ]);

        $response->assertRedirect(route('citizen.incidents.create'));
        $response->assertSessionHasErrors(['description']);
        $this->assertDatabaseEmpty('incidents');

        // 15 characters -> must pass
        $responseValid = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => '123456789012345', // exactly 15 chars
            'latitude' => -6.75,
            'longitude' => 107.61,
            'photo' => $photo,
        ]);

        $responseValid->assertSessionHasNoErrors();
        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_gps_coordinates_are_required_and_bounded(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();
        $photo = UploadedFile::fake()->image('bukti.jpg')->size(200);

        // Missing coordinates
        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Ditemukan perambahan kayu di blok utara.',
            'latitude' => null,
            'longitude' => null,
            'photo' => $photo,
        ])->assertSessionHasErrors(['latitude', 'longitude']);

        // Out of bounds latitude (> 90) & longitude (> 180)
        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Ditemukan perambahan kayu di blok utara.',
            'latitude' => 110.5,
            'longitude' => 200.0,
            'photo' => $photo,
        ])->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertDatabaseEmpty('incidents');
    }

    public function test_photo_validation_rejects_oversized_and_non_image_files(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();

        // Oversized (> 5MB)
        $largePhoto = UploadedFile::fake()->image('large.jpg')->size(6000);
        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Ditemukan kebakaran semak di perbatasan.',
            'latitude' => -6.75,
            'longitude' => 107.61,
            'photo' => $largePhoto,
        ])->assertSessionHasErrors(['photo']);

        // Non-image file (PDF)
        $pdfFile = UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf');
        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Ditemukan kebakaran semak di perbatasan.',
            'latitude' => -6.75,
            'longitude' => 107.61,
            'photo' => $pdfFile,
        ])->assertSessionHasErrors(['photo']);

        $this->assertDatabaseEmpty('incidents');
    }

    public function test_occurred_at_cannot_be_in_the_future(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();
        $photo = UploadedFile::fake()->image('foto.jpg')->size(300);

        $futureTime = now()->addDays(2)->format('Y-m-d H:i:s');

        $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => (string) Str::uuid(),
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => $futureTime,
            'description' => 'Kejadian di masa depan tidak diperbolehkan.',
            'latitude' => -6.75,
            'longitude' => 107.61,
            'photo' => $photo,
        ])->assertSessionHasErrors(['occurred_at']);

        $this->assertDatabaseEmpty('incidents');
    }

    public function test_idempotency_with_identical_client_uuid(): void
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();
        $clientUuid = (string) Str::uuid();

        $photo = UploadedFile::fake()->image('bukti.jpg')->size(500);

        // First attempt
        $response1 = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => $clientUuid,
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Laporan pertama dengan UUID idempotensi.',
            'latitude' => -6.75,
            'longitude' => 107.61,
            'photo' => $photo,
        ]);

        $this->assertDatabaseCount('incidents', 1);

        // Second attempt with exact same client_uuid
        $response2 = $this->actingAs($citizen)->post(route('citizen.incidents.store'), [
            'client_uuid' => $clientUuid,
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'occurred_at' => now()->format('Y-m-d H:i:s'),
            'description' => 'Laporan duplikat retry jaringan.',
            'latitude' => -6.75,
            'longitude' => 107.61,
            'photo' => $photo,
        ]);

        // Still only 1 incident in database
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('evidences', 1);
        $this->assertDatabaseCount('status_histories', 1);
    }
}
