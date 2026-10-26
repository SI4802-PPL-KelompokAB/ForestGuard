<?php

namespace Tests\Browser;

use App\Models\Checkpoint;
use App\Models\ForestArea;
use App\Models\Patrol;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi05PatrolAssignmentTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_admin_assigns_patrol_and_only_target_ranger_sees_ordered_task(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $other = User::factory()->ranger()->create();
        $area = ForestArea::factory()->create();
        $checkpoint = Checkpoint::factory()->for($area)->create(['code' => 'CP-FIRST']);
        $second = Checkpoint::factory()->for($area)->create(['code' => 'CP-SECOND']);

        $this->browse(function (Browser $browser) use ($admin, $ranger, $other, $area, $checkpoint, $second): void {
            $browser->loginAs($admin)->resize(1280, 900)->visitRoute('admin.patrols.index')
                ->select('ranger_id', $ranger->id)->select('forest_area_id', $area->id)
                ->waitFor('input[value="'.$checkpoint->id.'"]')
                ->check('input[value="'.$second->id.'"]')->check('input[value="'.$checkpoint->id.'"]');
            $browser->script('document.querySelector("#scheduled_at").value = "2026-10-27T08:30"');
            $browser->press('Tugaskan patroli')->waitForText('berhasil ditugaskan');
            $patrol = Patrol::sole();
            $browser->loginAs($ranger)->resize(360, 800)->visitRoute('ranger.patrols.index')
                ->assertSee($patrol->code)->clickLink('Buka detail')->waitForText('Rencana checkpoint')
                ->assertSee('Ditugaskan')->assertSee('27/10/2026 08:30')
                ->assertSeeIn('ol li:nth-child(1)', 'CP-FIRST')->assertSeeIn('ol li:nth-child(2)', 'CP-SECOND');
            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0]);
            $browser->loginAs($other)->visitRoute('ranger.patrols.index')->assertDontSee($patrol->code);
        });

        $this->assertDatabaseCount('patrols', 1);
        $this->assertDatabaseCount('patrol_checkpoints', 2);
    }

    public function test_changing_area_discards_selection_and_server_validation_keeps_valid_input(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $first = Checkpoint::factory()->create();
        $second = Checkpoint::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $ranger, $first, $second): void {
            $browser->loginAs($admin)->resize(360, 800)->visitRoute('admin.patrols.index')
                ->select('ranger_id', $ranger->id)->select('forest_area_id', $first->forest_area_id)
                ->waitFor('input[value="'.$first->id.'"]')->scrollIntoView('input[value="'.$first->id.'"]')->check('input[value="'.$first->id.'"]')
                ->select('forest_area_id', $second->forest_area_id)->waitFor('input[value="'.$second->id.'"]')
                ->assertMissing('input[value="'.$first->id.'"]')->assertNotChecked('input[value="'.$second->id.'"]')
                ->assertDisabled('[data-assign-submit]')->scrollIntoView('input[value="'.$second->id.'"]')->check('input[value="'.$second->id.'"]');
            $browser->script('document.querySelector("#scheduled_at").removeAttribute("required")');
            $browser->scrollIntoView('[data-assign-submit]')->press('Tugaskan patroli')->waitFor('#scheduled_at-error')
                ->assertSelected('ranger_id', $ranger->id)->assertSelected('forest_area_id', $second->forest_area_id)
                ->assertChecked('input[value="'.$second->id.'"]');
            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0]);
        });

        $this->assertDatabaseCount('patrols', 0);
    }
}
