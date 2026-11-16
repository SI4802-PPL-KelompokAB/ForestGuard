<?php

namespace Tests\Feature;

use App\Enums\ObservationCategory;
use App\Models\Checkpoint;
use App\Models\Evidence;
use App\Models\Observation;
use App\Models\Patrol;
use App\Models\PatrolCheckpoint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class Pbi08FieldRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_visit_updates_the_assignment_with_actual_coordinates_and_one_track_without_enforcing_sequence(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 03:00:00', 'UTC'));
        $patrol = Patrol::factory()->inProgress()->create();
        $first = $this->plannedCheckpoint($patrol, 1);
        $second = $this->plannedCheckpoint($patrol, 2);
        $masterCoordinates = $second->checkpoint->only(['latitude', 'longitude']);
        $uuid = $second->client_uuid;
        $url = route('ranger.patrols.checkpoints.visit', [$patrol, $second]);

        $this->actingAs($patrol->ranger)->postJson($url, [
            'latitude' => -7.1234567, 'longitude' => 110.1234567,
            'visited_at' => '2000-01-01', 'checkpoint_id' => $first->checkpoint_id,
        ])->assertOk()->assertJsonPath('checkpoint.id', $second->id)->assertJsonPath('unvisited_count', 1);
        $this->assertDatabaseHas('patrol_checkpoints', [
            'id' => $second->id, 'client_uuid' => $uuid, 'visited_at' => '2026-11-16 03:00:00',
            'latitude' => '-7.1234567', 'longitude' => '110.1234567',
        ]);
        $this->assertDatabaseCount('patrol_checkpoints', 2);
        $this->assertDatabaseHas('patrol_tracks', [
            'patrol_id' => $patrol->id, 'latitude' => '-7.1234567',
            'longitude' => '110.1234567', 'recorded_at' => '2026-11-16 03:00:00',
        ]);
        $this->assertSame($masterCoordinates, $second->checkpoint->fresh()->only(['latitude', 'longitude']));

        $this->travel(10)->minutes();
        $this->postJson($url, ['latitude' => -1, 'longitude' => 1])->assertOk();
        $this->assertDatabaseHas('patrol_checkpoints', [
            'id' => $second->id, 'visited_at' => '2026-11-16 03:00:00', 'latitude' => '-7.1234567',
        ]);
        $this->assertDatabaseCount('patrol_tracks', 1);
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSee('1/2 dikunjungi')
            ->assertSee('10:00:00')->assertSee('Checkpoint berikutnya')->assertSee('-7.1234567');
        $this->assertNull($first->fresh()->visited_at);
    }

    public function test_valid_observation_without_photo_is_trimmed_deduplicated_and_visible_after_finish(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 03:00:00', 'UTC'));
        $patrol = Patrol::factory()->inProgress()->create();
        $this->plannedCheckpoint($patrol, 1);
        $data = $this->observationData() + ['observed_at' => '1990-01-01', 'patrol_id' => 999999];
        $data['description'] = '  Pohon tumbang di jalur  ';
        $url = route('ranger.patrols.observations.store', $patrol);

        $this->actingAs($patrol->ranger)->postJson($url, $data)->assertCreated()
            ->assertJsonPath('observation.patrol_id', $patrol->id);
        $this->postJson($url, $data)->assertCreated();
        $this->assertDatabaseCount('observations', 1);
        $this->assertDatabaseCount('evidences', 0);
        $this->assertDatabaseHas('observations', [
            'patrol_id' => $patrol->id, 'description' => 'Pohon tumbang di jalur',
            'observed_at' => '2026-11-16 03:00:00', 'location_recorded_at' => '2026-11-16 02:59:00',
        ]);
        $this->postJson(route('ranger.patrols.finish', $patrol))->assertOk();
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSee('Pohon tumbang di jalur')
            ->assertSee('09:59:00')->assertSee('10:00:00')->assertSee('Belum dikunjungi')
            ->assertDontSee('data-observation-form', false)->assertDontSee('data-checkpoint-form', false);
        $this->actingAs($patrol->creator)->get(route('admin.patrols.show', $patrol))->assertOk()->assertSee('Pohon tumbang di jalur');
    }

    #[DataProvider('invalidObservations')]
    public function test_invalid_observation_returns_422_and_does_not_write(string $field, mixed $value): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 03:00:00', 'UTC'));
        $patrol = Patrol::factory()->inProgress()->create();
        $data = array_replace($this->observationData(), [$field => $value]);

        $response = $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.observations.store', $patrol), $data);
        $response->assertUnprocessable()->assertJsonValidationErrors($field);
        if ($field === 'description' && is_string($value) && trim($value) !== '') {
            $response->assertJsonPath('errors.description.0', 'Catatan observasi minimal 5 karakter setelah trim.');
        }
        $this->assertDatabaseCount('observations', 0);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidObservations(): array
    {
        return [
            'short trimmed note' => ['description', '   abcd   '],
            'blank note' => ['description', '   '],
            'category whitelist' => ['category', 'Illegal logging'],
            'missing latitude' => ['latitude', null],
            'latitude lower bound' => ['latitude', -90.01],
            'latitude upper bound' => ['latitude', 90.01],
            'longitude lower bound' => ['longitude', -180.01],
            'longitude upper bound' => ['longitude', 180.01],
            'nonnumeric coordinate' => ['longitude', 'unknown'],
            'missing GPS time' => ['location_recorded_at', null],
            'invalid GPS time' => ['location_recorded_at', 'invalid'],
            'future GPS time' => ['location_recorded_at', '2099-01-01'],
            'invalid UUID' => ['client_uuid', 'bad-uuid'],
        ];
    }

    public function test_minimum_five_characters_and_coordinate_boundaries_are_accepted(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $data = array_replace($this->observationData(), ['description' => '  abcde  ', 'latitude' => -90, 'longitude' => 180]);
        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.observations.store', $patrol), $data)->assertCreated();
        $this->assertDatabaseHas('observations', ['description' => 'abcde', 'latitude' => -90, 'longitude' => 180]);
    }

    #[DataProvider('inaccessibleRoles')]
    public function test_other_roles_and_rangers_cannot_record(string $role): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $planned = $this->plannedCheckpoint($patrol, 1);
        $user = User::factory()->$role()->create();
        $expected = $role === 'ranger' ? 404 : 403;
        $this->actingAs($user)->postJson(route('ranger.patrols.checkpoints.visit', [$patrol, $planned]), [
            'latitude' => 0, 'longitude' => 0,
        ])->assertStatus($expected);
        $this->postJson(route('ranger.patrols.observations.store', $patrol), $this->observationData())->assertStatus($expected);
        $this->assertNull($planned->fresh()->visited_at);
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('patrol_tracks', 0);
    }

    /** @return array<string, array{string}> */
    public static function inaccessibleRoles(): array
    {
        return ['admin' => ['admin'], 'citizen' => ['citizen'], 'another ranger' => ['ranger']];
    }

    public function test_guest_and_inactive_ranger_cannot_record(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $planned = $this->plannedCheckpoint($patrol, 1);
        $this->postJson(route('ranger.patrols.checkpoints.visit', [$patrol, $planned]), ['latitude' => 0, 'longitude' => 0])->assertUnauthorized();
        $this->postJson(route('ranger.patrols.observations.store', $patrol), $this->observationData())->assertUnauthorized();
        $patrol->ranger->update(['is_active' => false]);
        $this->actingAs($patrol->ranger->fresh())->postJson(route('ranger.patrols.observations.store', $patrol), $this->observationData())->assertRedirect(route('login'));
        $this->actingAs($patrol->ranger->fresh())->postJson(route('ranger.patrols.checkpoints.visit', [$patrol, $planned]), ['latitude' => 0, 'longitude' => 0])->assertRedirect(route('login'));
        $this->assertDatabaseCount('observations', 0);
        $this->assertNull($planned->fresh()->visited_at);
    }

    public function test_checkpoint_must_belong_to_the_open_patrol_and_coordinates_are_required(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $planned = $this->plannedCheckpoint(Patrol::factory()->create(), 1);
        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.checkpoints.visit', [$patrol, $planned]), [
            'latitude' => 0, 'longitude' => 0,
        ])->assertNotFound();
        $own = $this->plannedCheckpoint($patrol, 1);
        $this->postJson(route('ranger.patrols.checkpoints.visit', [$patrol, $own]), [])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->postJson(route('ranger.patrols.checkpoints.visit', [$patrol, $own]), ['latitude' => 91, 'longitude' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->assertNull($own->fresh()->visited_at);
        $this->assertNull($planned->fresh()->visited_at);
        $this->assertDatabaseCount('patrol_tracks', 0);
    }

    #[DataProvider('closedStatuses')]
    public function test_assigned_and_completed_patrols_reject_both_record_types(string $state): void
    {
        $patrol = $state === 'completed' ? Patrol::factory()->completed()->create() : Patrol::factory()->create();
        $planned = $this->plannedCheckpoint($patrol, 1);
        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.checkpoints.visit', [$patrol, $planned]), ['latitude' => 0, 'longitude' => 0])->assertConflict();
        $this->postJson(route('ranger.patrols.observations.store', $patrol), $this->observationData())->assertConflict();
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('patrol_tracks', 0);
        $this->assertNull($planned->fresh()->visited_at);
    }

    /** @return array<string, array{string}> */
    public static function closedStatuses(): array
    {
        return ['assigned' => ['assigned'], 'completed' => ['completed']];
    }

    public function test_optional_photo_uses_private_evidence_pipeline_and_does_not_duplicate_on_retry(): void
    {
        Storage::fake('private');
        config(['filesystems.evidence_disk' => 'private']);
        $patrol = Patrol::factory()->inProgress()->create();
        $data = $this->observationData() + ['photo' => UploadedFile::fake()->image('forest.jpg', 20, 20)];
        $url = route('ranger.patrols.observations.store', $patrol);
        $this->actingAs($patrol->ranger)->postJson($url, $data)->assertCreated();
        $this->postJson($url, $data)->assertCreated();
        $evidence = Evidence::sole();
        $this->assertSame(Observation::sole()->id, $evidence->observation_id);
        $this->assertNull($evidence->incident_id);
        $this->assertNull($evidence->update_id);
        $this->assertSame($patrol->ranger_id, $evidence->uploaded_by);
        Storage::disk('private')->assertExists($evidence->file_path);
        $this->assertCount(1, Storage::disk('private')->allFiles());
        $this->get(route('ranger.patrols.show', $patrol))->assertOk()->assertSee(route('evidence.show', $evidence));
        $this->get(route('evidence.show', $evidence))->assertRedirect();
        $this->actingAs(User::factory()->ranger()->create())->get(route('evidence.show', $evidence))->assertForbidden();
    }

    public function test_invalid_and_oversized_photos_reject_the_entire_observation(): void
    {
        Storage::fake('private');
        config(['filesystems.evidence_disk' => 'private']);
        $patrol = Patrol::factory()->inProgress()->create();
        foreach ([
            UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->image('large.png', 10, 10)->size(5121),
        ] as $photo) {
            $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.observations.store', $patrol), $this->observationData() + ['photo' => $photo])
                ->assertUnprocessable()->assertJsonValidationErrors('photo');
        }
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('evidences', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_storage_failure_rolls_back_observation_and_evidence(): void
    {
        Storage::fake('private');
        config(['filesystems.evidence_disk' => 'private']);
        $disk = Storage::disk('private');
        $failingDisk = \Mockery::mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('put')->once()->andReturnFalse();
        $failingDisk->shouldReceive('delete')->once()->andReturnTrue();
        Storage::shouldReceive('disk')->with('private')->andReturn($failingDisk);
        $patrol = Patrol::factory()->inProgress()->create();

        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.observations.store', $patrol), $this->observationData() + ['photo' => UploadedFile::fake()->image('photo.png')])->assertServerError();
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('evidences', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_database_failure_after_upload_removes_the_file_and_rolls_back(): void
    {
        Storage::fake('private');
        config(['filesystems.evidence_disk' => 'private']);
        $patrol = Patrol::factory()->inProgress()->create();
        Event::listen(QueryExecuted::class, function (QueryExecuted $event): void {
            if (str_starts_with($event->sql, 'select') && str_contains($event->sql, 'evidences')) {
                throw new RuntimeException('Simulated database failure after upload');
            }
        });
        try {
            $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.observations.store', $patrol), $this->observationData() + ['photo' => UploadedFile::fake()->image('photo.png')])->assertServerError();
        } finally {
            Event::forget(QueryExecuted::class);
        }
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('evidences', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_observation_context_prefills_existing_incident_form_without_creating_a_report_and_rejects_another_owner(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $data = $this->observationData();
        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.observations.store', $patrol), $data)->assertCreated();
        $observation = Observation::sole();
        $url = route('ranger.incidents.create', ['observation' => $observation->id]);
        $this->get($url)->assertOk()->assertSee($data['description'])->assertSee($data['latitude'])
            ->assertSee('Periksa rincian')->assertSee($patrol->code);
        $this->assertDatabaseCount('incidents', 0);
        $this->actingAs(User::factory()->ranger()->create())->get($url)->assertNotFound();
    }

    public function test_observation_uuid_cannot_be_reused_for_another_patrol_and_notes_are_escaped(): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $data = array_replace($this->observationData(), ['description' => '<script>alert(1)</script>']);
        $this->actingAs($patrol->ranger)->postJson(route('ranger.patrols.observations.store', $patrol), $data)->assertCreated();
        $other = Patrol::factory()->inProgress()->create();
        $this->actingAs($other->ranger)->postJson(route('ranger.patrols.observations.store', $other), $data)->assertConflict();
        $this->assertDatabaseCount('observations', 1);
        $this->actingAs($patrol->ranger)->get(route('ranger.patrols.show', $patrol))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
    }

    private function plannedCheckpoint(Patrol $patrol, int $sequence): PatrolCheckpoint
    {
        return $patrol->patrolCheckpoints()->create([
            'client_uuid' => (string) Str::uuid(),
            'checkpoint_id' => Checkpoint::factory()->for($patrol->forestArea)->create()->id,
            'sequence' => $sequence,
        ]);
    }

    /** @return array{client_uuid: string, category: string, description: string, latitude: string, longitude: string, location_recorded_at: string} */
    private function observationData(): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'category' => ObservationCategory::ForestCondition->value,
            'description' => 'Pohon tumbang di jalur',
            'latitude' => '-6.1234567',
            'longitude' => '107.1234567',
            'location_recorded_at' => '2026-11-16T02:59:00Z',
        ];
    }
}
