<?php

namespace Tests\Feature;

use App\Actions\Admin\AssignPatrol;
use App\Enums\PatrolStatus;
use App\Events\PatrolAssigned;
use App\Models\Checkpoint;
use App\Models\ForestArea;
use App\Models\Patrol;
use App\Models\PatrolCheckpoint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;

class Pbi05PatrolAssignmentTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test case Jira: TC-PBI05-02a, TC-PBI05-02c, TC-PBI05-08.
     * TC-PBI05-02a: Penugasan atomik, status awal, jadwal UTC, UUID dan urutan checkpoint benar.
     * TC-PBI05-02c: Pembuat, kode, status dan waktu aktual tidak dapat dipalsukan klien.
     * TC-PBI05-08: Tugas hanya diserahkan ke Ranger tujuan dengan jadwal/checkpoint benar.
     */
    #[TestDox('TC-PBI05-02a, TC-PBI05-02c, TC-PBI05-08: assignment, protected fields and ranger handoff')]
    public function test_assignment_is_atomic_ordered_utc_and_visible_only_to_its_ranger(): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $area = ForestArea::factory()->create();
        $second = Checkpoint::factory()->for($area)->create(['code' => 'CP-B']);
        $first = Checkpoint::factory()->for($area)->create(['code' => 'CP-A']);
        Event::fake([PatrolAssigned::class]);

        $this->actingAs($admin)->postJson(route('admin.patrols.store'), [
            'ranger_id' => $ranger->id, 'forest_area_id' => $area->id,
            'scheduled_at' => '2026-01-01T08:30', 'checkpoint_ids' => [$second->id, $first->id],
            'created_by' => $ranger->id, 'status' => PatrolStatus::Completed->value,
            'started_at' => '2020-01-01', 'code' => 'FORGED',
        ])->assertCreated();

        $patrol = Patrol::sole();
        $this->assertSame($admin->id, $patrol->created_by);
        $this->assertSame($ranger->id, $patrol->ranger_id);
        $this->assertSame(PatrolStatus::Assigned, $patrol->status);
        $this->assertNull($patrol->started_at);
        $this->assertNull($patrol->ended_at);
        $this->assertNull($patrol->total_distance_km);
        $this->assertSame('2026-01-01 01:30:00', $patrol->getRawOriginal('scheduled_at'));
        $this->assertSame([$first->id, $second->id], $patrol->patrolCheckpoints->pluck('checkpoint_id')->all());
        $this->assertSame([1, 2], $patrol->patrolCheckpoints->pluck('sequence')->all());
        foreach ($patrol->patrolCheckpoints as $planned) {
            $this->assertTrue(Str::isUuid($planned->client_uuid));
            $this->assertNull($planned->visited_at);
            $this->assertNull($planned->latitude);
            $this->assertNull($planned->longitude);
        }
        Event::assertDispatched(PatrolAssigned::class, fn ($event) => $event->patrolId === $patrol->id && $event->rangerId === $ranger->id && $event->createdBy === $admin->id);
        $this->actingAs($admin)->get(route('admin.patrols.show', $patrol))->assertOk()->assertSee('08:30');
        $this->actingAs($ranger)->get(route('ranger.patrols.index'))->assertOk()->assertSee($patrol->code);
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSeeInOrder(['CP-A', 'CP-B']);
        $this->actingAs(User::factory()->ranger()->create())->get(route('ranger.patrols.index'))->assertOk()->assertDontSee($patrol->code);
        $this->get(route('ranger.patrols.show', $patrol))->assertNotFound();
    }

    /**
     * Test case Jira: TC-PBI05-01a.
     * TC-PBI05-01a: Pilihan Ranger/area/checkpoint valid dan pagination stabil.
     */
    #[TestDox('TC-PBI05-01a: only valid master options are offered')]
    public function test_admin_sees_only_valid_options_and_active_checkpoints_for_selected_area(): void
    {
        $admin = User::factory()->admin()->create();
        $active = User::factory()->ranger()->create();
        $inactive = User::factory()->ranger()->inactive()->create();
        $deleted = User::factory()->ranger()->create();
        $deleted->delete();
        $area = ForestArea::factory()->create();
        $checkpoint = Checkpoint::factory()->for($area)->create();
        $removed = Checkpoint::factory()->for($area)->create();
        $removed->delete();
        $deletedArea = ForestArea::factory()->create();
        $deletedArea->delete();
        Checkpoint::factory()->create();

        $this->actingAs($admin)->get(route('admin.patrols.index'))->assertOk()
            ->assertSee($active->name)->assertDontSee($inactive->name)->assertDontSee($deleted->name)->assertDontSee($deletedArea->name);
        $this->getJson(route('admin.patrols.checkpoints', $area))->assertOk()->assertExactJson([
            ['id' => $checkpoint->id, 'code' => $checkpoint->code, 'name' => $checkpoint->name],
        ]);
    }

    /**
     * Test case Jira: TC-PBI05-03a.
     * TC-PBI05-03a: Semua field wajib dan array checkpoint kosong ditolak; input web valid dipertahankan.
     */
    #[TestDox('TC-PBI05-03a: missing required fields never create an assignment')]
    public function test_required_fields_are_rejected_without_persistence(): void
    {
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.patrols.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['ranger_id', 'forest_area_id', 'scheduled_at', 'checkpoint_ids']);

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
    }

    /**
     * Test case Jira: TC-PBI05-03b, TC-PBI05-04a, TC-PBI05-04b, TC-PBI05-04c, TC-PBI05-05a.
     * TC-PBI05-03b: Tanggal mustahil dan format jadwal tidak sah ditolak.
     * TC-PBI05-04a: Checkpoint duplikat ditolak.
     * TC-PBI05-04b: Checkpoint dari area lain ditolak.
     * TC-PBI05-04c: Checkpoint terhapus/tidak ada ditolak.
     * TC-PBI05-05a: Ranger nonaktif, terhapus, tidak ada, Citizen, dan Admin ditolak sebagai petugas.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('invalidSelections')]
    #[TestDox('TC-PBI05-03b, TC-PBI05-04a, TC-PBI05-04b, TC-PBI05-04c, TC-PBI05-05a: invalid selections are rejected ($invalid)')]
    public function test_invalid_or_changed_master_is_rejected(string $invalid): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        $payload = ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-01-01T08:30', 'checkpoint_ids' => [$checkpoint->id]];
        $field = 'checkpoint_ids';
        switch ($invalid) {
            case 'duplicate': $payload['checkpoint_ids'][] = $checkpoint->id;
                break;
            case 'wrong_area': $payload['forest_area_id'] = ForestArea::factory()->create()->id;
                break;
            case 'deleted_checkpoint': $checkpoint->delete();
                break;
            case 'missing_checkpoint': $payload['checkpoint_ids'] = [999999];
                break;
            case 'inactive_ranger': $ranger->update(['is_active' => false]);
                $field = 'ranger_id';
                break;
            case 'deleted_ranger': $ranger->delete();
                $field = 'ranger_id';
                break;
            case 'citizen': $payload['ranger_id'] = User::factory()->citizen()->create()->id;
                $field = 'ranger_id';
                break;
            case 'deleted_area': $checkpoint->forestArea->delete();
                $field = 'forest_area_id';
                break;
            case 'invalid_date': $payload['scheduled_at'] = '2026-02-30T10:00';
                $field = 'scheduled_at';
                break;
            case 'missing_ranger': $payload['ranger_id'] = 999999;
                $field = 'ranger_id';
                break;
            case 'missing_area': $payload['forest_area_id'] = 999999;
                $field = 'forest_area_id';
                break;
            case 'admin': $payload['ranger_id'] = $admin->id;
                $field = 'ranger_id';
                break;
            case 'invalid_date_format': $payload['scheduled_at'] = 'not-a-date';
                $field = 'scheduled_at';
                break;
        }

        $this->actingAs($admin)->postJson(route('admin.patrols.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field === 'checkpoint_ids' && $invalid === 'duplicate' ? 'checkpoint_ids.0' : $field);

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
    }

    /**
     * Test case Jira: TC-PBI05-06.
     * TC-PBI05-06: Insert checkpoint kedua gagal: rollback penuh, tanpa event, error HTTP/web dan input lama benar.
     */
    #[TestDox('TC-PBI05-06: checkpoint failure rolls back the complete assignment')]
    public function test_failed_checkpoint_insert_rolls_back_and_never_publishes_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        Event::fake([PatrolAssigned::class]);
        PatrolCheckpoint::creating(function (): void {
            throw new RuntimeException('Simulated checkpoint failure');
        });

        try {
            app(AssignPatrol::class)->handle($admin, ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-01-01T08:30', 'checkpoint_ids' => [$checkpoint->id]]);
            $this->fail('Insert failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated checkpoint failure', $exception->getMessage());
        } finally {
            PatrolCheckpoint::flushEventListeners();
        }

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
        Event::assertNotDispatched(PatrolAssigned::class);
    }

    /**
     * Test case Jira: TC-PBI05-09a, TC-PBI05-09b, TC-PBI05-09c.
     * TC-PBI05-09a: Event baru terbit sesudah transaksi terluar commit.
     * TC-PBI05-09b: Rollback transaksi luar tidak meninggalkan data/event.
     * TC-PBI05-09c: Listener gagal sesudah commit tidak membatalkan penugasan sah.
     */
    #[TestDox('TC-PBI05-09a, TC-PBI05-09b, TC-PBI05-09c: event commit, rollback and listener failure')]
    public function test_event_waits_for_outer_commit_and_listener_failure_does_not_undo_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        $payload = ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-01-01T08:30', 'checkpoint_ids' => [$checkpoint->id]];
        $published = [];
        Event::listen(PatrolAssigned::class, function ($event) use (&$published): void {
            $published[] = $event->patrolId;
            throw new RuntimeException('Notification unavailable');
        });

        DB::beginTransaction();
        app(AssignPatrol::class)->handle($admin, $payload);
        $this->assertSame([], $published);
        DB::rollBack();
        $this->assertSame([], $published);
        $this->assertDatabaseCount('patrols', 0);
        $patrol = app(AssignPatrol::class)->handle($admin, $payload);

        $this->assertSame([$patrol->id], $published);
        $this->assertModelExists($patrol);
    }

    /**
     * Test case Jira: TC-PBI05-05b.
     * TC-PBI05-05b: Master yang berubah setelah form dibuka diperiksa ulang di transaksi.
     */
    #[TestDox('TC-PBI05-05b: master is checked inside the assignment transaction')]
    public function test_transaction_revalidates_master_after_request_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        $ranger->update(['is_active' => false]);

        try {
            app(AssignPatrol::class)->handle($admin, ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-01-01T08:30', 'checkpoint_ids' => [$checkpoint->id]]);
            $this->fail('Changed master must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ranger_id', $exception->errors());
        }
        $this->assertDatabaseCount('patrols', 0);
    }

    /**
     * Test case Jira: TC-PBI05-07a, TC-PBI05-07b.
     * TC-PBI05-07a: Guest ditolak sesuai kontrak autentikasi.
     * TC-PBI05-07b: Ranger/Citizen tidak dapat memakai endpoint Admin.
     */
    #[TestDox('TC-PBI05-07a, TC-PBI05-07b: guest and non-admin access is refused')]
    public function test_admin_endpoints_reject_other_roles_and_guests(): void
    {
        $area = ForestArea::factory()->create();
        $patrol = Patrol::factory()->create();
        $this->get(route('admin.patrols.index'))->assertRedirect(route('login'));
        $this->postJson(route('admin.patrols.store'), [])->assertUnauthorized();
        foreach ([User::factory()->ranger()->create(), User::factory()->citizen()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.patrols.index'))->assertForbidden();
            $this->get(route('admin.patrols.show', $patrol))->assertForbidden();
            $this->getJson(route('admin.patrols.checkpoints', $area))->assertForbidden();
            $this->postJson(route('admin.patrols.store'), [])->assertForbidden();
        }
        $this->assertDatabaseCount('patrols', 1);
    }

    /**
     * Test tambahan PBI-05; belum dipetakan ke nomor test case Jira.
     * Memastikan nama master di-escape agar skrip tidak dieksekusi dan detail tetap terbaca setelah master dihapus.
     */
    public function test_detail_escapes_master_names_and_retains_deleted_master(): void
    {
        $patrol = Patrol::factory()->create();
        $patrol->forestArea->update(['name' => '<script>alert(1)</script>']);
        $patrol->forestArea->delete();

        $this->actingAs($patrol->ranger)->get(route('ranger.patrols.show', $patrol))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * Test case Jira: TC-PBI05-02b.
     * TC-PBI05-02b: Jadwal lampau/bertumpuk diterima dengan kode dan UUID unik.
     */
    #[TestDox('TC-PBI05-02b: past and overlapping schedules have distinct server identifiers')]
    public function test_multiple_assignments_for_the_same_ranger_allow_past_and_overlapping_schedules(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 01:00:00', 'UTC'));
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        $payload = ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-10-27T08:30', 'checkpoint_ids' => [$checkpoint->id]];
        Event::fake([PatrolAssigned::class]);

        $this->actingAs($admin)->postJson(route('admin.patrols.store'), $payload)->assertCreated();
        $this->postJson(route('admin.patrols.store'), $payload)->assertCreated();

        $patrols = Patrol::orderBy('id')->get();
        $this->assertCount(2, $patrols);
        $this->assertCount(2, $patrols->pluck('code')->unique());
        $this->assertCount(2, $patrols->pluck('client_uuid')->unique());
        foreach ($patrols as $patrol) {
            $this->assertSame($ranger->id, $patrol->ranger_id);
            $this->assertSame(PatrolStatus::Assigned, $patrol->status);
            $this->assertSame('2026-10-27 01:30:00', $patrol->getRawOriginal('scheduled_at'));
            $this->assertTrue(Str::isUuid($patrol->client_uuid));
        }
        $this->assertDatabaseCount('patrol_checkpoints', 2);
        Event::assertDispatchedTimes(PatrolAssigned::class, 2);
    }

    /**
     * Test case Jira: TC-PBI05-09a.
     * TC-PBI05-09a: Event baru terbit sesudah transaksi terluar commit.
     */
    #[TestDox('TC-PBI05-09a: a nested assignment event is published only after the outer commit')]
    public function test_assignment_event_is_published_only_after_the_outer_transaction_commits(): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        Event::fake([PatrolAssigned::class]);

        DB::beginTransaction();
        try {
            $patrol = app(AssignPatrol::class)->handle($admin, ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-10-28T08:30', 'checkpoint_ids' => [$checkpoint->id]]);
            Event::assertNotDispatched(PatrolAssigned::class);
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        Event::assertDispatchedTimes(PatrolAssigned::class, 1);
        Event::assertDispatched(PatrolAssigned::class, fn (PatrolAssigned $event): bool => $event->patrolId === $patrol->id && $event->rangerId === $ranger->id && $event->createdBy === $admin->id);
        $this->assertModelExists($patrol);
        $this->assertDatabaseCount('patrol_checkpoints', 1);
    }

    /**
     * Test case Jira: TC-PBI05-03a.
     * TC-PBI05-03a: Semua field wajib dan array checkpoint kosong ditolak; input web valid dipertahankan.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('missingInputs')]
    #[TestDox('TC-PBI05-03a: each missing or empty input returns its field error ($field)')]
    public function test_each_required_input_is_rejected_without_persisting_data(string $field, bool $emptyArray): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        $payload = ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-10-28T08:30', 'checkpoint_ids' => [$checkpoint->id]];
        if ($emptyArray) {
            $payload[$field] = [];
        } else {
            unset($payload[$field]);
        }
        Event::fake([PatrolAssigned::class]);

        $response = $this->actingAs($admin)->postJson(route('admin.patrols.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        if ($field === 'checkpoint_ids') {
            $response->assertJsonPath('errors.checkpoint_ids.0', 'Pilih minimal satu checkpoint.');
        }
        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
        Event::assertNotDispatched(PatrolAssigned::class);
    }

    /**
     * Test case Jira: TC-PBI05-05b.
     * TC-PBI05-05b: Master yang berubah setelah form dibuka diperiksa ulang di transaksi.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('changedMasters')]
    #[TestDox('TC-PBI05-05b: changes after opening the form cannot slip through ($master)')]
    public function test_master_changes_after_opening_the_form_are_rejected(string $master, string $field): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();
        $payload = ['ranger_id' => $ranger->id, 'forest_area_id' => $checkpoint->forest_area_id, 'scheduled_at' => '2026-10-28T08:30', 'checkpoint_ids' => [$checkpoint->id]];
        $this->actingAs($admin)->get(route('admin.patrols.index'))->assertOk();
        match ($master) {
            'ranger' => $ranger->update(['is_active' => false]),
            'area' => $checkpoint->forestArea->delete(),
            'checkpoint' => $checkpoint->delete(),
        };
        Event::fake([PatrolAssigned::class]);

        $this->postJson(route('admin.patrols.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
        Event::assertNotDispatched(PatrolAssigned::class);
    }

    /**
     * Test case Jira: TC-PBI05-06.
     * TC-PBI05-06: Insert checkpoint kedua gagal: rollback penuh, tanpa event, error HTTP/web dan input lama benar.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('transports')]
    #[TestDox('TC-PBI05-06: second checkpoint query failure returns an error and rolls back ($json)')]
    public function test_checkpoint_query_failure_is_atomic_and_preserves_web_input(bool $json): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $area = ForestArea::factory()->create();
        $checkpoints = Checkpoint::factory()->count(2)->for($area)->create();
        $payload = ['ranger_id' => $ranger->id, 'forest_area_id' => $area->id, 'scheduled_at' => '2026-10-28T08:30', 'checkpoint_ids' => $checkpoints->modelKeys()];
        $attempts = 0;
        PatrolCheckpoint::creating(function (PatrolCheckpoint $planned) use (&$attempts): void {
            if (++$attempts === 2) {
                $planned->checkpoint_id = null;
            }
        });
        Event::fake([PatrolAssigned::class]);

        try {
            $this->actingAs($admin)->from(route('admin.patrols.index'));
            if ($json) {
                $this->postJson(route('admin.patrols.store'), $payload)->assertInternalServerError()
                    ->assertJsonPath('message', 'Penugasan belum dapat disimpan. Silakan coba kembali.');
            } else {
                $response = $this->post(route('admin.patrols.store'), $payload)->assertRedirect(route('admin.patrols.index'))
                    ->assertSessionHas('warning', 'Penugasan belum dapat disimpan. Silakan coba kembali.');
                foreach ($payload as $key => $value) {
                    $response->assertSessionHasInput($key, $value);
                }
            }
        } finally {
            PatrolCheckpoint::flushEventListeners();
        }

        $this->assertSame(2, $attempts);
        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
        Event::assertNotDispatched(PatrolAssigned::class);
    }

    /**
     * Test case Jira: TC-PBI05-01a.
     * TC-PBI05-01a: Pilihan Ranger/area/checkpoint valid dan pagination stabil.
     */
    #[TestDox('TC-PBI05-01a: admin assignments use stable pagination')]
    public function test_admin_assignment_list_paginates_without_losing_or_repeating_patrols(): void
    {
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        Patrol::factory()->count(16)->for($admin, 'creator')->for($ranger, 'ranger')->create();

        $first = $this->actingAs($admin)->get(route('admin.patrols.index'))->assertOk()->viewData('patrols');
        $second = $this->get(route('admin.patrols.index', ['page' => 2]))->assertOk()->viewData('patrols');

        $this->assertSame(16, $first->total());
        $this->assertCount(15, $first);
        $this->assertCount(1, $second);
        $this->assertSame([], array_intersect($first->modelKeys(), $second->modelKeys()));
    }

    /** @return array<string, array{string, bool}> */
    public static function missingInputs(): array
    {
        return ['ranger' => ['ranger_id', false], 'area' => ['forest_area_id', false], 'schedule' => ['scheduled_at', false], 'checkpoints missing' => ['checkpoint_ids', false], 'checkpoints empty' => ['checkpoint_ids', true]];
    }

    /** @return array<string, array{string, string}> */
    public static function changedMasters(): array
    {
        return ['ranger deactivated' => ['ranger', 'ranger_id'], 'area deleted' => ['area', 'forest_area_id'], 'checkpoint deleted' => ['checkpoint', 'checkpoint_ids']];
    }

    /** @return array<string, array{bool}> */
    public static function transports(): array
    {
        return ['JSON' => [true], 'web' => [false]];
    }

    /** @return array<string, array{string}> */
    public static function invalidSelections(): array
    {
        return array_combine($cases = ['duplicate', 'wrong_area', 'deleted_checkpoint', 'missing_checkpoint', 'inactive_ranger', 'deleted_ranger', 'citizen', 'deleted_area', 'invalid_date', 'missing_ranger', 'missing_area', 'admin', 'invalid_date_format'], array_map(fn ($case) => [$case], $cases));
    }
}
