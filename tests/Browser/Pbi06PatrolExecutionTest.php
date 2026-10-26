<?php

namespace Tests\Browser;

use App\Enums\PatrolStatus;
use App\Models\Checkpoint;
use App\Models\Patrol;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi06PatrolExecutionTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_ranger_starts_reloads_and_confirms_finish_with_unvisited_checkpoints(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->create();
        $checkpoint = Checkpoint::factory()->for($patrol->forestArea)->create();
        $patrol->patrolCheckpoints()->create(['client_uuid' => (string) Str::uuid(), 'checkpoint_id' => $checkpoint->id, 'sequence' => 1]);

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)->resize(360, 800)->visitRoute('ranger.patrols.show', $patrol)
                ->scrollIntoView('[data-patrol-mutation] button')->press('Mulai patroli')->waitFor('[data-finish-open]')
                ->assertSeeIn('strong', 'Berlangsung')->assertPresent('[data-patrol-duration]');
            $started = $patrol->fresh()->getRawOriginal('started_at');
            $browser->refresh()->waitFor('[data-finish-open]')->assertSeeIn('strong', 'Berlangsung');
            $this->assertSame($started, $patrol->fresh()->getRawOriginal('started_at'));
            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0]);
            $browser->scrollIntoView('[data-finish-open]')->press('Akhiri patroli')->waitFor('dialog[open]')
                ->assertSeeIn('dialog', 'Masih ada 1 checkpoint belum dikunjungi.')
                ->keys('[data-finish-cancel]', '{ESCAPE}')->waitUntilMissing('dialog[open]');
            $this->assertSame(PatrolStatus::InProgress, $patrol->fresh()->status);
            $browser->press('Akhiri patroli')->waitFor('dialog[open]')->press('Ya, akhiri patroli')
                ->waitUntilMissing('[data-finish-open]')->assertSeeIn('strong', 'Selesai')
                ->assertSee('Belum dikunjungi')->assertMissing('[data-patrol-mutation]')
                ->refresh()->assertSeeIn('strong', 'Selesai');
            $this->assertSame($started, $patrol->fresh()->getRawOriginal('started_at'));
        });

        $this->assertSame(PatrolStatus::Completed, $patrol->fresh()->status);
        $this->assertNotNull($patrol->fresh()->ended_at);
        $this->assertNull($patrol->patrolCheckpoints->sole()->visited_at);
    }

    public function test_other_assignment_links_to_current_active_patrol(): void
    {
        $this->seed(RoleSeeder::class);
        $active = Patrol::factory()->inProgress()->create();
        $other = Patrol::factory()->for($active->ranger, 'ranger')->create();

        $this->browse(function (Browser $browser) use ($active, $other): void {
            $browser->loginAs($active->ranger)->resize(1280, 900)->visitRoute('ranger.patrols.show', $other)
                ->assertSee('Selesaikan patroli yang sedang berlangsung sebelum memulai tugas lain.')
                ->assertMissing('[data-patrol-mutation]')->clickLink('Lanjutkan '.$active->code)
                ->waitFor('[data-finish-open]')->assertSee($active->code)->assertSeeIn('[data-patrol-duration]', '01:00:');
        });

        $this->assertSame(PatrolStatus::Assigned, $other->fresh()->status);
    }

    public function test_lost_server_confirmation_does_not_claim_success_and_retry_reads_latest_state(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->create();

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)->visitRoute('ranger.patrols.show', $patrol);
            $browser->script(<<<'JS'
window.patrolFetch = window.fetch.bind(window);
sessionStorage.setItem('patrolRequests', '[]');
window.fetch = async (url, options = {}) => {
    const calls = JSON.parse(sessionStorage.getItem('patrolRequests'));
    calls.push(options.method || 'GET');
    sessionStorage.setItem('patrolRequests', JSON.stringify(calls));
    const response = await window.patrolFetch(url, options);
    if (options.method === 'POST') throw new TypeError('Simulated lost confirmation');
    return response;
};
JS);
            $browser->scrollIntoView('[data-patrol-mutation] button')->press('Mulai patroli')
                ->waitForText('Perubahan belum dapat dikonfirmasi.')
                ->assertSeeIn('strong', 'Ditugaskan')->assertMissing('[data-finish-open]');
            $started = $patrol->fresh()->getRawOriginal('started_at');
            $this->assertSame(PatrolStatus::InProgress, $patrol->fresh()->status);
            $browser->press('Mulai patroli')->waitFor('[data-finish-open]')->assertSeeIn('strong', 'Berlangsung');
            $this->assertSame(['GET', 'POST', 'GET'], $browser->script('return JSON.parse(sessionStorage.getItem("patrolRequests"))')[0]);
            $this->assertSame($started, $patrol->fresh()->getRawOriginal('started_at'));
            $browser->script('window.dispatchEvent(new Event("offline"))');
            $browser->assertDisabled('[data-finish-open]')->assertSee('Perubahan belum dapat dikonfirmasi.');
            $browser->script('window.dispatchEvent(new Event("online"))');
            $browser->waitUntil('document.querySelector("[data-finish-open]") && !document.querySelector("[data-finish-open]").disabled');
        });
    }
}
