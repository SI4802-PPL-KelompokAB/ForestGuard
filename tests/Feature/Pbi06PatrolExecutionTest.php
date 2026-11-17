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
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class Pbi06PatrolExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[TestDox('TC-PBI06-02a, TC-PBI06-02b, TC-PBI06-03, TC-PBI06-05a, TC-PBI06-05b: server time and unvisited checkpoints')]
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

    #[TestDox('TC-PBI06-04a: the second task cannot start while another patrol is active')]
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

    #[TestDox('TC-PBI06-06b, TC-PBI06-07a, TC-PBI06-07b: terminal status and retries preserve timestamps')]
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

    #[TestDox('TC-PBI06-06a: finishing an assigned patrol returns a conflict with current state')]
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
    #[TestDox('TC-PBI06-08b: other roles cannot read or mutate ranger tasks ($role)')]
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

    #[TestDox('TC-PBI06-01b, TC-PBI06-08a: guest and cross-ranger access disclose no task metadata')]
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

    #[TestDox('TC-PBI06-08c: an inactive ranger is logged out without starting the patrol')]
    public function test_inactive_ranger_is_rejected(): void
    {
        $patrol = Patrol::factory()->create();
        $patrol->ranger->update(['is_active' => false]);

        $this->actingAs($patrol->ranger->fresh())->postJson(route('ranger.patrols.start', $patrol))->assertRedirect(route('login'));

        $this->assertNull($patrol->fresh()->started_at);
    }

    #[TestDox('TC-PBI06-10: deleted master records remain readable during execution')]
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

    #[TestDox('TC-PBI06-11b: clock skew never produces a negative duration or a fabricated finish time')]
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

    #[TestDox('TC-PBI06-01c: tasks are ordered by active state, schedule and recent completion')]
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

    #[TestDox('TC-PBI06-12: a failed start write rolls back status and time')]
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

    #[TestDox('TC-PBI06-02b: finish ignores forged ownership, assignment and server timestamps')]
    public function test_finish_cannot_overwrite_assignment_fields_or_accept_client_timestamps(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 01:15:00', 'UTC'));
        $patrol = Patrol::factory()->inProgress()->create(['started_at' => CarbonImmutable::parse('2026-10-28 01:00:00', 'UTC')]);
        $original = $patrol->refresh()->getRawOriginal();

        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.finish', $patrol), [
            'ranger_id' => $patrol->created_by, 'created_by' => $patrol->ranger_id,
            'forest_area_id' => 999999, 'code' => 'FORGED', 'scheduled_at' => '2000-01-01',
            'status' => PatrolStatus::Assigned->value, 'started_at' => '2000-01-01', 'ended_at' => '2000-01-01',
        ])->assertOk()->assertJsonPath('patrol.status', 'Selesai')
            ->assertJsonPath('patrol.started_at', '2026-10-28T01:00:00.000000Z')
            ->assertJsonPath('patrol.ended_at', '2026-10-28T01:15:00.000000Z')
            ->assertJsonPath('patrol.duration_seconds', 900);

        foreach (['ranger_id', 'created_by', 'forest_area_id', 'code', 'scheduled_at', 'client_uuid', 'started_at'] as $field) {
            $this->assertSame($original[$field], $patrol->fresh()->getRawOriginal($field));
        }
        $this->assertDatabaseCount('patrols', 1);
    }

    #[TestDox('TC-PBI06-11a: positive final duration and read-only state survive time passing')]
    public function test_completed_duration_and_timestamps_remain_fixed_when_time_passes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 01:15:00', 'UTC'));
        $patrol = Patrol::factory()->completed()->create([
            'started_at' => CarbonImmutable::parse('2026-10-28 01:00:00', 'UTC'),
            'ended_at' => CarbonImmutable::parse('2026-10-28 01:15:00', 'UTC'),
        ]);
        $original = $patrol->refresh()->getRawOriginal();
        $this->travel(2)->hours();

        $this->actingAs($patrol->ranger)->getJson(route('ranger.patrols.show', $patrol))->assertOk()
            ->assertJsonPath('patrol.duration_seconds', 900);
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSee('00:15:00')
            ->assertDontSee('data-patrol-mutation', false)->assertDontSee('data-finish-open', false);

        $this->assertSame($original, $patrol->fresh()->getRawOriginal());
        $this->assertDatabaseCount('patrols', 1);
    }

    #[TestDox('TC-PBI06-01a, TC-PBI06-01c: pagination never includes a different ranger task')]
    public function test_pagination_keeps_ranger_tasks_isolated_on_every_page(): void
    {
        $ranger = User::factory()->ranger()->create();
        Patrol::factory()->count(16)->for($ranger, 'ranger')->create();
        $other = Patrol::factory()->create();

        $first = $this->actingAs($ranger)->get(route('ranger.patrols.index'))->assertOk()->assertDontSee($other->code)->viewData('patrols');
        $second = $this->get(route('ranger.patrols.index', ['page' => 2]))->assertOk()->assertDontSee($other->code)->viewData('patrols');

        $this->assertSame(16, $first->total());
        $this->assertCount(15, $first);
        $this->assertCount(1, $second);
        $this->assertSame([], array_intersect($first->modelKeys(), $second->modelKeys()));
        $this->assertSame([$ranger->id], $second->pluck('ranger_id')->unique()->values()->all());
    }

    #[DataProvider('transports')]
    #[TestDox('TC-PBI06-12: a failed finish write preserves the active patrol ($json)')]
    public function test_failed_finish_rolls_back_and_returns_a_recoverable_error(bool $json): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $original = $patrol->refresh()->getRawOriginal();
        Patrol::updating(function (Patrol $changing): void {
            DB::table('patrols')->where('id', $changing->id)->update(['ranger_id' => null]);
        });
        $message = 'Perubahan belum dapat dikonfirmasi. Periksa kembali status patroli setelah koneksi tersedia.';

        try {
            $this->actingAs($patrol->ranger);
            if ($json) {
                $this->postJson(route('ranger.patrols.finish', $patrol))->assertInternalServerError()->assertJsonPath('message', $message);
            } else {
                $this->post(route('ranger.patrols.finish', $patrol))->assertRedirect(route('ranger.patrols.show', $patrol))
                    ->assertSessionHas('warning', $message);
            }
        } finally {
            Patrol::flushEventListeners();
        }

        $this->assertSame($original, $patrol->fresh()->getRawOriginal());
    }

    #[TestDox('TC-PBI06-08c: an inactive ranger cannot finish an already active task')]
    public function test_deactivated_ranger_cannot_finish_and_is_logged_out(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $patrol->ranger->update(['is_active' => false]);
        $original = $patrol->refresh()->getRawOriginal();

        $this->actingAs($patrol->ranger->fresh())->post(route('ranger.patrols.finish', $patrol))
            ->assertRedirect(route('login'))->assertSessionHasErrors(['email' => 'Akun Anda tidak aktif. Hubungi Admin untuk mengaktifkan kembali.']);

        $this->assertGuest();
        $this->assertSame($original, $patrol->fresh()->getRawOriginal());
    }

    /** @return array<string, array{bool}> */
    public static function transports(): array
    {
        return ['JSON' => [true], 'web' => [false]];
    }

    /** @return array<string, array{string}> */
    public static function forbiddenRoles(): array
    {
        return ['admin' => ['admin'], 'citizen' => ['citizen']];
    }
}
