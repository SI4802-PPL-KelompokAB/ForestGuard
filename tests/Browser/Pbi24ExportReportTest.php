<?php

// PBI-24: Ekspor laporan PDF — pengujian alur browser Admin.

namespace Tests\Browser;

use App\Models\CaseStatus;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * PBI-24 Ekspor Laporan PDF - Laravel Dusk.
 *
 * Isi binary PDF divalidasi oleh Feature test. Dusk memvalidasi alur browser,
 * filter, link ekspor, otorisasi, dan tampilan halaman.
 */
class Pbi24ExportReportTest extends DuskTestCase
{
    private const PAUSE_MS = 1000;

    private const FIXTURE_CODES = [
        'DUSK-PBI24-01',
        'DUSK-PBI24-02',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFixtures();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();
        parent::tearDown();
    }

    /** Remove only records created by this Dusk test. */
    private function cleanFixtures(): void
    {
        Incident::query()->whereIn('code', self::FIXTURE_CODES)->delete();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@forestguard.test')->firstOrFail();
    }

    private function loginAsAdminUser(Browser $browser): Browser
    {
        return $browser->loginAs($this->admin());
    }

    private function createFixture(
        string $code,
        string $status,
        string $type,
        string $area,
        ?\Carbon\Carbon $occurredAt = null,
    ): Incident {
        return Incident::factory()
            ->for($this->admin(), 'reporter')
            ->for(IncidentType::query()->where('name', $type)->firstOrFail(), 'incidentType')
            ->for(ForestArea::query()->where('name', $area)->firstOrFail(), 'forestArea')
            ->for(CaseStatus::query()->where('name', $status)->firstOrFail(), 'caseStatus')
            ->state([
                'code' => $code,
                'occurred_at' => $occurredAt ?? now()->subDays(2),
            ])
            ->create();
    }

    private function exportHref(Browser $browser): string
    {
        return (string) $browser->attribute('a[href*="/admin/laporan/pdf"]', 'href');
    }

