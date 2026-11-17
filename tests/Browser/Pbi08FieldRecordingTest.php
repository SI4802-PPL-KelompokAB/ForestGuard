<?php

namespace Tests\Browser;

use App\Models\Checkpoint;
use App\Models\Patrol;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi08FieldRecordingTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_mobile_checkpoint_and_observation_flow_survives_reload(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();
        $checkpoint = $patrol->patrolCheckpoints()->create([
            'client_uuid' => (string) Str::uuid(), 'sequence' => 1,
            'checkpoint_id' => Checkpoint::factory()->for($patrol->forestArea)->create()->id,
        ]);
        $this->browse(function (Browser $browser) use ($patrol, $checkpoint): void {
            $browser->loginAs($patrol->ranger)->resize(360, 800);
            $this->visitPatrolWithoutTracking($browser, $patrol);
            $this->stubLocation($browser);
            $browser->scrollIntoView('[data-checkpoint-form] button')->click('[data-checkpoint-form] button')
                ->waitForText('Kunjungan checkpoint tersimpan.')
                ->assertSeeIn('[data-checkpoint-progress]', '1/1 dikunjungi')
                ->assertMissing('[data-checkpoint-form]');
            $browser->scrollIntoView('[data-observation-details] summary')->click('[data-observation-details] summary')
                ->type('description', 'Ada jejak satwa di jalur patroli')
                ->select('category', 'Temuan satwa')
                ->scrollIntoView('[data-observation-submit]')->press('Simpan observasi')
                ->waitForText('Observasi lapangan tersimpan.')
                ->assertSeeIn('[data-observations-list]', 'Ada jejak satwa di jalur patroli')
                ->assertSeeIn('[data-observations-list]', 'Temuan satwa')
                ->assertSeeIn('[data-observations-list]', '-7.1234567')
                ->refresh()->waitFor('[data-observation-id]')
                ->assertSeeIn('[data-checkpoint-progress]', '1/1 dikunjungi')
                ->assertSeeIn('[data-observations-list]', 'Ada jejak satwa di jalur patroli');
            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0]);
            $this->assertSame('-7.1234567', $checkpoint->fresh()->latitude);
            $this->assertSame(1, $patrol->observations()->count());
            $browser->scrollIntoView('[data-observations-list] a')->clickLink('Laporkan insiden dari temuan')
                ->waitFor('#incidentForm')->assertInputValue('description', 'Ada jejak satwa di jalur patroli')
                ->assertInputValue('latitude', '-7.1234567')
                ->assertInputValue('patrol_id', (string) $patrol->id);
        });
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_gps_and_server_failures_preserve_notes_and_never_claim_success(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();
        $checkpoint = $patrol->patrolCheckpoints()->create([
            'client_uuid' => (string) Str::uuid(), 'sequence' => 1,
            'checkpoint_id' => Checkpoint::factory()->for($patrol->forestArea)->create()->id,
        ]);
        $this->browse(function (Browser $browser) use ($patrol, $checkpoint): void {
            $browser->loginAs($patrol->ranger);
            $this->visitPatrolWithoutTracking($browser, $patrol);
            $browser->script('navigator.geolocation.getCurrentPosition = (success, failure) => failure({code: 1});');
            $browser->scrollIntoView('[data-checkpoint-form] button')->click('[data-checkpoint-form] button')
                ->waitForText('Akses GPS ditolak.')->assertMissing('[data-observation-id]');
            $this->assertNull($checkpoint->fresh()->visited_at);
            $this->assertDatabaseCount('patrol_tracks', 0);
            $browser->scrollIntoView('[data-observation-details] summary')->click('[data-observation-details] summary')
                ->type('description', 'Catatan tetap ada ketika server gagal');
            $this->stubLocation($browser);
            $browser->script(<<<'JS'
window.originalFieldFetch = window.fetch.bind(window);
window.fetch = (url, options = {}) => options.method === 'POST'
    ? Promise.resolve(new Response(JSON.stringify({message: 'Failed'}), {status: 500, headers: {'Content-Type': 'application/json'}}))
    : window.originalFieldFetch(url, options);
JS);
            $browser->scrollIntoView('[data-observation-submit]')->press('Simpan observasi')
                ->waitForText('Server gagal mengonfirmasi penyimpanan.')
                ->assertInputValue('description', 'Catatan tetap ada ketika server gagal')
                ->assertSeeIn('[data-observation-location]', 'Lokasi GPS: -7.1234567')
                ->assertMissing('[data-observation-id]');
            $this->assertDatabaseCount('observations', 0);
            $browser->script('window.fetch = window.originalFieldFetch;');
            $browser->scrollIntoView('[data-observation-submit]')->press('Simpan observasi')
                ->waitForText('Observasi lapangan tersimpan.')->assertPresent('[data-observation-id]');
        });
        $this->assertSame(1, $patrol->observations()->count());
    }

    public function test_lost_confirmation_and_double_submit_do_not_duplicate_observations(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();
        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger);
            $this->visitPatrolWithoutTracking($browser, $patrol);
            $this->stubLocation($browser);
            $browser->scrollIntoView('[data-observation-details] summary')->click('[data-observation-details] summary')
                ->type('description', 'Pohon besar tumbang menutup jalur');
            $browser->script(<<<'JS'
window.originalFieldFetch = window.fetch.bind(window);
window.fetch = async (url, options = {}) => {
    const response = await window.originalFieldFetch(url, options);
    if (options.method === 'POST' && url.includes('/observasi')) throw new TypeError('Lost confirmation');
    return response;
};
document.querySelector('[data-observation-form]').requestSubmit();
document.querySelector('[data-observation-form]').requestSubmit();
JS);
            $browser->waitForText('Penyimpanan belum dapat dikonfirmasi.')
                ->assertInputValue('description', 'Pohon besar tumbang menutup jalur')
                ->assertMissing('[data-observation-id]');
            $this->assertSame(1, $patrol->observations()->count());
            $browser->script('window.fetch = window.originalFieldFetch;');
            $browser->scrollIntoView('[data-observation-submit]')->click('[data-observation-submit]')
                ->waitForText('Observasi lapangan tersimpan.');
            $this->assertSame(1, $patrol->observations()->count());
        });
    }

    private function visitPatrolWithoutTracking(Browser $browser, Patrol $patrol): void
    {
        $script = $browser->driver->executeCustomCommand('/session/:sessionId/goog/cdp/execute', 'POST', [
            'cmd' => 'Page.addScriptToEvaluateOnNewDocument',
            'params' => ['source' => <<<'JS'
Object.keys(localStorage)
    .filter(key => key.startsWith('pending_patrol_tracks_'))
    .forEach(key => localStorage.removeItem(key));
navigator.geolocation.watchPosition = () => 0;
JS],
        ]);
        try {
            $browser->visitRoute('ranger.patrols.show', $patrol);
        } finally {
            $browser->driver->executeCustomCommand('/session/:sessionId/goog/cdp/execute', 'POST', [
                'cmd' => 'Page.removeScriptToEvaluateOnNewDocument',
                'params' => ['identifier' => $script['identifier']],
            ]);
        }
    }

    private function stubLocation(Browser $browser): void
    {
        $browser->script(<<<'JS'
navigator.geolocation.getCurrentPosition = success => success({
    coords: {latitude: -7.1234567, longitude: 110.1234567, accuracy: 5},
    timestamp: Date.now() - 1000,
});
JS);
    }
}
