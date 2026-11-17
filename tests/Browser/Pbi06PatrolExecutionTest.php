<?php

namespace Tests\Browser;

use App\Enums\PatrolStatus;
use App\Models\Checkpoint;
use App\Models\Patrol;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\DuskTestCase;

class Pbi06PatrolExecutionTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[TestDox('TC-PBI06-04a: a second active patrol rejects start from an already open page')]
    public function test_start_from_stale_page_rejects_another_active_patrol_and_recovers(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->create();

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)->visitRoute('ranger.patrols.show', $patrol);
            $active = Patrol::factory()->inProgress()->for($patrol->ranger, 'ranger')->create();
            $browser->scrollIntoView('[data-patrol-mutation] button')->press('Mulai patroli')
                ->waitForText('Selesaikan patroli yang sedang berlangsung sebelum memulai tugas lain.')
                ->assertSeeIn('strong', 'Ditugaskan')->assertVisible('[data-state-refresh]');
            $this->assertNull($patrol->fresh()->started_at);
            $this->assertSame(PatrolStatus::InProgress, $active->fresh()->status);
            $active->update(['status' => PatrolStatus::Completed, 'ended_at' => now('UTC')]);
            $browser->press('Mulai patroli')->waitFor('[data-finish-open]')->assertSeeIn('strong', 'Berlangsung');
        });

        $this->assertSame(PatrolStatus::InProgress, $patrol->fresh()->status);
    }

    #[TestDox('TC-PBI06-06b: stale finish confirmation preserves the completion saved elsewhere')]
    public function test_finish_from_stale_page_preserves_completion_saved_elsewhere(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create();

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)->visitRoute('ranger.patrols.show', $patrol)
                ->scrollIntoView('[data-finish-open]')->press('Akhiri patroli')->waitFor('dialog[open]');
            $patrol->update(['status' => PatrolStatus::Completed, 'ended_at' => now('UTC')->subSeconds(10)]);
            $ended = $patrol->fresh()->getRawOriginal('ended_at');
            $browser->press('Ya, akhiri patroli')->waitUntilMissing('[data-finish-open]')
                ->assertSeeIn('strong', 'Selesai')->assertMissing('[data-patrol-mutation]');
            $this->assertSame($ended, $patrol->fresh()->getRawOriginal('ended_at'));
        });
    }

    #[TestDox('TC-PBI06-02a, TC-PBI06-03, TC-PBI06-05a, TC-PBI06-05b, TC-PBI06-05c, TC-PBI06-11a: browser lifecycle and confirmation')]
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
                ->press('Batal')->waitUntilMissing('dialog[open]');
            $this->assertNull($patrol->fresh()->ended_at);
            $browser->press('Akhiri patroli')->waitFor('dialog[open]')
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

    #[TestDox('TC-PBI06-04a: browser links a blocked task to its active patrol')]
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

    #[TestDox('TC-PBI06-09b: a lost response is reconciled without a second start')]
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
    const actualUrl = typeof url === 'string' ? url : (url?.url || '');
    if (!actualUrl.includes('/mulai') && !actualUrl.match(/\/patroli\/\d+$/)) {
        return window.patrolFetch(url, options);
    }
    const calls = JSON.parse(sessionStorage.getItem('patrolRequests'));
    const method = (options?.method || (url instanceof Request ? url.method : 'GET')).toUpperCase();
    calls.push(method);
    sessionStorage.setItem('patrolRequests', JSON.stringify(calls));
    const response = await window.patrolFetch(url, options);
    if (method === 'POST') throw new TypeError('Simulated lost confirmation');
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

    #[TestDox('TC-PBI06-03: signing in again restores the existing patrol and elapsed duration')]
    public function test_logging_in_again_restores_the_existing_active_patrol(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->inProgress()->create(['started_at' => now('UTC')->subMinutes(5)]);
        $started = $patrol->refresh()->getRawOriginal('started_at');

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)->visitRoute('ranger.patrols.show', $patrol)->waitFor('[data-patrol-duration]');
            $before = (int) $browser->attribute('[data-patrol-duration]', 'data-seconds');
            $browser->logout()->visitRoute('login')->type('email', $patrol->ranger->email)->type('password', 'Password123!')
                ->press('Masuk')->waitForRoute('ranger.home')->visitRoute('ranger.patrols.show', $patrol)
                ->waitFor('[data-finish-open]')->assertSeeIn('strong', 'Berlangsung');
            $after = (int) $browser->attribute('[data-patrol-duration]', 'data-seconds');
            $this->assertGreaterThanOrEqual(300, $before);
            $this->assertGreaterThanOrEqual($before, $after);
        });

        $this->assertSame($started, $patrol->fresh()->getRawOriginal('started_at'));
        $this->assertNull($patrol->fresh()->ended_at);
        $this->assertDatabaseCount('patrols', 1);
    }

    #[DataProvider('connectionFailures')]
    #[TestDox('TC-PBI06-09a: a $failure before the mutation cannot claim success')]
    public function test_connection_failure_before_mutation_can_retry_after_reading_current_state(string $failure): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->create();

        $this->browse(function (Browser $browser) use ($patrol, $failure): void {
            $browser->loginAs($patrol->ranger)->visitRoute('ranger.patrols.show', $patrol);
            $browser->script('window.patrolFailureKind = '.json_encode($failure, JSON_THROW_ON_ERROR).';');
            $browser->script(<<<'JS'
const originalExecutionFetch = window.fetch.bind(window);
const patrolStatePath = new URL(document.querySelector('[data-patrol-execution]').dataset.stateUrl).pathname;
sessionStorage.setItem('executionCalls', '[]');
let failedOnce = false;
window.fetch = (url, options = {}) => {
    const pathname = new URL(typeof url === 'string' ? url : url.url, location.href).pathname;
    if (pathname !== patrolStatePath && pathname !== patrolStatePath + '/mulai') return originalExecutionFetch(url, options);
    const calls = JSON.parse(sessionStorage.getItem('executionCalls'));
    calls.push(options.method || 'GET');
    sessionStorage.setItem('executionCalls', JSON.stringify(calls));
    if (!failedOnce) {
        failedOnce = true;
        return Promise.reject(window.patrolFailureKind === 'timeout'
            ? new DOMException('Simulated timeout', 'TimeoutError')
            : new TypeError('Simulated network failure'));
    }
    return originalExecutionFetch(url, options);
};
JS);
            $browser->scrollIntoView('[data-patrol-mutation] button')->press('Mulai patroli')
                ->waitForText('Perubahan belum dapat dikonfirmasi.')
                ->assertSeeIn('strong', 'Ditugaskan')->assertMissing('[data-finish-open]')
                ->assertVisible('[data-state-refresh]');
            $this->assertSame(PatrolStatus::Assigned, $patrol->fresh()->status);
            $this->assertNull($patrol->fresh()->started_at);
            $this->assertSame(['GET'], $browser->script('return JSON.parse(sessionStorage.getItem("executionCalls"))')[0]);
            $browser->press('Mulai patroli')->waitFor('[data-finish-open]')->assertSeeIn('strong', 'Berlangsung');
            $this->assertSame(['GET', 'GET', 'POST'], $browser->script('return JSON.parse(sessionStorage.getItem("executionCalls"))')[0]);
        });

        $this->assertSame(PatrolStatus::InProgress, $patrol->fresh()->status);
        $this->assertDatabaseCount('patrols', 1);
    }

    #[TestDox('TC-PBI06-11c: pending server confirmation prevents duplicate browser mutations')]
    public function test_pending_confirmation_disables_controls_and_ignores_duplicate_submissions(): void
    {
        $this->seed(RoleSeeder::class);
        $patrol = Patrol::factory()->create();

        $this->browse(function (Browser $browser) use ($patrol): void {
            $browser->loginAs($patrol->ranger)->resize(1280, 900)->visitRoute('ranger.patrols.show', $patrol);
            $browser->script(<<<'JS'
const pendingOriginalFetch = window.fetch.bind(window);
const startPath = new URL(document.querySelector('[data-patrol-mutation]').action).pathname;
window.executionPosts = 0;
window.fetch = (url, options = {}) => {
    if (options.method === 'POST' && new URL(url, location.href).pathname === startPath) {
        window.executionPosts++;
        return new Promise(resolve => {
            window.releasePatrolConfirmation = async () => resolve(await pendingOriginalFetch(url, options));
        });
    }
    return pendingOriginalFetch(url, options);
};
JS);
            $browser->scrollIntoView('[data-patrol-mutation] button')->press('Mulai patroli')
                ->waitForText('Menunggu konfirmasi server')->assertDisabled('[data-patrol-mutation] button')
                ->assertSeeIn('strong', 'Ditugaskan');
            $browser->script('document.querySelector("[data-patrol-mutation]").requestSubmit()');
            $this->assertSame(1, $browser->script('return window.executionPosts')[0]);
            $this->assertSame(PatrolStatus::Assigned, $patrol->fresh()->status);
            $browser->script('window.releasePatrolConfirmation()');
            $browser->waitFor('[data-finish-open]')->assertSeeIn('strong', 'Berlangsung');
        });

        $this->assertDatabaseCount('patrols', 1);
        $this->assertSame(PatrolStatus::InProgress, $patrol->fresh()->status);
    }

    #[DataProvider('viewportWidths')]
    #[TestDox('TC-PBI06-11c: empty state and keyboard confirmation work at width $width')]
    public function test_empty_state_and_keyboard_confirmation_at_each_viewport(int $width): void
    {
        $this->seed(RoleSeeder::class);
        $ranger = User::factory()->ranger()->create();

        $this->browse(function (Browser $browser) use ($ranger, $width): void {
            $browser->loginAs($ranger)->resize($width, 900)->visitRoute('ranger.patrols.index')
                ->assertSee('Belum ada tugas patroli untuk Anda.');
            $patrol = Patrol::factory()->inProgress()->for($ranger, 'ranger')->create();
            $browser->visitRoute('ranger.patrols.show', $patrol)->scrollIntoView('[data-finish-open]')
                ->keys('[data-finish-open]', '{ENTER}')->waitFor('dialog[open]')
                ->keys('[data-finish-cancel]', '{ENTER}')->waitUntilMissing('dialog[open]');
            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0]);
            $this->assertTrue($browser->script('const box = document.querySelector("[data-finish-open]").getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth && box.top >= 0 && box.bottom <= innerHeight')[0]);
            $this->assertSame(PatrolStatus::InProgress, $patrol->fresh()->status);
            $this->assertNull($patrol->fresh()->ended_at);
        });
    }

    /** @return array<string, array{string}> */
    public static function connectionFailures(): array
    {
        return ['network failure' => ['network'], 'request timeout' => ['timeout']];
    }

    /** @return array<string, array{int}> */
    public static function viewportWidths(): array
    {
        return ['phone' => [360], 'desktop' => [1280]];
    }
}
