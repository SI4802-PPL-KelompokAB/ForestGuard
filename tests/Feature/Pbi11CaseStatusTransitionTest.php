<?php

namespace Tests\Feature;

use App\Actions\Incidents\TransitionIncidentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\CaseStatus;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\RiskLevel;
use App\Models\StatusHistory;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RiskLevelSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

class Pbi11CaseStatusTransitionTest extends TestCase
{
    use DatabaseMigrations;

    protected CaseStatus $reported;

    protected CaseStatus $underVerification;

    protected CaseStatus $verified;

    protected CaseStatus $assigned;

    protected CaseStatus $investigation;

    protected CaseStatus $monitoring;

    protected CaseStatus $resolved;

    protected CaseStatus $rejected;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            CaseStatusSeeder::class,
            IncidentTypeSeeder::class,
            ForestAreaSeeder::class,
            RiskLevelSeeder::class,
        ]);

        $this->reported = CaseStatus::where('name', 'Reported')->firstOrFail();
        $this->underVerification = CaseStatus::where('name', 'Under Verification')->firstOrFail();
        $this->verified = CaseStatus::where('name', 'Verified')->firstOrFail();
        $this->assigned = CaseStatus::where('name', 'Assigned')->firstOrFail();
        $this->investigation = CaseStatus::where('name', 'Investigation/Response')->firstOrFail();
        $this->monitoring = CaseStatus::where('name', 'Monitoring')->firstOrFail();
        $this->resolved = CaseStatus::where('name', 'Resolved')->firstOrFail();
        $this->rejected = CaseStatus::where('name', 'Rejected')->firstOrFail();
    }

    protected function createIncident(CaseStatus $status): Incident
    {
        $citizen = User::factory()->citizen()->create();
        $type = IncidentType::firstOrFail();
        $area = ForestArea::firstOrFail();

        return Incident::create([
            'code' => 'INC-202610-0099',
            'client_uuid' => (string) Str::uuid(),
            'reporter_id' => $citizen->id,
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'case_status_id' => $status->id,
            'description' => 'Insiden uji coba mesin transisi status kasus.',
            'latitude' => -6.7574,
            'longitude' => 107.6199,
            'occurred_at' => now(),
        ]);
    }

    public function test_citizen_cannot_update_case_status(): void
    {
        $citizen = User::factory()->citizen()->create();
        $incident = $this->createIncident($this->reported);

        $response = $this->actingAs($citizen)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Under Verification',
        ]);

        $response->assertForbidden();
        $this->assertSame($this->reported->id, $incident->fresh()->case_status_id);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $incident = $this->createIncident($this->reported);

        $response = $this->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Under Verification',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_transition_from_reported_to_under_verification(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($this->reported);

        $response = $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Under Verification',
            'note' => 'Admin mulai memverifikasi berkas dan titik lokasi.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Status kasus berhasil diperbarui.');

        $this->assertSame($this->underVerification->id, $incident->fresh()->case_status_id);

        $history = StatusHistory::sole();
        $this->assertSame($incident->id, $history->incident_id);
        $this->assertSame($this->reported->id, $history->from_status_id);
        $this->assertSame($this->underVerification->id, $history->to_status_id);
        $this->assertSame($admin->id, $history->changed_by);
        $this->assertSame('Admin mulai memverifikasi berkas dan titik lokasi.', $history->note);
    }

    public function test_under_verification_to_verified_requires_risk_level(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($this->underVerification);
        $riskLevel = RiskLevel::firstOrFail();

        // 1. Without risk_level_id -> must fail
        $responseFail = $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Verified',
        ]);

        $responseFail->assertSessionHasErrors(['risk_level_id']);
        $this->assertSame($this->underVerification->id, $incident->fresh()->case_status_id);

        // 2. With risk_level_id -> must succeed
        $responsePass = $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Verified',
            'risk_level_id' => $riskLevel->id,
            'note' => 'Laporan terkonfirmasi valid.',
        ]);

        $responsePass->assertSessionHasNoErrors();
        $refreshed = $incident->fresh();
        $this->assertSame($this->verified->id, $refreshed->case_status_id);
        $this->assertSame($riskLevel->id, $refreshed->risk_level_id);
    }

    public function test_under_verification_to_rejected_requires_reason_and_becomes_terminal(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($this->underVerification);

        // 1. Without reject_reason -> fails
        $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Rejected',
            'reject_reason' => '',
        ])->assertSessionHasErrors(['reject_reason']);

        // 2. With reason < 10 characters -> fails
        $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Rejected',
            'reject_reason' => 'Bohong', // 6 chars
        ])->assertSessionHasErrors(['reject_reason']);

        // 3. With valid reason >= 10 characters -> succeeds
        $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Rejected',
            'reject_reason' => 'Lokasi tidak berada di kawasan hutan lindung kami.',
        ])->assertSessionHasNoErrors();

        $refreshed = $incident->fresh();
        $this->assertSame($this->rejected->id, $refreshed->case_status_id);
        $this->assertSame('Lokasi tidak berada di kawasan hutan lindung kami.', $refreshed->reject_reason);

        // 4. Terminal check: trying to transition from Rejected to another status -> rejected
        $this->expectException(InvalidStatusTransitionException::class);
        app(TransitionIncidentStatus::class)->handle($admin, $refreshed, 'Under Verification');
    }

    public function test_illegal_status_skipping_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($this->reported);

        // Cannot skip directly from Reported to Resolved
        $this->expectException(InvalidStatusTransitionException::class);
        app(TransitionIncidentStatus::class)->handle($admin, $incident, 'Resolved');
    }

    public function test_monitoring_to_resolved_requires_resolution_note_and_sets_resolved_at(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($this->monitoring);

        // 1. Missing resolution_note -> fails
        $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Resolved',
            'resolution_note' => '',
        ])->assertSessionHasErrors(['resolution_note']);

        // 2. Valid resolution note -> succeeds
        $this->actingAs($admin)->post(route('admin.incidents.update-status', $incident), [
            'to_status' => 'Resolved',
            'resolution_note' => 'Pelaku penebangan liar telah ditindak dan kayu disita.',
        ])->assertSessionHasNoErrors();

        $refreshed = $incident->fresh();
        $this->assertSame($this->resolved->id, $refreshed->case_status_id);
        $this->assertNotNull($refreshed->resolved_at);
        $this->assertSame('Pelaku penebangan liar telah ditindak dan kayu disita.', $refreshed->resolution_note);

        // 3. Terminal lock: cannot change resolved incident
        $this->expectException(InvalidStatusTransitionException::class);
        app(TransitionIncidentStatus::class)->handle($admin, $refreshed, 'Monitoring');
    }

    public function test_admin_case_detail_displays_chronological_history(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($this->reported);

        // Record 2 status transitions
        app(TransitionIncidentStatus::class)->handle($admin, $incident, 'Under Verification', [
            'note' => 'Pemeriksaan dimulai.',
        ]);

        $riskLevel = RiskLevel::firstOrFail();
        app(TransitionIncidentStatus::class)->handle($admin, $incident->fresh(), 'Verified', [
            'risk_level_id' => $riskLevel->id,
            'note' => 'Verifikasi selesai.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('Under Verification');
        $response->assertSee('Verified');
        $response->assertSee('Pemeriksaan dimulai.');
        $response->assertSee('Verifikasi selesai.');
    }
}
