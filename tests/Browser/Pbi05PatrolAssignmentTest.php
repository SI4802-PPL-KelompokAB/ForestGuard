<?php

namespace Tests\Browser;

use App\Models\Checkpoint;
use App\Models\ForestArea;
use App\Models\Patrol;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\DuskTestCase;
use Illuminate\Support\Facades\Artisan;

class Pbi05PatrolAssignmentTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh');
    }

    /**
     * Test case Jira: TC-PBI05-03a.
     * TC-PBI05-03a: Semua field wajib dan array checkpoint kosong ditolak; input web valid dipertahankan.
     */
    #[TestDox('TC-PBI05-03a: required inputs prevent submission and corrected input creates one assignment')]
    public function test_empty_assignment_fields_prevent_submission_until_completed(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $ranger, $checkpoint): void {
            $browser->loginAs($admin)->visitRoute('admin.patrols.index')
                ->assertDisabled('[data-assign-submit]')
                ->select('ranger_id', $ranger->id)->assertDisabled('[data-assign-submit]')
                ->select('forest_area_id', $checkpoint->forest_area_id)
                ->waitFor('[data-checkpoints] input')->assertDisabled('[data-assign-submit]')
                ->check('[data-checkpoints] input')->scrollIntoView('[data-assign-submit]')
                ->press('Tugaskan patroli');
            $this->assertTrue($browser->script('return document.querySelector("#scheduled_at").validity.valueMissing')[0]);
            $this->assertDatabaseCount('patrols', 0);
            $browser->script('document.querySelector("#scheduled_at").value = "2026-11-18T08:30"');
            $browser->uncheck('[data-checkpoints] input')->assertDisabled('[data-assign-submit]')
                ->check('[data-checkpoints] input')->press('Tugaskan patroli')
                ->waitForText('berhasil ditugaskan');
        });

        $this->assertDatabaseCount('patrols', 1);
        $this->assertDatabaseCount('patrol_checkpoints', 1);
    }

    /**
     * Test case Jira: TC-PBI05-05a.
     * TC-PBI05-05a: Ranger nonaktif, terhapus, tidak ada, Citizen, dan Admin ditolak sebagai petugas.
     */
    #[TestDox('TC-PBI05-05a: an unavailable ranger produces a visible error and can be replaced')]
    public function test_ranger_deactivated_after_form_opens_shows_error_and_allows_correction(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $replacement = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $ranger, $replacement, $checkpoint): void {
            $browser->loginAs($admin)->visitRoute('admin.patrols.index')
                ->select('ranger_id', $ranger->id)->select('forest_area_id', $checkpoint->forest_area_id)
                ->waitFor('[data-checkpoints] input')->check('[data-checkpoints] input');
            $browser->script('document.querySelector("#scheduled_at").value = "2026-11-18T08:30"');
            $ranger->update(['is_active' => false]);
            $browser->scrollIntoView('[data-assign-submit]')->press('Tugaskan patroli')
                ->waitFor('#ranger_id-error')->assertSeeIn('#ranger_id-error', 'Pilih Ranger aktif yang tersedia.')
                ->assertSelected('forest_area_id', $checkpoint->forest_area_id)
                ->assertInputValue('scheduled_at', '2026-11-18T08:30')->assertChecked('[data-checkpoints] input');
            $this->assertDatabaseCount('patrols', 0);
            $browser->select('ranger_id', $replacement->id)->scrollIntoView('[data-assign-submit]')
                ->press('Tugaskan patroli')->waitForText('berhasil ditugaskan');
        });

        $this->assertSame($replacement->id, Patrol::sole()->ranger_id);
    }

    /**
     * Test case Jira: TC-PBI05-04c.
     * TC-PBI05-04c: Checkpoint terhapus/tidak ada ditolak.
     */
    #[TestDox('TC-PBI05-04c: a removed checkpoint is rejected without saving a partial assignment')]
    public function test_checkpoint_removed_after_selection_shows_error_without_saving_assignment(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $ranger, $checkpoint): void {
            $browser->loginAs($admin)->visitRoute('admin.patrols.index')
                ->select('ranger_id', $ranger->id)->select('forest_area_id', $checkpoint->forest_area_id)
                ->waitFor('[data-checkpoints] input')->check('[data-checkpoints] input');
            $browser->script('document.querySelector("#scheduled_at").value = "2026-11-18T08:30"');
            $checkpoint->delete();
            $browser->scrollIntoView('[data-assign-submit]')->press('Tugaskan patroli')
                ->waitForText('Checkpoint yang dipilih tidak sesuai area hutan atau sudah tidak tersedia.')
                ->assertSelected('ranger_id', $ranger->id)->assertInputValue('scheduled_at', '2026-11-18T08:30');
        });

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
    }

    /**
     * Test case Jira: TC-PBI05-02a, TC-PBI05-08.
     * TC-PBI05-02a: Penugasan atomik, status awal, jadwal UTC, UUID dan urutan checkpoint benar.
     * TC-PBI05-08: Tugas hanya diserahkan ke Ranger tujuan dengan jadwal/checkpoint benar.
     */
    #[TestDox('TC-PBI05-02a, TC-PBI05-08: browser assignment reaches only its target ranger')]
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
                ->assertSee($patrol->code)->clickLink('Buka detail')->waitForText('Rencana Checkpoint')
                ->assertSee('Ditugaskan')->assertSee('27/10/2026 08:30')
                ->assertSeeIn('ol li:nth-child(1)', 'CP-FIRST')->assertSeeIn('ol li:nth-child(2)', 'CP-SECOND');
            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0]);
            $browser->loginAs($other)->visitRoute('ranger.patrols.index')->assertDontSee($patrol->code);
        });

        $this->assertDatabaseCount('patrols', 1);
        $this->assertDatabaseCount('patrol_checkpoints', 2);
    }

    /**
     * Test case Jira: TC-PBI05-01b, TC-PBI05-03a, TC-PBI05-10.
     * TC-PBI05-01b: Pergantian area menghapus pilihan lama; respons lama tidak menimpa area terbaru.
     * TC-PBI05-03a: Semua field wajib dan array checkpoint kosong ditolak; input web valid dipertahankan.
     * TC-PBI05-10: Loading, kegagalan fetch/pemulihan, keyboard, submit tertunda, dan layout 360/1280 px.
     */
    #[TestDox('TC-PBI05-01b, TC-PBI05-03a, TC-PBI05-10: area changes, field errors and mobile layout')]
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

    /**
     * Test case Jira: TC-PBI05-01b, TC-PBI05-10.
     * TC-PBI05-01b: Pergantian area menghapus pilihan lama; respons lama tidak menimpa area terbaru.
     * TC-PBI05-10: Loading, kegagalan fetch/pemulihan, keyboard, submit tertunda, dan layout 360/1280 px.
     */
    #[TestDox('TC-PBI05-01b, TC-PBI05-10: a late checkpoint response cannot overwrite the latest area')]
    public function test_loading_and_late_checkpoint_responses_keep_the_latest_area_selection(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $old = Checkpoint::factory()->create();
        $latest = Checkpoint::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $ranger, $old, $latest): void {
            $browser->loginAs($admin)->visitRoute('admin.patrols.index')->select('ranger_id', $ranger->id);
            $oldUrl = route('admin.patrols.checkpoints', $old->forest_area_id);
            $browser->script('window.delayedCheckpointUrl = '.json_encode($oldUrl, JSON_THROW_ON_ERROR).';');
            $browser->script(<<<'JS'
const originalCheckpointFetch = window.fetch.bind(window);
window.fetch = (url, options) => {
    if (url === window.delayedCheckpointUrl) {
        return new Promise(resolve => {
            window.releaseOldCheckpointResponse = async () => {
                const response = await originalCheckpointFetch(url, {headers: {Accept: 'application/json'}});
                const body = await response.text();
                resolve(new Response(body, {status: 200, headers: {'Content-Type': 'application/json'}}));
                window.oldCheckpointResponseReleased = true;
            };
        });
    }
    return originalCheckpointFetch(url, options);
};
JS);
            $browser->select('forest_area_id', $old->forest_area_id)->waitForText('Memuat checkpoint')
                ->assertDisabled('[data-assign-submit]')->assertMissing('[data-checkpoints] input')
                ->select('forest_area_id', $latest->forest_area_id)
                ->waitFor('[data-checkpoints] input[value="'.$latest->id.'"]')
                ->scrollIntoView('[data-checkpoints]')->check('input[value="'.$latest->id.'"]');
            $browser->script('window.releaseOldCheckpointResponse()');
            $browser->waitUntil('window.oldCheckpointResponseReleased === true')
                ->assertSelected('forest_area_id', $latest->forest_area_id)
                ->assertChecked('input[value="'.$latest->id.'"]')->assertMissing('input[value="'.$old->id.'"]')
                ->assertEnabled('[data-assign-submit]');
        });

        $this->assertDatabaseCount('patrols', 0);
    }

    /**
     * Test case Jira: TC-PBI05-10.
     * TC-PBI05-10: Loading, kegagalan fetch/pemulihan, keyboard, submit tertunda, dan layout 360/1280 px.
     */
    #[TestDox('TC-PBI05-10: checkpoint fetch failure clears stale choices and can recover')]
    public function test_checkpoint_fetch_failure_clears_stale_choices_and_allows_reselection(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $first = Checkpoint::factory()->create();
        $second = Checkpoint::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $ranger, $first, $second): void {
            $browser->loginAs($admin)->visitRoute('admin.patrols.index')->select('ranger_id', $ranger->id)
                ->select('forest_area_id', $first->forest_area_id)->waitFor('input[value="'.$first->id.'"]')
                ->scrollIntoView('[data-checkpoints]')->check('input[value="'.$first->id.'"]');
            $browser->script(<<<'JS'
window.originalCheckpointFetch = window.fetch.bind(window);
window.fetch = (url, options) => String(url).endsWith('/checkpoints')
    ? Promise.resolve(new Response('{}', {status: 503}))
    : window.originalCheckpointFetch(url, options);
JS);
            $browser->select('forest_area_id', $second->forest_area_id)->waitForText('Checkpoint tidak dapat dimuat.')
                ->assertMissing('[data-checkpoints] input')->assertDisabled('[data-assign-submit]');
            $browser->script('window.fetch = window.originalCheckpointFetch');
            $browser->select('forest_area_id', '')->select('forest_area_id', $second->forest_area_id)
                ->waitFor('input[value="'.$second->id.'"]')->assertNotChecked('input[value="'.$second->id.'"]');
        });

        $this->assertDatabaseCount('patrols', 0);
    }

    /**
     * Test case Jira: TC-PBI05-01c.
     * TC-PBI05-01c: Tanpa Ranger, area, atau checkpoint, empty state tampil dan submit tidak aktif.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('emptyMasters')]
    #[TestDox('TC-PBI05-01c: missing master data prevents browser submission ($missing)')]
    public function test_missing_master_data_shows_an_empty_state_and_disables_assignment(string $missing, string $message): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        if ($missing !== 'ranger') {
            User::factory()->ranger()->create();
        }
        $area = $missing === 'area' ? null : ForestArea::factory()->create();
        if ($missing === 'ranger') {
            Checkpoint::factory()->for($area)->create();
        }

        $this->browse(function (Browser $browser) use ($admin, $area, $missing, $message): void {
            $browser->loginAs($admin)->resize(360, 800)->visitRoute('admin.patrols.index');
            if ($missing === 'checkpoint') {
                $browser->select('forest_area_id', $area->id)->waitForText($message);
            }
            $browser->assertSee($message)->assertSee('Belum ada penugasan patroli.')
                ->assertDisabled('[data-assign-submit]');
        });

        $this->assertDatabaseCount('patrols', 0);
        $this->assertDatabaseCount('patrol_checkpoints', 0);
    }

    /**
     * Test case Jira: TC-PBI05-10.
     * TC-PBI05-10: Loading, kegagalan fetch/pemulihan, keyboard, submit tertunda, dan layout 360/1280 px.
     * Setiap dataset pada DataProvider menjalankan variasi skenario ini secara terpisah.
     */
    #[DataProvider('viewportWidths')]
    #[TestDox('TC-PBI05-10: keyboard input and pending submission work at width $width')]
    public function test_keyboard_selection_and_pending_submission_are_usable_at_each_viewport(int $width): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->admin()->create();
        $ranger = User::factory()->ranger()->create();
        $checkpoint = Checkpoint::factory()->create();

        $this->browse(function (Browser $browser) use ($admin, $ranger, $checkpoint, $width): void {
            $browser->loginAs($admin)->resize($width, 900)->visitRoute('admin.patrols.index')
                ->select('ranger_id', $ranger->id)->select('forest_area_id', $checkpoint->forest_area_id)
                ->waitFor('input[value="'.$checkpoint->id.'"]')->scrollIntoView('[data-checkpoints]')
                ->keys('input[value="'.$checkpoint->id.'"]', '{SPACE}')->assertChecked('input[value="'.$checkpoint->id.'"]');
            $browser->script(<<<'JS'
document.querySelector('#scheduled_at').value = '2026-10-28T08:30';
document.querySelector('[data-patrol-assignment]').addEventListener('submit', event => event.preventDefault());
JS);
            $browser->scrollIntoView('[data-assign-submit]')->keys('[data-assign-submit]', '{ENTER}')
                ->waitForText('Menyimpan penugasan')->assertDisabled('[data-assign-submit]');
            $this->assertTrue($browser->script('return document.documentElement.scrollWidth <= window.innerWidth')[0]);
            $this->assertTrue($browser->script('const box = document.querySelector("[data-assign-submit]").getBoundingClientRect(); return box.left >= 0 && box.right <= innerWidth && box.top >= 0 && box.bottom <= innerHeight')[0]);
        });

        $this->assertDatabaseCount('patrols', 0);
    }

    /** @return array<string, array{string, string}> */
    public static function emptyMasters(): array
    {
        return ['no ranger' => ['ranger', 'Belum ada Ranger aktif.'], 'no area' => ['area', 'Belum ada area hutan tersedia.'], 'no checkpoints' => ['checkpoint', 'Area ini belum memiliki checkpoint.']];
    }

    /** @return array<string, array{int}> */
    public static function viewportWidths(): array
    {
        return ['phone' => [360], 'desktop' => [1280]];
    }
}
