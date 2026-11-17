<?php

namespace Tests\Browser;

use App\Enums\PatrolStatus;
use App\Models\Checkpoint;
use App\Models\Patrol;
use App\Models\PatrolTrack;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\DuskTestCase;

class Pbi07PatrolTrackTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[TestDox('TC07-01: Guest / Unauthenticated cannot record GPS track (HTTP 401 Unauthorized)')]
    public function test_tc07_01_guest_cannot_record_gps_tracks(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();
        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.917464,
            'longitude' => 107.619123,
            'recorded_at' => now('UTC')->toISOString(),
        ];

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->logout()
                ->visitRoute('ranger.patrols.tracks.index', $patrol)
                ->slow()
                ->assertRouteIs('login');
        });

        $this->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertUnauthorized();
    }

    #[TestDox('TC07-02: Citizen or unassigned ranger cannot record GPS track on another ranger patrol (HTTP 403 / 404)')]
    public function test_tc07_02_citizen_or_unassigned_ranger_cannot_record_tracks(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();
        $otherRanger = User::factory()->ranger()->create();
        $citizen = User::factory()->citizen()->create();
        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.917464,
            'longitude' => 107.619123,
            'recorded_at' => now('UTC')->toISOString(),
        ];

        $this->browse(function (Browser $browser) use ($citizen, $patrol): void {
            $browser->loginAs($citizen)
                ->visit('/ranger/patroli/'.$patrol->id)
                ->slow()
                ->assertSee('403');
        });

        // Citizen cannot record track (403 Forbidden)
        $this->actingAs($citizen)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertForbidden();

        // Other ranger cannot record track for this patrol (404 Not Found)
        $this->actingAs($otherRanger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertNotFound();
    }

    #[TestDox('TC07-03: Cannot record GPS track if patrol status is not in_progress (HTTP 409 Conflict)')]
    public function test_tc07_03_cannot_record_track_if_patrol_is_not_in_progress(): void
    {
        $this->seed(RoleSeeder::class);
        $assignedPatrol = Patrol::factory()->create(['status' => PatrolStatus::Assigned]);
        $completedPatrol = Patrol::factory()->completed()->create();

        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.917464,
            'longitude' => 107.619123,
        ];

        $this->browse(function (Browser $browser) use ($assignedPatrol): void {
            $browser->loginAs($assignedPatrol->ranger)
                ->visitRoute('ranger.patrols.show', $assignedPatrol)
                ->slow()
                ->assertSee('Ditugaskan')
                ->assertMissing('[data-gps-indicator]');
        });

        $this->actingAs($assignedPatrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $assignedPatrol), $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'Titik rute hanya dapat dicatat saat patroli berstatus sedang berlangsung.');

        $this->actingAs($completedPatrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $completedPatrol), $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'Titik rute hanya dapat dicatat saat patroli berstatus sedang berlangsung.');
    }

    #[TestDox('TC07-04: Cannot record track with invalid coordinates or malformed client uuid (HTTP 422 Unprocessable Entity)')]
    public function test_tc07_04_validates_track_coordinates_and_uuid(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)
                ->visitRoute('ranger.patrols.show', $patrol)
                ->slow()
                ->assertPresent('#patrol-map');
        });

        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), [
                'client_uuid' => 'invalid-uuid',
                'latitude' => 120,
                'longitude' => 200,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_uuid', 'latitude', 'longitude']);
    }

    #[TestDox('TC07-05: Ranger records initial valid GPS coordinate with zero total distance (HTTP 201 Created)')]
    public function test_tc07_05_records_first_track_point_successfully(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();
        $uuid = (string) Str::uuid();

        $response = $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), [
                'client_uuid' => $uuid,
                'latitude' => -6.917464,
                'longitude' => 107.619123,
                'recorded_at' => '2026-10-27T10:00:00Z',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Titik rute berhasil dicatat.')
            ->assertJsonPath('tracks_count', 1);

        $this->assertDatabaseHas('patrol_tracks', [
            'patrol_id' => $patrol->id,
            'client_uuid' => $uuid,
            'latitude' => -6.9174640,
            'longitude' => 107.6191230,
        ]);

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)
                ->visitRoute('ranger.patrols.show', $patrol)
                ->slow()
                ->assertSee('Berlangsung')
                ->assertPresent('#patrol-map');
        });

        $patrol->refresh();
        $this->assertNull($patrol->total_distance_km);
    }

    #[TestDox('TC07-06: Ranger records next GPS coordinate ~231m away and total distance accumulates via Haversine')]
    public function test_tc07_06_calculates_distance_between_consecutive_points_accurately(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();

        $uuid1 = (string) Str::uuid();
        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), [
                'client_uuid' => $uuid1,
                'latitude' => -6.902476,
                'longitude' => 107.618721,
                'recorded_at' => '2026-10-27T10:00:00Z',
            ])
            ->assertCreated();

        $uuid2 = (string) Str::uuid();
        $response = $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), [
                'client_uuid' => $uuid2,
                'latitude' => -6.900400,
                'longitude' => 107.618721,
                'recorded_at' => '2026-10-27T10:05:00Z',
            ]);

        $response->assertCreated()
            ->assertJsonPath('tracks_count', 2);

        $patrol->refresh();
        $this->assertNotNull($patrol->total_distance_km);
        $this->assertEqualsWithDelta(0.231, (float) $patrol->total_distance_km, 0.01);

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)
                ->visitRoute('ranger.patrols.show', $patrol)
                ->slow()
                ->assertSee('Berlangsung')
                ->assertPresent('[data-patrol-distance]')
                ->assertPresent('#patrol-map');
        });
    }

    #[TestDox('TC07-07: Ranger sends batch array of GPS coordinates post-offline and distance accumulates')]
    public function test_tc07_07_records_batch_of_tracks_and_accumulates_distance(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();

        $batch = [
            'tracks' => [
                [
                    'client_uuid' => (string) Str::uuid(),
                    'latitude' => -6.902476,
                    'longitude' => 107.618721,
                    'recorded_at' => '2026-10-27T10:00:00Z',
                ],
                [
                    'client_uuid' => (string) Str::uuid(),
                    'latitude' => -6.900400,
                    'longitude' => 107.618721,
                    'recorded_at' => '2026-10-27T10:05:00Z',
                ],
                [
                    'client_uuid' => (string) Str::uuid(),
                    'latitude' => -6.898000,
                    'longitude' => 107.618721,
                    'recorded_at' => '2026-10-27T10:10:00Z',
                ],
            ],
        ];

        $response = $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), $batch);

        $response->assertCreated()
            ->assertJsonPath('tracks_count', 3);

        $patrol->refresh();
        $this->assertEqualsWithDelta(0.498, (float) $patrol->total_distance_km, 0.01);

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)
                ->visitRoute('ranger.patrols.show', $patrol)
                ->slow()
                ->assertPresent('#patrol-map');
        });
    }

    #[TestDox('TC07-08: Resending payload with duplicate client uuid is idempotent with no duplicate rows')]
    public function test_tc07_08_track_recording_is_idempotent_with_same_client_uuid(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();
        $uuid = (string) Str::uuid();

        $payload = [
            'client_uuid' => $uuid,
            'latitude' => -6.902476,
            'longitude' => 107.618721,
            'recorded_at' => '2026-10-27T10:00:00Z',
        ];

        // First send
        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertCreated();

        // Resend same client_uuid
        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertCreated();

        $this->assertSame(1, PatrolTrack::where('patrol_id', $patrol->id)->count());

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)
                ->visitRoute('ranger.patrols.show', $patrol)
                ->slow()
                ->assertSee('Berlangsung');
        });
    }

    #[TestDox('TC07-09: Admin can access patrol route tracks endpoint and view JSON coordinates (HTTP 200 OK)')]
    public function test_tc07_09_admin_can_fetch_all_tracks_for_any_patrol(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $patrol = Patrol::factory()->inProgress()->create();
        PatrolTrack::factory()->for($patrol)->count(4)->create();

        $this->browse(function (Browser $browser) use ($admin, $patrol): void {
            $browser->loginAs($admin)
                ->visitRoute('admin.patrols.show', $patrol)
                ->slow()
                ->assertSee('Peta Rute Patroli')
                ->assertPresent('#patrol-map');
        });

        $response = $this->actingAs($admin)
            ->getJson(route('admin.patrols.tracks.index', $patrol));

        $response->assertOk()
            ->assertJsonPath('patrol_id', $patrol->id)
            ->assertJsonCount(4, 'tracks');
    }

    #[TestDox('TC07-10: Admin can view patrol route map with checkpoints and distance in browser')]
    public function test_tc07_10_admin_can_view_patrol_route_map_with_checkpoints_and_distance_in_browser(): void
    {
        $this->seed(RoleSeeder::class);

        $patrol = Patrol::factory()->inProgress()->create([
            'total_distance_km' => 1.45,
        ]);

        $checkpoint = Checkpoint::factory()->for($patrol->forestArea)->create([
            'name' => 'Pos Pantau Satu',
            'code' => 'CP-01',
            'latitude' => -6.8560000,
            'longitude' => 107.6320000,
        ]);

        $patrol->patrolCheckpoints()->create([
            'client_uuid' => (string) Str::uuid(),
            'checkpoint_id' => $checkpoint->id,
            'sequence' => 1,
        ]);

        // Simpan titik lintasan rute GPS
        $patrol->tracks()->create([
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.8560000,
            'longitude' => 107.6320000,
            'recorded_at' => now('UTC')->subMinutes(10),
            'distance_from_previous_km' => 0.00,
        ]);

        $patrol->tracks()->create([
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.8650000,
            'longitude' => 107.6410000,
            'recorded_at' => now('UTC')->subMinutes(5),
            'distance_from_previous_km' => 1.45,
        ]);

        $admin = User::factory()->admin()->create();

        $this->browse(function (Browser $browser) use ($admin, $patrol): void {
            $browser->loginAs($admin)
                ->slow()
                ->visitRoute('admin.patrols.show', $patrol)
                ->waitForText('Peta Rute Patroli')
                ->slow()
                ->assertSee('Peta Rute Patroli')
                ->assertSee($patrol->forestArea->name)
                ->assertPresent('[data-patrol-distance]')
                ->assertPresent('#patrol-map')
                ->slow();
        });
    }

    #[TestDox('TC07-11: In progress patrol shows active tracking badge and gps indicator for ranger in browser')]
    public function test_tc07_11_in_progress_patrol_shows_active_tracking_badge_and_gps_indicator_for_ranger_in_browser(): void
    {
        $this->seed(RoleSeeder::class);

        $ranger = User::factory()->ranger()->create();
        $patrol = Patrol::factory()->inProgress()->for($ranger, 'ranger')->create();

        $this->browse(function (Browser $browser) use ($ranger, $patrol): void {
            $browser->loginAs($ranger)
                ->slow()
                ->visitRoute('ranger.patrols.show', $patrol)
                ->waitForText('Peta Rute Patroli')
                ->slow()
                ->assertSee('Peta Rute Patroli')
                ->assertSee('Tracking Aktif')
                ->assertPresent('[data-gps-indicator]')
                ->assertPresent('#patrol-map')
                ->slow();
        });
    }
}