    /** TC-PBI24-01 */
    public function test_admin_can_open_report_index(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan')
                ->pause(self::PAUSE_MS)
                ->waitForText('Ekspor Laporan PDF')
                ->assertSee('Tanggal awal')
                ->assertSee('Tanggal akhir')
                ->assertSee('Terapkan Filter')
                ->assertSee('Ekspor PDF');
        });
    }

    /** TC-PBI24-02 */
    public function test_admin_can_filter_report_by_date_range(): void
    {
        $target = $this->createFixture('DUSK-PBI24-01', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai', now()->subDays(2));
        $other = $this->createFixture('DUSK-PBI24-02', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai', now()->subDays(20));

        $dateFrom = $target->occurred_at->format('Y-m-d');
        $dateTo = $target->occurred_at->format('Y-m-d');

        $this->browse(function (Browser $browser) use ($target, $other, $dateFrom, $dateTo): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan?date_from='.$dateFrom.'&date_to='.$dateTo)
                ->pause(self::PAUSE_MS)
                ->waitForText($target->code)
                ->assertSee($target->code)
                ->assertDontSee($other->code);
        });
    }

    /** TC-PBI24-03 */
    public function test_admin_can_filter_report_by_status(): void
    {
        $reported = $this->createFixture('DUSK-PBI24-01', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai');
        $verified = $this->createFixture('DUSK-PBI24-02', 'Verified', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai');

        $this->browse(function (Browser $browser) use ($reported, $verified): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan')
                ->pause(self::PAUSE_MS)
                ->select('status', 'Reported')
                ->press('Terapkan Filter')
                ->pause(self::PAUSE_MS)
                ->waitForText($reported->code)
                ->assertSee($reported->code)
                ->assertDontSee($verified->code);
        });
    }

    /** TC-PBI24-04 */
    public function test_admin_can_filter_report_by_incident_type(): void
    {
        $fire = $this->createFixture('DUSK-PBI24-01', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai');
        $logging = $this->createFixture('DUSK-PBI24-02', 'Reported', 'Dugaan illegal logging', 'Taman Nasional Gunung Ciremai');

        $this->browse(function (Browser $browser) use ($fire, $logging): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan')
                ->pause(self::PAUSE_MS)
                ->select('incident_type_id', (string) $fire->incident_type_id)
                ->press('Terapkan Filter')
                ->pause(self::PAUSE_MS)
                ->waitForText($fire->code)
                ->assertSee($fire->code)
                ->assertDontSee($logging->code);
        });
    }

    /** TC-PBI24-05 */
    public function test_admin_can_filter_report_by_forest_area(): void
    {
        $ciremai = $this->createFixture('DUSK-PBI24-01', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai');
        $djuanda = $this->createFixture('DUSK-PBI24-02', 'Reported', 'Kebakaran hutan', 'Taman Hutan Raya Ir. H. Djuanda');

        $this->browse(function (Browser $browser) use ($ciremai, $djuanda): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan')
                ->pause(self::PAUSE_MS)
                ->select('forest_area_id', (string) $ciremai->forest_area_id)
                ->press('Terapkan Filter')
                ->pause(self::PAUSE_MS)
                ->waitForText($ciremai->code)
                ->assertSee($ciremai->code)
                ->assertDontSee($djuanda->code);
        });
    }

    /** TC-PBI24-06 */
    public function test_admin_can_export_report_to_pdf(): void
    {
        $this->createFixture('DUSK-PBI24-01', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai');

        $this->browse(function (Browser $browser): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan?status=Reported')
                ->pause(self::PAUSE_MS)
                ->waitForText('Ekspor PDF');

            $href = $this->exportHref($browser);
            $this->assertStringContainsString('/admin/laporan/pdf', $href);
            $browser->clickLink('Ekspor PDF')->pause(self::PAUSE_MS);
        });
    }

    /** TC-PBI24-07 */
    public function test_exported_pdf_matches_selected_filters(): void
    {
        $target = $this->createFixture('DUSK-PBI24-01', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai');

        $this->browse(function (Browser $browser) use ($target): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan?status=Reported&incident_type_id='.$target->incident_type_id.'&forest_area_id='.$target->forest_area_id)
                ->pause(self::PAUSE_MS)
                ->waitForText($target->code);

            $href = $this->exportHref($browser);
            $this->assertStringContainsString('status=Reported', $href);
            $this->assertStringContainsString('incident_type_id='.$target->incident_type_id, $href);
            $this->assertStringContainsString('forest_area_id='.$target->forest_area_id, $href);
        });
    }

    /** TC-PBI24-08 */
    public function test_invalid_date_filter_shows_validation_error(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan')
                ->waitFor('form[action*="/admin/laporan"]');

            // Isi input date melalui JS agar stabil di Chrome, lalu submit dari
            // halaman laporan supaya redirect validasi memiliki referer yang benar.
            $browser->script(
                "document.querySelector('[name=date_from]').value='2026-11-20';"
                . "document.querySelector('[name=date_to]').value='2026-11-01';"
            );

            $browser->press('Terapkan Filter')
                ->pause(self::PAUSE_MS)
                ->waitForText('Tanggal akhir tidak boleh sebelum tanggal awal.')
                ->assertSee('Tanggal akhir tidak boleh sebelum tanggal awal.');
        });
    }

    /** TC-PBI24-09 */
    public function test_empty_report_result_is_displayed(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan?date_from=2099-01-01&date_to=2099-01-02')
                ->pause(self::PAUSE_MS)
                ->waitForText('Tidak ada laporan sesuai filter.')
                ->assertSee('Tidak ada laporan sesuai filter.');
        });
    }

    /** TC-PBI24-10 */
    public function test_guest_and_non_admin_users_cannot_export_report(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->logout()
                ->visit('/admin/laporan')
                ->pause(self::PAUSE_MS)
                ->assertPathIs('/login');

            $browser->loginAs(User::query()->where('email', 'ranger1@forestguard.test')->firstOrFail())
                ->visit('/admin/laporan')
                ->assertSee('403');

            $browser->loginAs(User::query()->where('email', 'citizen1@forestguard.test')->firstOrFail())
                ->visit('/admin/laporan')
                ->assertSee('403');
        });
    }

    /** TC-PBI24-11 */
    public function test_export_does_not_modify_report_data(): void
    {
        $incident = $this->createFixture('DUSK-PBI24-01', 'Reported', 'Kebakaran hutan', 'Taman Nasional Gunung Ciremai');
        $before = $incident->fresh()->toArray();

        $this->browse(function (Browser $browser) use ($incident): void {
            $this->loginAsAdminUser($browser)
                ->visit('/admin/laporan/pdf?status=Reported&incident_type_id='.$incident->incident_type_id)
                ->pause(500);
        });

        $this->assertSame($before, $incident->fresh()->toArray());
    }

    /** TC-PBI24-12 */
    public function test_report_page_is_responsive(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginAsAdminUser($browser)->visit('/admin/laporan');

            foreach ([[360, 800], [1280, 800]] as [$width, $height]) {
                $browser->resize($width, $height)
                    ->waitForText('Ekspor Laporan PDF')
                    ->assertSee('Terapkan Filter');

                $overflow = $browser->script(
                    'return document.documentElement.scrollWidth > document.documentElement.clientWidth;'
                )[0];
                $this->assertFalse($overflow, "Halaman laporan overflow pada lebar {$width}px");
            }
        });
    }
}
