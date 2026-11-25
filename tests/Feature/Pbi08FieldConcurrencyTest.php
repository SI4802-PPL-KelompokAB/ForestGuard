<?php

namespace Tests\Feature;

use App\Actions\Ranger\TransitionPatrol;
use App\Enums\PatrolStatus;
use App\Models\Checkpoint;
use App\Models\Patrol;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Pbi08FieldConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            $this->markTestSkipped('Requires a dedicated PostgreSQL/MySQL database with row locks.');
        }
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test case Jira FG-8: TC-PBI08-11a.
     * Dua request bersamaan untuk data yang sama menghasilkan tepat satu catatan checkpoint atau observasi.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('recordTypes')]
    public function test_concurrent_duplicate_requests_preserve_one_record(string $kind): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $checkpoint = $patrol->patrolCheckpoints()->create([
            'client_uuid' => (string) Str::uuid(), 'sequence' => 1,
            'checkpoint_id' => Checkpoint::factory()->for($patrol->forestArea)->create()->id,
        ]);
        $uuid = (string) Str::uuid();
        $workers = [
            $this->worker($patrol, $kind, $checkpoint->id, $uuid, 11),
            $this->worker($patrol, $kind, $checkpoint->id, $uuid, 22),
        ];
        $this->assertSame([0, 0], $this->runContendingWorkers($patrol->ranger, $workers));
        if ($kind === 'checkpoint') {
            $this->assertDatabaseCount('patrol_tracks', 1);
            $this->assertDatabaseCount('patrol_checkpoints', 1);
            $this->assertNotNull($checkpoint->fresh()->visited_at);
            $this->assertContains((float) $checkpoint->fresh()->latitude, [11.0, 22.0]);
            $this->assertSame($checkpoint->fresh()->latitude, $patrol->tracks()->sole()->latitude);
        } else {
            $this->assertDatabaseCount('observations', 1);
            $this->assertSame($uuid, $patrol->observations()->sole()->client_uuid);
        }
    }

    /**
     * Test case Jira FG-8: TC-PBI08-11b.
     * Request pencatatan yang menunggu lock ditolak jika transaksi penyelesaian patroli lebih dahulu commit.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('recordTypes')]
    public function test_waiting_record_request_is_rejected_when_finish_commits_first(string $kind): void
    {
        $patrol = Patrol::factory()->inProgress()->create();
        $checkpoint = $patrol->patrolCheckpoints()->create([
            'client_uuid' => (string) Str::uuid(), 'sequence' => 1,
            'checkpoint_id' => Checkpoint::factory()->for($patrol->forestArea)->create()->id,
        ]);
        $worker = $this->worker($patrol, $kind, $checkpoint->id, (string) Str::uuid(), 11);
        try {
            DB::beginTransaction();
            User::whereKey($patrol->ranger_id)->lockForUpdate()->firstOrFail();
            $worker->start();
            $this->assertTrue($worker->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready')), $worker->getErrorOutput());
            app(TransitionPatrol::class)->handle($patrol->ranger, $patrol->id, PatrolStatus::Completed);
            DB::commit();
            $this->assertSame(9, $worker->wait(), $worker->getErrorOutput());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        $this->assertSame(PatrolStatus::Completed, $patrol->fresh()->status);
        $this->assertNull($checkpoint->fresh()->visited_at);
        $this->assertDatabaseCount('observations', 0);
        $this->assertDatabaseCount('patrol_tracks', 0);
    }

    /** @return array<string, array{string}> */
    public static function recordTypes(): array
    {
        return ['checkpoint' => ['checkpoint'], 'observation' => ['observation']];
    }

    /** @param array<int, Process> $workers
     * @return array<int, int>
     */
    private function runContendingWorkers(User $ranger, array $workers): array
    {
        try {
            DB::beginTransaction();
            User::whereKey($ranger->id)->lockForUpdate()->firstOrFail();
            try {
                foreach ($workers as $worker) {
                    $worker->start();
                    $this->assertTrue($worker->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready')), $worker->getErrorOutput());
                }
            } finally {
                DB::rollBack();
            }

            return array_map(fn (Process $worker): int => $worker->wait(), $workers);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }

    private function worker(Patrol $patrol, string $kind, int $checkpointId, string $uuid, int $latitude): Process
    {
        $script = <<<'PHP'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$driver = getenv("FG_TEST_DRIVER");
config(["database.default" => $driver, "database.connections.".$driver => json_decode(getenv("FG_TEST_DATABASE"), true)]);
Illuminate\Support\Facades\DB::purge($driver);
$ranger = App\Models\User::findOrFail((int) $argv[1]);
echo "ready\n";
fflush(STDOUT);
try {
    if ($argv[3] === "checkpoint") {
        app(App\Actions\Ranger\RecordPatrolCheckpoint::class)->handle($ranger, (int) $argv[2], (int) $argv[4], ["latitude" => (int) $argv[6], "longitude" => 100]);
    } else {
        app(App\Actions\Ranger\StoreObservation::class)->handle($ranger, (int) $argv[2], [
            "client_uuid" => $argv[5], "category" => "Kondisi hutan", "description" => "Pohon tumbang",
            "latitude" => (int) $argv[6], "longitude" => 100, "location_recorded_at" => "2026-11-16T00:00:00Z",
        ]);
    }
    exit(0);
} catch (App\Exceptions\PatrolConflict $exception) {
    exit(9);
}
PHP;

        return new Process([PHP_BINARY, '-r', $script, (string) $patrol->ranger_id, (string) $patrol->id, $kind, (string) $checkpointId, $uuid, (string) $latitude], base_path(), [
            'APP_ENV' => 'testing',
            'FG_TEST_DRIVER' => config('database.default'),
            'FG_TEST_DATABASE' => json_encode(config('database.connections.'.config('database.default')), JSON_THROW_ON_ERROR),
        ], timeout: 30);
    }
}
