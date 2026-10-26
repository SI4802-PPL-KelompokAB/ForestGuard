<?php

namespace Tests\Feature;

use App\Models\CaseStatus;
use App\Models\Checkpoint;
use App\Models\Evidence;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Observation;
use App\Models\Patrol;
use App\Models\RiskLevel;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class Pbi01SetupAndDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_page_returns_successful_response(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('ForestGuard');
        $response->assertSee('Masuk ke akun');
    }

    public function test_seeder_produces_expected_master_and_sample_records(): void
    {
        // 3 Roles
        $this->assertDatabaseCount('roles', 3);
        $this->assertTrue(Role::where('name', Role::ADMIN)->exists());
        $this->assertTrue(Role::where('name', Role::RANGER)->exists());
        $this->assertTrue(Role::where('name', Role::CITIZEN)->exists());

        // 8 Case Statuses
        $this->assertDatabaseCount('case_statuses', 8);
        $resolved = CaseStatus::where('name', 'Resolved')->firstOrFail();
        $rejected = CaseStatus::where('name', 'Rejected')->firstOrFail();
        $this->assertTrue($resolved->is_terminal);
        $this->assertTrue($rejected->is_terminal);

        $underVerification = CaseStatus::where('name', 'Under Verification')->firstOrFail();
        $this->assertFalse($underVerification->is_terminal);

        // 6 Incident Types
        $this->assertDatabaseCount('incident_types', 6);
        $this->assertTrue(IncidentType::where('name', 'Kebakaran hutan')->exists());
        $this->assertTrue(IncidentType::where('name', 'Dugaan illegal logging')->exists());
        $this->assertTrue(IncidentType::where('name', 'Ancaman satwa liar')->exists());

        // 4 Risk Levels
        $this->assertDatabaseCount('risk_levels', 4);
        $this->assertEquals([1, 2, 3, 4], RiskLevel::orderBy('sort_order')->pluck('sort_order')->all());

        // 2-3 Forest Areas and Checkpoints
        $this->assertGreaterThanOrEqual(2, ForestArea::count());
        $this->assertGreaterThanOrEqual(10, Checkpoint::count());
        $this->assertTrue(Checkpoint::where('code', 'CP-GC-01')->exists());

        // 5 Sample Users (1 admin, 2 rangers, 2 citizens)
        $this->assertDatabaseCount('users', 5);
        $this->assertEquals(1, User::whereHas('role', fn ($q) => $q->where('name', Role::ADMIN))->count());
        $this->assertEquals(2, User::whereHas('role', fn ($q) => $q->where('name', Role::RANGER))->count());
        $this->assertEquals(2, User::whereHas('role', fn ($q) => $q->where('name', Role::CITIZEN))->count());
    }

    public function test_seeder_is_idempotent_when_run_multiple_times(): void
    {
        $roleCount = Role::count();
        $statusCount = CaseStatus::count();
        $typeCount = IncidentType::count();
        $riskCount = RiskLevel::count();
        $areaCount = ForestArea::count();
        $checkpointCount = Checkpoint::count();
        $userCount = User::count();

        // Seed a second time
        $this->seed(DatabaseSeeder::class);

        $this->assertEquals($roleCount, Role::count());
        $this->assertEquals($statusCount, CaseStatus::count());
        $this->assertEquals($typeCount, IncidentType::count());
        $this->assertEquals($riskCount, RiskLevel::count());
        $this->assertEquals($areaCount, ForestArea::count());
        $this->assertEquals($checkpointCount, Checkpoint::count());
        $this->assertEquals($userCount, User::count());
    }

    public function test_evidence_insertion_with_exactly_one_parent_is_allowed(): void
    {
        $user = User::firstOrFail();
        $area = ForestArea::firstOrFail();
        $type = IncidentType::firstOrFail();
        $status = CaseStatus::firstOrFail();

        $incident = Incident::create([
            'code' => 'INC-TEST-001',
            'client_uuid' => (string) Str::uuid(),
            'reporter_id' => $user->id,
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'case_status_id' => $status->id,
            'description' => 'Test insiden',
            'latitude' => -6.893,
            'longitude' => 108.407,
            'occurred_at' => now(),
        ]);

        $evidence = Evidence::create([
            'client_uuid' => (string) Str::uuid(),
            'incident_id' => $incident->id,
            'observation_id' => null,
            'update_id' => null,
            'uploaded_by' => $user->id,
            'file_path' => 'evidences/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'latitude' => -6.893,
            'longitude' => 108.407,
            'captured_at' => now(),
        ]);

        $this->assertDatabaseHas('evidences', ['id' => $evidence->id]);
    }

    public function test_evidence_insertion_with_zero_parents_is_rejected_by_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = User::firstOrFail();

        Evidence::create([
            'client_uuid' => (string) Str::uuid(),
            'incident_id' => null,
            'observation_id' => null,
            'update_id' => null,
            'uploaded_by' => $user->id,
            'file_path' => 'evidences/test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'latitude' => -6.893,
            'longitude' => 108.407,
            'captured_at' => now(),
        ]);
    }

    public function test_evidence_insertion_with_multiple_parents_is_rejected_by_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = User::firstOrFail();
        $area = ForestArea::firstOrFail();
        $type = IncidentType::firstOrFail();
        $status = CaseStatus::firstOrFail();

        $incident = Incident::create([
            'code' => 'INC-TEST-002',
            'client_uuid' => (string) Str::uuid(),
            'reporter_id' => $user->id,
            'incident_type_id' => $type->id,
            'forest_area_id' => $area->id,
            'case_status_id' => $status->id,
            'description' => 'Test insiden 2',
            'latitude' => -6.893,
            'longitude' => 108.407,
            'occurred_at' => now(),
        ]);

        $patrol = Patrol::create([
            'code' => 'PAT-TEST-001',
            'client_uuid' => (string) Str::uuid(),
            'ranger_id' => $user->id,
            'forest_area_id' => $area->id,
            'created_by' => $user->id,
            'scheduled_at' => now(),
            'status' => 'Ditugaskan',
        ]);

        $observation = Observation::create([
            'client_uuid' => (string) Str::uuid(),
            'patrol_id' => $patrol->id,
            'category' => 'Kondisi hutan',
            'description' => 'Observasi test',
            'latitude' => -6.893,
            'longitude' => 108.407,
            'observed_at' => now(),
        ]);

        Evidence::create([
            'client_uuid' => (string) Str::uuid(),
            'incident_id' => $incident->id,
            'observation_id' => $observation->id,
            'update_id' => null,
            'uploaded_by' => $user->id,
            'file_path' => 'evidences/test2.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'latitude' => -6.893,
            'longitude' => 108.407,
            'captured_at' => now(),
        ]);
    }

    public function test_evidence_database_check_constraint_rejects_invalid_rows(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Database check constraint test applies to non-SQLite databases.');
        }

        $user = User::firstOrFail();

        $this->expectException(QueryException::class);

        // Direct DB insert bypassing Eloquent model event
        DB::table('evidences')->insert([
            'client_uuid' => (string) Str::uuid(),
            'incident_id' => null,
            'observation_id' => null,
            'update_id' => null,
            'uploaded_by' => $user->id,
            'file_path' => 'evidences/direct.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'latitude' => -6.893,
            'longitude' => 108.407,
            'captured_at' => now(),
        ]);
    }
}
