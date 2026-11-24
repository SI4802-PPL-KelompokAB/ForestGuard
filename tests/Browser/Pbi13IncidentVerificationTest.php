<?php

namespace Tests\Browser;

use App\Models\CaseStatus;
use App\Models\Incident;
use App\Models\RiskLevel;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RiskLevelSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi13IncidentVerificationTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class, CaseStatusSeeder::class, IncidentTypeSeeder::class,
            RiskLevelSeeder::class, ForestAreaSeeder::class, UserSeeder::class,
        ]);
    }

    public function test_admin_verifies_a_report_from_the_queue_and_the_result_persists(): void
    {
        $incident = $this->reportedIncident();
        $risk = RiskLevel::active()->firstOrFail();

        $this->browse(function (Browser $browser) use ($incident, $risk): void {
            $this->startVerification($browser, $incident)
                ->select('risk_level_id', (string) $risk->id)
                ->press('Verifikasi Laporan')
                ->waitForText('Status: Verified')
                ->assertSee('Risiko: '.$risk->name)
                ->assertMissing('@suspected-status')
                ->refresh()
                ->waitForText('Status: Verified')
                ->assertSee('Lini Masa Riwayat Kasus')
                ->visitRoute('admin.incidents.index', ['status' => 'needs-verification'])
                ->assertDontSee($incident->code);
        });
    }

    public function test_admin_rejects_a_report_and_citizen_can_read_the_persisted_reason(): void
    {
        $incident = $this->reportedIncident();
        $reason = 'Foto tidak menunjukkan kejadian yang dilaporkan.';

        $this->browse(function (Browser $browser) use ($incident, $reason): void {
            $this->startVerification($browser, $incident)
                ->type('reject_reason', $reason)
                ->press('Tolak Laporan Ini')
                ->waitForText('Status: Rejected')
                ->assertSee('Ditolak')
                ->assertMissing('@suspected-status')
                ->refresh()
                ->waitForText($reason)
                ->visitRoute('admin.incidents.index', ['status' => 'needs-verification'])
                ->assertDontSee($incident->code);

            $this->loginAsCitizen($browser)
                ->visitRoute('citizen.incidents.show', $incident)
                ->waitForText($reason)
                ->assertSee('Ditolak');
        });
    }

    private function reportedIncident(): Incident
    {
        return Incident::factory()->create([
            'reporter_id' => User::where('email', 'citizen1@forestguard.test')->sole()->id,
            'case_status_id' => CaseStatus::where('name', 'Reported')->sole()->id,
        ]);
    }

    private function startVerification(Browser $browser, Incident $incident): Browser
    {
        return $this->loginAsAdmin($browser)
            ->clickLink('Kasus')
            ->waitForRoute('admin.incidents.index')
            ->clickLink('Perlu verifikasi')
            ->waitForText($incident->code)
            ->click('a[href="'.route('admin.incidents.show', $incident).'"]')
            ->waitForText('Status: Reported')
            ->assertSeeIn('@suspected-status', 'Dugaan')
            ->press('Mulai Verifikasi Kasus')
            ->waitForText('Status: Under Verification')
            ->assertSeeIn('@suspected-status', 'Dugaan');
    }
}
