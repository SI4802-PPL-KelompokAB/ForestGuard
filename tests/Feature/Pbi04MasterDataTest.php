<?php

namespace Tests\Feature;

use App\Models\CaseStatus;
use App\Models\Checkpoint;
use App\Models\ForestArea;
use App\Models\IncidentType;
use App\Models\RiskLevel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Pbi04MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    // --- KAWASAN HUTAN (FOREST AREAS) TESTS ---

    public function test_admin_can_view_forest_areas_index_with_seeded_data(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.master.forest-areas.index'));

        $response->assertOk();
        $response->assertSee('Kawasan Hutan');
        $response->assertSee('Taman Nasional Way Kambas');
    }

    public function test_admin_can_create_forest_area_with_valid_data(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.master.forest-areas.store'), [
            'name' => 'Kawasan Hutan Lindung Baru',
            'description' => 'Wilayah penyangga konservasi baru.',
            'area_ha' => 1250.75,
            'center_lat' => -5.1234567,
            'center_lng' => 105.1234567,
            'boundary' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [
                    [[105.1, -5.1], [105.2, -5.1], [105.2, -5.2], [105.1, -5.2], [105.1, -5.1]],
                ],
            ]),
        ]);

        $response->assertRedirect(route('admin.master.forest-areas.index'));
        $response->assertSessionHas('status', 'Kawasan hutan berhasil ditambahkan.');

        $this->assertDatabaseHas('forest_areas', [
            'name' => 'Kawasan Hutan Lindung Baru',
            'area_ha' => 1250.75,
        ]);
    }

    public function test_forest_area_validation_rejects_invalid_inputs(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('admin.master.forest-areas.create'))
            ->post(route('admin.master.forest-areas.store'), [
                'name' => 'Ab',
                'area_ha' => -5,
                'center_lat' => 100,
                'center_lng' => -200,
                'boundary' => 'invalid-json-text',
            ]);

        $response->assertRedirect(route('admin.master.forest-areas.create'));
        $response->assertSessionHasErrors(['name', 'area_ha', 'center_lat', 'center_lng', 'boundary']);
    }

    public function test_admin_can_update_forest_area(): void
    {
        $area = ForestArea::firstOrFail();

        $response = $this->actingAs($this->admin())
            ->put(route('admin.master.forest-areas.update', $area), [
                'name' => 'Kawasan Diperbarui',
                'description' => 'Deskripsi baru.',
                'area_ha' => 999.50,
                'center_lat' => $area->center_lat,
                'center_lng' => $area->center_lng,
            ]);

        $response->assertRedirect(route('admin.master.forest-areas.index'));
        $response->assertSessionHas('status', 'Kawasan hutan berhasil diperbarui.');

        $this->assertEquals('Kawasan Diperbarui', $area->fresh()->name);
    }

    public function test_admin_can_soft_delete_and_restore_forest_area_with_child_checkpoints(): void
    {
        $area = ForestArea::has('checkpoints')->firstOrFail();
        $checkpointId = $area->checkpoints()->first()->id;

        // Soft Delete
        $response = $this->actingAs($this->admin())
            ->delete(route('admin.master.forest-areas.destroy', $area));

        $response->assertRedirect(route('admin.master.forest-areas.index'));
        $this->assertSoftDeleted($area);
        $this->assertSoftDeleted('checkpoints', ['id' => $checkpointId]);

        // Restore
        $restoreResponse = $this->actingAs($this->admin())
            ->post(route('admin.master.forest-areas.restore', $area));

        $restoreResponse->assertRedirect(route('admin.master.forest-areas.index', ['status' => 'terhapus']));
        $this->assertNotSoftDeleted($area);
        $this->assertNotSoftDeleted('checkpoints', ['id' => $checkpointId]);
    }

    // --- CHECKPOINT TESTS ---

    public function test_admin_can_create_checkpoint_tied_to_active_forest_area(): void
    {
        $area = ForestArea::firstOrFail();

        $response = $this->actingAs($this->admin())->post(route('admin.master.checkpoints.store'), [
            'forest_area_id' => $area->id,
            'code' => 'CP-TEST-99',
            'name' => 'Pos Pantau Baru',
            'latitude' => -5.1234567,
            'longitude' => 105.1234567,
        ]);

        $response->assertRedirect(route('admin.master.checkpoints.index'));
        $response->assertSessionHas('status', 'Checkpoint berhasil ditambahkan.');

        $this->assertDatabaseHas('checkpoints', [
            'code' => 'CP-TEST-99',
            'name' => 'Pos Pantau Baru',
        ]);
    }

    public function test_checkpoint_rejects_soft_deleted_forest_area(): void
    {
        $area = ForestArea::firstOrFail();
        $area->delete();

        $response = $this->actingAs($this->admin())
            ->from(route('admin.master.checkpoints.create'))
            ->post(route('admin.master.checkpoints.store'), [
                'forest_area_id' => $area->id,
                'code' => 'CP-ORPHAN-01',
                'name' => 'Pos Pantau Yatim',
                'latitude' => -5.1,
                'longitude' => 105.1,
            ]);

        $response->assertRedirect(route('admin.master.checkpoints.create'));
        $response->assertSessionHasErrors(['forest_area_id']);
    }

    public function test_checkpoint_rejects_duplicate_code_including_soft_deleted(): void
    {
        $checkpoint = Checkpoint::firstOrFail();
        $checkpoint->delete();

        $area = ForestArea::firstOrFail();

        $response = $this->actingAs($this->admin())
            ->from(route('admin.master.checkpoints.create'))
            ->post(route('admin.master.checkpoints.store'), [
                'forest_area_id' => $area->id,
                'code' => $checkpoint->code,
                'name' => 'Nama Baru Kode Sama',
                'latitude' => -5.1,
                'longitude' => 105.1,
            ]);

        $response->assertRedirect(route('admin.master.checkpoints.create'));
        $response->assertSessionHasErrors(['code']);
    }

    public function test_admin_can_soft_delete_and_restore_checkpoint(): void
    {
        $checkpoint = Checkpoint::firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.master.checkpoints.destroy', $checkpoint))
            ->assertRedirect(route('admin.master.checkpoints.index'));

        $this->assertSoftDeleted($checkpoint);

        $this->actingAs($this->admin())
            ->post(route('admin.master.checkpoints.restore', $checkpoint))
            ->assertRedirect(route('admin.master.checkpoints.index', ['status' => 'terhapus']));

        $this->assertNotSoftDeleted($checkpoint);
    }

    // --- TIPE INSIDEN TESTS ---

    public function test_admin_can_crud_incident_type(): void
    {
        // Create
        $response = $this->actingAs($this->admin())->post(route('admin.master.incident-types.store'), [
            'name' => 'Penebangan Mangrove Ilegal',
        ]);
        $response->assertRedirect(route('admin.master.incident-types.index'));
        $this->assertDatabaseHas('incident_types', ['name' => 'Penebangan Mangrove Ilegal']);

        $incidentType = IncidentType::where('name', 'Penebangan Mangrove Ilegal')->firstOrFail();

        // Update
        $this->actingAs($this->admin())
            ->put(route('admin.master.incident-types.update', $incidentType), [
                'name' => 'Penebangan Mangrove Diperbarui',
            ])->assertRedirect(route('admin.master.incident-types.index'));

        $this->assertEquals('Penebangan Mangrove Diperbarui', $incidentType->fresh()->name);

        // Soft Delete
        $this->actingAs($this->admin())
            ->delete(route('admin.master.incident-types.destroy', $incidentType))
            ->assertRedirect(route('admin.master.incident-types.index'));
        $this->assertSoftDeleted($incidentType);

        // Restore
        $this->actingAs($this->admin())
            ->post(route('admin.master.incident-types.restore', $incidentType))
            ->assertRedirect(route('admin.master.incident-types.index', ['status' => 'terhapus']));
        $this->assertNotSoftDeleted($incidentType);
    }

    // --- TINGKAT RISIKO TESTS ---

    public function test_admin_can_crud_and_toggle_risk_level(): void
    {
        // Create
        $this->actingAs($this->admin())->post(route('admin.master.risk-levels.store'), [
            'name' => 'Bencana Luar Biasa',
            'sort_order' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('admin.master.risk-levels.index'));

        $risk = RiskLevel::where('name', 'Bencana Luar Biasa')->firstOrFail();
        $this->assertTrue($risk->is_active);

        // Toggle to inactive
        $this->actingAs($this->admin())
            ->post(route('admin.master.risk-levels.toggle', $risk))
            ->assertRedirect(route('admin.master.risk-levels.index'));

        $this->assertFalse($risk->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($this->admin())
            ->post(route('admin.master.risk-levels.toggle', $risk))
            ->assertRedirect(route('admin.master.risk-levels.index'));

        $this->assertTrue($risk->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_last_active_risk_level(): void
    {
        // Deactivate all except one
        $activeRisks = RiskLevel::where('is_active', true)->get();
        for ($i = 1; $i < $activeRisks->count(); $i++) {
            $activeRisks[$i]->update(['is_active' => false]);
        }

        $lastActive = $activeRisks->first();

        // Attempting to toggle the last active risk level must fail
        $response = $this->actingAs($this->admin())
            ->from(route('admin.master.risk-levels.index'))
            ->post(route('admin.master.risk-levels.toggle', $lastActive));

        $response->assertSessionHasErrors(['is_active']);
        $this->assertTrue($lastActive->fresh()->is_active);

        // Attempting via form update also must fail
        $updateResponse = $this->actingAs($this->admin())
            ->from(route('admin.master.risk-levels.edit', $lastActive))
            ->put(route('admin.master.risk-levels.update', $lastActive), [
                'name' => $lastActive->name,
                'sort_order' => $lastActive->sort_order,
                'is_active' => '0',
            ]);

        $updateResponse->assertRedirect(route('admin.master.risk-levels.edit', $lastActive));
        $updateResponse->assertSessionHasErrors(['is_active']);
        $this->assertTrue($lastActive->fresh()->is_active);
    }

    // --- STATUS KASUS (READ-ONLY) TESTS ---

    public function test_case_statuses_is_read_only_and_forbids_write_requests(): void
    {
        // GET is allowed and shows seeded statuses
        $response = $this->actingAs($this->admin())->get(route('admin.master.case-statuses.index'));
        $response->assertOk();
        $response->assertSee('Status Kasus');
        $response->assertSee('Reported');
        $response->assertSee('Resolved');
        $response->assertSee('Data Referensi Sistem Tetap');

        // Write requests must be rejected (404/405)
        $this->actingAs($this->admin())->post(route('admin.master.case-statuses.index'))->assertStatus(405);
        $this->actingAs($this->admin())->put(route('admin.master.case-statuses.index'))->assertStatus(405);
        $this->actingAs($this->admin())->delete(route('admin.master.case-statuses.index'))->assertStatus(405);
    }

    // --- RBAC & AUTHORIZATION TESTS ---

    #[DataProvider('nonAdminEmails')]
    public function test_non_admin_users_are_forbidden_from_master_data(string $email): void
    {
        $user = User::where('email', $email)->firstOrFail();

        $this->actingAs($user)->get(route('admin.master.forest-areas.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.checkpoints.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.incident-types.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.risk-levels.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.master.case-statuses.index'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login_from_master_data(): void
    {
        $this->get(route('admin.master.forest-areas.index'))->assertRedirect(route('login'));
        $this->get(route('admin.master.checkpoints.index'))->assertRedirect(route('login'));
        $this->get(route('admin.master.incident-types.index'))->assertRedirect(route('login'));
        $this->get(route('admin.master.risk-levels.index'))->assertRedirect(route('login'));
        $this->get(route('admin.master.case-statuses.index'))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminEmails(): array
    {
        return [
            'ranger' => ['ranger1@forestguard.test'],
            'citizen' => ['citizen1@forestguard.test'],
        ];
    }

    private function admin(): User
    {
        return User::where('email', 'admin@forestguard.test')->firstOrFail();
    }
}
