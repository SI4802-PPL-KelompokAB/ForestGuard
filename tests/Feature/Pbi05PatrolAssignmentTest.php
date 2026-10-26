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
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
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
        Event::assertDispatched(PatrolAssigned::class, fn ($event) => $event->patrolId === $patrol->id && $event->rangerId === $ranger->id && $event->createdBy === $admin->id);
        $this->actingAs($admin)->get(route('admin.patrols.show', $patrol))->assertOk()->assertSee('08:30');
        $this->actingAs($ranger)->get(route('ranger.patrols.index'))->assertOk()->assertSee($patrol->code);
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSeeInOrder(['CP-A', 'CP-B']);
        $this->actingAs(User::factory()->ranger()->create())->get(route('ranger.patrols.index'))->assertOk()->assertDontSee($patrol->code);
        $this->get(route('ranger.patrols.show', $patrol))->assertNotFound();
    }

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
        Checkpoint::factory()->create();

        $this->actingAs($admin)->get(route('admin.patrols.index'))->assertOk()
            ->assertSee($active->name)->assertDontSee($inactive->name)->assertDontSee($deleted->name);
        $this->getJson(route('admin.patrols.checkpoints', $area))->assertOk()->assertExactJson([
            ['id' => $checkpoint->id, 'code' => $checkpoint->code, 'name' => $checkpoint->name],
        ]);
    }

    public function test_required_fields_are_rejected_without_persistence(): void
    {
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.patrols.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['ranger_id', 'forest_area_id', 'scheduled_at', 'checkpoint_ids']);

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
    }

    #[DataProvider('invalidSelections')]
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
        }

        $this->actingAs($admin)->postJson(route('admin.patrols.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field === 'checkpoint_ids' && $invalid === 'duplicate' ? 'checkpoint_ids.0' : $field);

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
    }

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

    public function test_detail_escapes_master_names_and_retains_deleted_master(): void
    {
        $patrol = Patrol::factory()->create();
        $patrol->forestArea->update(['name' => '<script>alert(1)</script>']);
        $patrol->forestArea->delete();

        $this->actingAs($patrol->ranger)->get(route('ranger.patrols.show', $patrol))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @return array<string, array{string}> */
    public static function invalidSelections(): array
    {
        return array_combine($cases = ['duplicate', 'wrong_area', 'deleted_checkpoint', 'missing_checkpoint', 'inactive_ranger', 'deleted_ranger', 'citizen', 'deleted_area', 'invalid_date'], array_map(fn ($case) => [$case], $cases));
    }
}
