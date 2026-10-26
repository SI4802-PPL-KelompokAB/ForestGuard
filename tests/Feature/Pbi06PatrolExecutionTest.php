<?php

namespace Tests\Feature;

use App\Enums\PatrolStatus;
use App\Models\Checkpoint;
use App\Models\Patrol;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Pbi06PatrolExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_server_time_survives_reload_and_finish_keeps_unvisited_checkpoints(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 01:00:00', 'UTC'));
        $patrol = Patrol::factory()->create(['scheduled_at' => now('UTC')->addDay()]);
        $checkpoint = Checkpoint::factory()->for($patrol->forestArea)->create();
        $patrol->patrolCheckpoints()->create(['client_uuid' => (string) Str::uuid(), 'checkpoint_id' => $checkpoint->id, 'sequence' => 1]);

        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.start', $patrol), [
            'started_at' => '2000-01-01', 'ended_at' => '2000-01-01', 'ranger_id' => $patrol->created_by, 'status' => 'Selesai',
        ])->assertOk()->assertJsonPath('patrol.status', 'Berlangsung')->assertJsonPath('patrol.started_at', '2026-10-26T01:00:00.000000Z')->assertJsonPath('patrol.ended_at', null);
        $this->travel(90)->seconds();
        $this->getJson(route('ranger.patrols.show', $patrol))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('patrol.duration_seconds', 90)->assertJsonPath('patrol.started_at', '2026-10-26T01:00:00.000000Z');
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSee('08:00:00')->assertSee('00:01:30')->assertSee('Akhiri patroli');
        $this->postJson(route('ranger.patrols.finish', $patrol), ['ended_at' => '1990-01-01'])->assertOk()
            ->assertJsonPath('patrol.status', 'Selesai')->assertJsonPath('patrol.duration_seconds', 90)
            ->assertJsonPath('patrol.started_at', '2026-10-26T01:00:00.000000Z')->assertJsonPath('patrol.ended_at', '2026-10-26T01:01:30.000000Z');

        $patrol->refresh();
        $this->assertSame(PatrolStatus::Completed, $patrol->status);
        $this->assertSame($patrol->ranger_id, $patrol->ranger->id);
        $this->assertSame(90, $patrol->durationSeconds());
        $this->assertNull($patrol->patrolCheckpoints->sole()->visited_at);
        $this->assertNull($patrol->total_distance_km);
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSee('Belum dikunjungi')
            ->assertDontSee('data-patrol-mutation', false)->assertDontSee('data-finish-open', false);
    }

    public function test_active_patrol_blocks_other_assignment_with_current_state_and_link(): void
    {
        $active = Patrol::factory()->inProgress()->create();
        $other = Patrol::factory()->for($active->ranger, 'ranger')->create();

        $this->actingAs($active->ranger)->postJson(route('ranger.patrols.start', $other))->assertConflict()
            ->assertJsonPath('patrol.status', 'Ditugaskan')->assertJsonPath('active_patrol_id', $active->id);
        $this->get(route('ranger.patrols.show', $other))->assertOk()->assertSee('Selesaikan patroli yang sedang berlangsung')
            ->assertSee(route('ranger.patrols.show', $active))->assertDontSee('Mulai patroli');

        $this->assertSame(PatrolStatus::Assigned, $other->fresh()->status);
        $this->assertNull($other->fresh()->started_at);
    }

    public function test_duplicate_start_and_finish_return_conflict_without_overwriting_timestamps(): void
    {
        $this->freezeTime();
        $patrol = Patrol::factory()->create();
        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.start', $patrol))->assertOk();
        $started = $patrol->fresh()->getRawOriginal('started_at');
        $this->travel(5)->minutes();

        $this->postJson(route('ranger.patrols.start', $patrol))->assertConflict()->assertJsonPath('patrol.status', 'Berlangsung');
        $this->assertSame($started, $patrol->fresh()->getRawOriginal('started_at'));
        $this->postJson(route('ranger.patrols.finish', $patrol))->assertOk();
        $ended = $patrol->fresh()->getRawOriginal('ended_at');
        $this->travel(5)->minutes();
        $this->postJson(route('ranger.patrols.finish', $patrol))->assertConflict()->assertJsonPath('patrol.status', 'Selesai');
        $this->postJson(route('ranger.patrols.start', $patrol))->assertConflict();

        $this->assertSame($started, $patrol->fresh()->getRawOriginal('started_at'));
        $this->assertSame($ended, $patrol->fresh()->getRawOriginal('ended_at'));
    }

    public function test_assigned_cannot_finish_and_html_conflict_displays_current_detail(): void
    {
        $patrol = Patrol::factory()->create();

        $this->actingAs($patrol->ranger)->post(route('ranger.patrols.finish', $patrol))->assertConflict()
            ->assertSee('Status patroli sudah berubah.')->assertSee($patrol->code)->assertSee('Ditugaskan');

        $this->assertNull($patrol->fresh()->started_at);
        $this->assertNull($patrol->fresh()->ended_at);
        $this->assertSame(PatrolStatus::Assigned, $patrol->fresh()->status);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_roles_cannot_read_or_mutate_ranger_patrols(string $role): void
    {
        $patrol = Patrol::factory()->create();
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)->get(route('ranger.patrols.index'))->assertForbidden();
        $this->getJson(route('ranger.patrols.show', $patrol))->assertForbidden();
        $this->postJson(route('ranger.patrols.start', $patrol))->assertForbidden();
        $this->postJson(route('ranger.patrols.finish', $patrol))->assertForbidden();

        $this->assertSame(PatrolStatus::Assigned, $patrol->fresh()->status);
        $this->assertNull($patrol->fresh()->started_at);
    }

    public function test_guest_and_other_ranger_cannot_access_or_mutate_task(): void
    {
        $patrol = Patrol::factory()->create();
        $this->get(route('ranger.patrols.index'))->assertRedirect(route('login'));
        $this->postJson(route('ranger.patrols.start', $patrol))->assertUnauthorized();
        $this->postJson(route('ranger.patrols.finish', $patrol))->assertUnauthorized();
        $this->actingAs(User::factory()->ranger()->create())->getJson(route('ranger.patrols.show', $patrol))->assertNotFound()->assertDontSee($patrol->code);
        $this->postJson(route('ranger.patrols.start', $patrol))->assertNotFound()->assertDontSee($patrol->code);
        $this->postJson(route('ranger.patrols.finish', $patrol))->assertNotFound()->assertDontSee($patrol->code);

        $this->assertSame(PatrolStatus::Assigned, $patrol->fresh()->status);
        $this->assertNull($patrol->fresh()->started_at);
    }

    public function test_inactive_ranger_is_rejected(): void
    {
        $patrol = Patrol::factory()->create();
        $patrol->ranger->update(['is_active' => false]);

        $this->actingAs($patrol->ranger->fresh())->postJson(route('ranger.patrols.start', $patrol))->assertRedirect(route('login'));

        $this->assertNull($patrol->fresh()->started_at);
    }

    public function test_deleted_master_does_not_block_historical_detail_or_execution(): void
    {
        $patrol = Patrol::factory()->create();
        $checkpoint = Checkpoint::factory()->for($patrol->forestArea)->create();
        $patrol->patrolCheckpoints()->create(['client_uuid' => (string) Str::uuid(), 'checkpoint_id' => $checkpoint->id, 'sequence' => 1]);
        $checkpoint->delete();
        $patrol->forestArea->delete();

        $this->actingAs($patrol->ranger)->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSee($checkpoint->name);
        $this->postJson(route('ranger.patrols.start', $patrol))->assertOk();
        $this->postJson(route('ranger.patrols.finish', $patrol))->assertOk();

        $this->assertSame(PatrolStatus::Completed, $patrol->fresh()->status);
        $this->assertNull($patrol->patrolCheckpoints->sole()->visited_at);
    }

    public function test_duration_is_never_negative_and_completed_duration_does_not_grow(): void
    {
        $this->freezeTime();
        $patrol = Patrol::factory()->inProgress()->create(['started_at' => now('UTC')->addMinute()]);
        $this->assertSame(0, $patrol->durationSeconds());

        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.finish', $patrol))->assertOk()
            ->assertJsonPath('patrol.duration_seconds', 0)->assertJsonPath('patrol.ended_at', now('UTC')->toISOString());
        $this->travel(2)->hours();

        $this->assertSame(0, $patrol->fresh()->durationSeconds());
    }

    public function test_list_prioritizes_active_then_scheduled_assignments_then_recent_completions(): void
    {
        $ranger = User::factory()->ranger()->create();
        $finished = Patrol::factory()->completed()->for($ranger, 'ranger')->create(['ended_at' => now('UTC')->subDays(2)]);
        $later = Patrol::factory()->for($ranger, 'ranger')->create(['scheduled_at' => now('UTC')->addDay()]);
        $active = Patrol::factory()->inProgress()->for($ranger, 'ranger')->create();
        $sooner = Patrol::factory()->for($ranger, 'ranger')->create(['scheduled_at' => now('UTC')]);
        $recent = Patrol::factory()->completed()->for($ranger, 'ranger')->create();

        $this->actingAs($ranger)->get(route('ranger.patrols.index'))->assertOk()
            ->assertViewHas('patrols', fn ($patrols) => $patrols->pluck('id')->all() === [$active->id, $sooner->id, $later->id, $recent->id, $finished->id]);
    }

    public function test_failed_status_write_returns_error_without_partial_transition(): void
    {
        $patrol = Patrol::factory()->create();
        Patrol::updating(function (Patrol $changing): void {
            DB::table('patrols')->where('id', $changing->id)->update(['ranger_id' => null]);
        });

        try {
            $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.start', $patrol))
                ->assertInternalServerError()->assertJsonPath('message', 'Perubahan belum dapat dikonfirmasi. Periksa kembali status patroli setelah koneksi tersedia.');
        } finally {
            Patrol::flushEventListeners();
        }

        $this->assertSame(PatrolStatus::Assigned, $patrol->fresh()->status);
        $this->assertNull($patrol->fresh()->started_at);
        $this->assertNull($patrol->fresh()->ended_at);
    }

    /** @return array<string, array{string}> */
    public static function forbiddenRoles(): array
    {
        return ['admin' => ['admin'], 'citizen' => ['citizen']];
    }
}
