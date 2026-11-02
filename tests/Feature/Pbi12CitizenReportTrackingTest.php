<?php

namespace Tests\Feature;

use App\Models\CaseStatus;
use App\Models\Evidence;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\InvestigationUpdate;
use App\Models\StatusHistory;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

class Pbi12CitizenReportTrackingTest extends TestCase
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

    public function test_tc_12_01_citizen_list_only_shows_their_own_reports(): void
    {
        $citizenA = User::factory()->citizen()->create();
        $citizenB = User::factory()->citizen()->create();

        $reported = $this->createIncident($citizenA, 'Reported', 'FG-1201');
        $processing = $this->createIncident($citizenA, 'Under Verification', 'FG-1202');
        $resolved = $this->createIncident($citizenA, 'Resolved', 'FG-1203');
        $rejected = $this->createIncident($citizenA, 'Rejected', 'FG-1204');
        $otherCitizenReport = $this->createIncident($citizenB, 'Reported', 'FG-1205');

        $response = $this->actingAs($citizenA)->get(route('citizen.home'));

        $response->assertOk()
            ->assertSee($reported->code)
            ->assertSee($processing->code)
            ->assertSee($resolved->code)
            ->assertSee($rejected->code)
            ->assertDontSee($otherCitizenReport->code)
            ->assertSee('Laporan terkirim')
            ->assertSee('Sedang diproses')
            ->assertSee('Selesai ditangani')
            ->assertViewHas('totalReports', 4)
            ->assertViewHas('processingReports', 2)
            ->assertViewHas('resolvedReports', 1);
    }

    public function test_tc_12_02_citizen_can_view_photo_location_and_current_status_without_internal_notes(): void
    {
        $citizen = User::factory()->citizen()->create();
        $ranger = User::factory()->ranger()->create();
        $incident = $this->createIncident($citizen, 'Under Verification', 'FG-1206');
        $reported = CaseStatus::query()->where('name', 'Reported')->firstOrFail();
        $underVerification = CaseStatus::query()->where('name', 'Under Verification')->firstOrFail();
        $internalNote = 'Catatan investigasi internal jangan tampil';

        $this->recordStatus($incident, null, $reported, $citizen);
        $this->recordStatus($incident, $reported, $underVerification, $citizen, $internalNote);
        InvestigationUpdate::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'incident_id' => $incident->id,
            'ranger_id' => $ranger->id,
            'note' => $internalNote,
            'handling_status_id' => $underVerification->id,
        ]);
        $evidence = Evidence::query()->create([
            'client_uuid' => (string) Str::uuid(),
            'incident_id' => $incident->id,
            'uploaded_by' => $citizen->id,
            'file_path' => 'evidences/private/photo.webp',
            'mime_type' => 'image/webp',
            'file_size' => 1024,
            'latitude' => -6.7574,
            'longitude' => 107.6199,
            'captured_at' => now(),
        ]);

        $response = $this->actingAs($citizen)->get(route('citizen.incidents.show', $incident));

        $response->assertOk()
            ->assertSee($incident->code)
            ->assertSee($incident->forestArea->name)
            ->assertSee('-6.7574000')
            ->assertSee(route('evidence.show', $evidence->id), false)
            ->assertSee('Under Verification')
            ->assertSee('Status saat ini')
            ->assertDontSee($internalNote);
    }

    public function test_tc_12_03_rejected_report_displays_its_rejection_reason(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incident = $this->createIncident(
            $citizen,
            'Rejected',
            'FG-1207',
            ['reject_reason' => 'Lokasi laporan tidak berada di kawasan hutan.']
        );
        $underVerification = CaseStatus::query()->where('name', 'Under Verification')->firstOrFail();
        $rejected = CaseStatus::query()->where('name', 'Rejected')->firstOrFail();

        $this->recordStatus($incident, $underVerification, $rejected, $citizen);

        $response = $this->actingAs($citizen)->get(route('citizen.incidents.show', $incident));

        $response->assertOk()
            ->assertSee('Rejected')
            ->assertSee('Laporan ditolak')
            ->assertSee('Lokasi laporan tidak berada di kawasan hutan.');
    }

    public function test_tc_12_04_citizen_cannot_open_another_citizens_report(): void
    {
        $citizenA = User::factory()->citizen()->create();
        $citizenB = User::factory()->citizen()->create();
        $otherCitizenReport = $this->createIncident($citizenB, 'Reported', 'FG-1208');

        $response = $this->actingAs($citizenA)
            ->get(route('citizen.incidents.show', $otherCitizenReport));

        $response->assertForbidden();
    }

    public function test_tc_12_05_guest_is_redirected_to_login_from_report_list(): void
    {
        $response = $this->get(route('citizen.home'));

        $response->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createIncident(User $reporter, string $statusName, string $code, array $attributes = []): Incident
    {
        return Incident::factory()->create(array_merge([
            'reporter_id' => $reporter->id,
            'incident_type_id' => IncidentType::query()->value('id'),
            'forest_area_id' => ForestArea::query()->value('id'),
            'case_status_id' => CaseStatus::query()->where('name', $statusName)->value('id'),
            'code' => $code,
            'description' => 'Deskripsi laporan untuk pengujian.',
            'latitude' => -6.7574,
            'longitude' => 107.6199,
            'occurred_at' => now()->subHour(),
        ], $attributes));
    }

    private function recordStatus(
        Incident $incident,
        ?CaseStatus $fromStatus,
        CaseStatus $toStatus,
        User $changedBy,
        ?string $note = null
    ): void {
        StatusHistory::query()->create([
            'incident_id' => $incident->id,
            'from_status_id' => $fromStatus?->id,
            'to_status_id' => $toStatus->id,
            'changed_by' => $changedBy->id,
            'note' => $note,
            'changed_at' => now(),
        ]);
    }
}
