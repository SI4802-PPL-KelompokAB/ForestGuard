<?php

namespace Tests\Feature;

use App\Enums\PatrolStatus;
use App\Models\Patrol;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Pbi06PatrolConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_starts_on_separate_connections_allow_exactly_one_active_patrol(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('This row-lock integration test requires MySQL/MariaDB.');
        }
        $this->seed(RoleSeeder::class);
        $ranger = User::factory()->ranger()->create();
        $first = Patrol::factory()->for($ranger, 'ranger')->create();
        $second = Patrol::factory()->for($ranger, 'ranger')->create();
        $workers = [$this->worker($ranger, $first), $this->worker($ranger, $second)];

        try {
            DB::beginTransaction();
            User::whereKey($ranger->id)->lockForUpdate()->firstOrFail();
            try {
                foreach ($workers as $worker) {
                    $worker->start();
                    $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $worker->getErrorOutput());
                }
            } finally {
                DB::rollBack();
            }
            $exitCodes = array_map(fn ($worker) => $worker->wait(), $workers);
            sort($exitCodes);
            $this->assertSame([0, 9], $exitCodes, implode("\n", array_map(fn ($worker) => $worker->getErrorOutput(), $workers)));
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }

        $this->assertSame(1, $ranger->patrols()->where('status', PatrolStatus::InProgress)->count());
        $this->assertSame(1, $ranger->patrols()->where('status', PatrolStatus::Assigned)->whereNull('started_at')->count());
        $this->assertSame(0, $ranger->patrols()->whereNotNull('ended_at')->count());
    }

    private function worker(User $ranger, Patrol $patrol): Process
    {
        $script = <<<'PHP'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(["database.default" => "mysql", "database.connections.mysql" => json_decode(getenv("FG_TEST_DATABASE"), true)]);
Illuminate\Support\Facades\DB::purge("mysql");
$ranger = App\Models\User::findOrFail((int) $argv[1]);
echo "ready\n";
fflush(STDOUT);
try {
    app(App\Actions\Ranger\TransitionPatrol::class)->handle($ranger, (int) $argv[2], App\Enums\PatrolStatus::InProgress);
    exit(0);
} catch (App\Exceptions\PatrolConflict $exception) {
    exit(9);
}
PHP;

        return new Process([PHP_BINARY, '-r', $script, (string) $ranger->id, (string) $patrol->id], base_path(), [
            'APP_ENV' => 'testing',
            'FG_TEST_DATABASE' => json_encode(config('database.connections.'.config('database.default')), JSON_THROW_ON_ERROR),
        ], timeout: 30);
    }
}
