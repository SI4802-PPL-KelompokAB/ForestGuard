<?php

namespace Tests\Feature;

use App\Enums\PatrolStatus;
use App\Models\Patrol;
use App\Models\PatrolTrack;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Pbi07PatrolTrackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_only_assigned_active_ranger_can_record_tracks(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $otherRanger = User::factory()->ranger()->create();
        $citizen = User::factory()->citizen()->create();

        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.917464,
            'longitude' => 107.619123,
            'recorded_at' => now('UTC')->toISOString(),
        ];

        // Guest cannot record track
        $this->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertUnauthorized();

        // Citizen cannot record track (403 forbidden)
        $this->actingAs($citizen)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertForbidden();

        // Other ranger cannot record track for this patrol (404 not found)
        $this->actingAs($otherRanger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), $payload)
            ->assertNotFound();
    }

    public function test_cannot_record_track_if_patrol_is_not_in_progress(): void
    {
        $assignedPatrol = Patrol::factory()->create(['status' => PatrolStatus::Assigned]);
        $completedPatrol = Patrol::factory()->completed()->create();

        $payload = [
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.917464,
            'longitude' => 107.619123,
        ];

        // Assigned patrol -> 409 Conflict
        $this->actingAs($assignedPatrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $assignedPatrol), $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'Titik rute hanya dapat dicatat saat patroli berstatus sedang berlangsung.');

        // Completed patrol -> 409 Conflict
        $this->actingAs($completedPatrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $completedPatrol), $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'Titik rute hanya dapat dicatat saat patroli berstatus sedang berlangsung.');
    }

    public function test_validates_track_coordinates_and_uuid(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();

        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), [
                'client_uuid' => 'invalid-uuid',
                'latitude' => 120, // out of range
                'longitude' => 200, // out of range
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_uuid', 'latitude', 'longitude']);
    }

    public function test_records_first_track_point_successfully(): void
    {
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

        $patrol->refresh();
        $this->assertNull($patrol->total_distance_km);
    }

    public function test_calculates_distance_between_consecutive_points_accurately(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();

        // Point 1: Gedung Sate, Bandung (-6.902476, 107.618721)
        $uuid1 = (string) Str::uuid();
        $this->actingAs($patrol->ranger)
            ->postJson(route('ranger.patrols.tracks.store', $patrol), [
                'client_uuid' => $uuid1,
                'latitude' => -6.902476,
                'longitude' => 107.618721,
                'recorded_at' => '2026-10-27T10:00:00Z',
            ])
            ->assertCreated();

        // Point 2: Gasibu, Bandung (-6.900400, 107.618721) ~ approx 230 meters north
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
        // Distance between -6.902476 and -6.900400 at same longitude is ~0.231 km
        $this->assertEqualsWithDelta(0.231, (float) $patrol->total_distance_km, 0.01);
    }

    public function test_records_batch_of_tracks_and_accumulates_distance(): void
    {
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
        // Total distance for ~0.231 km + ~0.267 km = ~0.498 km
        $this->assertEqualsWithDelta(0.498, (float) $patrol->total_distance_km, 0.01);
    }

    public function test_track_recording_is_idempotent_with_same_client_uuid(): void
    {
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
    }

    public function test_can_fetch_all_tracks_for_a_patrol(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        PatrolTrack::factory()->for($patrol)->count(3)->create();

        $response = $this->actingAs($patrol->ranger)
            ->getJson(route('ranger.patrols.tracks.index', $patrol));

        $response->assertOk()
            ->assertJsonPath('patrol_id', $patrol->id)
            ->assertJsonCount(3, 'tracks');
    }
}
