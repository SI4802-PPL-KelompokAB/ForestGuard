<?php

namespace Tests\Browser;

use App\Models\Checkpoint;
use App\Models\Patrol;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi07PatrolTrackTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * D-07-01: Admin dapat melihat visualisasi peta rute patroli beserta checkpoint dan jarak tempuh.
     */
    public function test_admin_can_view_patrol_route_map_with_checkpoints_and_distance(): void
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

        $this->browse(function (Browser $browser) use ($patrol): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.patrols.show', $patrol)
                ->waitForText('Peta Rute Patroli')
                ->assertSee('Peta Rute Patroli')
                ->assertSee($patrol->forestArea->name)
                ->assertSee('1.45 km')
                ->assertPresent('#patrol-map')
                ->waitFor('#patrol-map .leaflet-container', 15)
                ->assertPresent('.custom-cp-marker');
        });
    }

    /**
     * D-07-02: Patroli berstatus Berlangsung menampilkan indikator pelacakan GPS aktif bagi Ranger.
     */
    public function test_in_progress_patrol_shows_active_tracking_badge_and_gps_indicator_for_ranger(): void
    {
        $this->seed(RoleSeeder::class);

        $ranger = User::factory()->ranger()->create();
        $patrol = Patrol::factory()->inProgress()->for($ranger, 'ranger')->create();

        $this->browse(function (Browser $browser) use ($ranger, $patrol): void {
            $browser->loginAs($ranger)
                ->visitRoute('ranger.patrols.show', $patrol)
                ->waitForText('Peta Rute Patroli')
                ->assertSee('Peta Rute Patroli')
                ->assertSee('Tracking Aktif')
                ->assertPresent('[data-gps-indicator]')
                ->assertPresent('#patrol-map')
                ->waitFor('#patrol-map .leaflet-container', 15);
        });
    }
}
