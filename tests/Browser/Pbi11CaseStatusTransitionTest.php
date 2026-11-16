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

/**
 * PBI-11: Alur Status Kasus & Riwayat Timeline — Laravel Dusk
 */
class Pbi11CaseStatusTransitionTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            CaseStatusSeeder::class,
            IncidentTypeSeeder::class,
            RiskLevelSeeder::class,
            ForestAreaSeeder::class,
            UserSeeder::class,
        ]);
    }

    /**
     * Buat incident dengan status tertentu menggunakan seeded reporter.
     */
    private function makeIncidentWithStatus(string $statusName): Incident
    {
        $statusId = CaseStatus::where('name', $statusName)->value('id');
        $citizen = User::where('email', 'citizen1@forestguard.test')->firstOrFail();

        return Incident::factory()->create([
            'case_status_id' => $statusId,
            'reporter_id' => $citizen->id,
        ]);
    }

    /**
     * TC-PBI11-01: Admin melihat detail kasus dengan status badge dan tombol aksi.
     */
    public function test_admin_can_view_case_detail_with_status_and_tahapan_kasus(): void
    {
        $incident = $this->makeIncidentWithStatus('Reported');

        $this->browse(function (Browser $browser) use ($incident): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.incidents.show', $incident)
                ->waitForText('Informasi Kasus')
                ->assertSee('Informasi Kasus')
                ->assertSee('Status: Reported')
                ->assertSee('Mulai Verifikasi Kasus')
                ->assertSee('Lini Masa Riwayat Kasus');
        });
    }

    /**
     * TC-PBI11-02: Admin dapat transisi Reported → Under Verification.
     */
    public function test_admin_can_transition_reported_to_under_verification(): void
    {
        $incident = $this->makeIncidentWithStatus('Reported');

        $this->browse(function (Browser $browser) use ($incident): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.incidents.show', $incident)
                ->waitForText('Mulai Verifikasi Kasus')
                ->press('Mulai Verifikasi Kasus')
                ->waitForText('Status: Under Verification', 15)
                ->assertSee('Status: Under Verification');
        });

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'case_status_id' => CaseStatus::where('name', 'Under Verification')->value('id'),
        ]);
    }

    /**
     * TC-PBI11-03: Admin dapat verifikasi laporan dengan risk level (Under Verification → Verified).
     */
    public function test_admin_can_verify_incident_with_risk_level(): void
    {
        $incident = $this->makeIncidentWithStatus('Under Verification');
        $riskLevel = RiskLevel::where('name', 'Sedang')->firstOrFail();

        $this->browse(function (Browser $browser) use ($incident, $riskLevel): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.incidents.show', $incident)
                ->waitForText('Verifikasi Laporan')
                ->select('risk_level_id', (string) $riskLevel->id)
                ->press('Verifikasi Laporan')
                ->waitForText('Status: Verified', 15)
                ->assertSee('Status: Verified');
        });

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'case_status_id' => CaseStatus::where('name', 'Verified')->value('id'),
        ]);
    }

    /**
     * TC-PBI11-04: Admin dapat menolak laporan dengan alasan (Under Verification → Rejected).
     */
    public function test_admin_can_reject_incident_with_reason(): void
    {
        $incident = $this->makeIncidentWithStatus('Under Verification');

        $this->browse(function (Browser $browser) use ($incident): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.incidents.show', $incident)
                ->waitForText('Tolak Laporan Ini')
                ->type('reject_reason', 'Laporan tidak terbukti berdasarkan hasil pengecekan lapangan.')
                ->press('Tolak Laporan Ini')
                ->waitForText('Status: Rejected', 15)
                ->assertSee('Status: Rejected');
        });

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'case_status_id' => CaseStatus::where('name', 'Rejected')->value('id'),
        ]);
    }

    /**
     * TC-PBI11-05: Admin dapat menyelesaikan kasus Monitoring → Resolved dengan catatan.
     */
    public function test_admin_can_resolve_monitoring_case_with_resolution_note(): void
    {
        $incident = $this->makeIncidentWithStatus('Monitoring');

        $this->browse(function (Browser $browser) use ($incident): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.incidents.show', $incident)
                ->waitForText('Selesaikan dan Tutup Kasus')
                ->type('resolution_note', 'Ancaman telah tertangani dan wilayah kembali normal.')
                ->press('Selesaikan dan Tutup Kasus')
                ->waitForText('Status: Resolved', 15)
                ->assertSee('Status: Resolved');
        });

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'case_status_id' => CaseStatus::where('name', 'Resolved')->value('id'),
        ]);
    }

    /**
     * TC-PBI11-06: Lini Masa Riwayat Kasus muncul di halaman detail kasus admin.
     */
    public function test_timeline_section_is_visible_on_admin_case_detail(): void
    {
        $incident = $this->makeIncidentWithStatus('Reported');

        $this->browse(function (Browser $browser) use ($incident): void {
            $this->loginAsAdmin($browser)
                ->visitRoute('admin.incidents.show', $incident)
                ->waitForText('Lini Masa Riwayat Kasus')
                ->assertSee('Lini Masa Riwayat Kasus');
        });
    }

    /**
     * TC-PBI11-07: Citizen dapat melihat riwayat status kasus miliknya.
     */
    public function test_citizen_can_view_their_incident_status_on_show_page(): void
    {
        $citizen = User::where('email', 'citizen1@forestguard.test')->firstOrFail();
        $incident = Incident::factory()->create([
            'reporter_id' => $citizen->id,
            'case_status_id' => CaseStatus::where('name', 'Reported')->value('id'),
        ]);

        $this->browse(function (Browser $browser) use ($incident): void {
            $this->loginAsCitizen($browser)
                ->visitRoute('citizen.incidents.show', $incident)
                ->waitForText('Tahapan penanganan')
                ->assertSee('Tahapan penanganan')
                ->assertSee('Reported');
        });
    }
}
