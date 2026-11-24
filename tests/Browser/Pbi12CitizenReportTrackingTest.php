<?php

namespace Tests\Browser;

use App\Models\CaseStatus;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\StatusHistory;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class Pbi12CitizenReportTrackingTest extends DuskTestCase
{
    protected User $citizenA;

    protected User $citizenB;

    protected Incident $activeIncident;

    protected Incident $rejectedIncident;

    protected Incident $otherCitizenIncident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizenA = User::query()
            ->where('email', 'citizen1@forestguard.test')
            ->firstOrFail();
        $this->citizenB = User::query()
            ->where('email', 'citizen2@forestguard.test')
            ->firstOrFail();

        $reportedStatus = CaseStatus::query()->where('name', 'Reported')->firstOrFail();
        $underVerificationStatus = CaseStatus::query()
            ->where('name', 'Under Verification')
            ->firstOrFail();
        $rejectedStatus = CaseStatus::query()->where('name', 'Rejected')->firstOrFail();
        $incidentType = IncidentType::query()->firstOrFail();
        $forestArea = ForestArea::query()->firstOrFail();

        $this->activeIncident = $this->createIncident(
            $this->citizenA,
            $incidentType,
            $forestArea,
            $underVerificationStatus,
            'active'
        );
        $this->recordStatus(
            $this->activeIncident,
            $reportedStatus,
            $underVerificationStatus,
            $this->citizenA
        );

        $this->rejectedIncident = $this->createIncident(
            $this->citizenA,
            $incidentType,
            $forestArea,
            $rejectedStatus,
            'rejected',
            'Lokasi laporan tidak berada di kawasan hutan.'
        );
        $this->recordStatus(
            $this->rejectedIncident,
            $underVerificationStatus,
            $rejectedStatus,
            $this->citizenA
        );

        $this->otherCitizenIncident = $this->createIncident(
            $this->citizenB,
            $incidentType,
            $forestArea,
            $reportedStatus,
            'citizen-b'
        );
    }

    public function test_tc_12_01_citizen_report_list_shows_only_their_own_reports(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsCitizen()
                ->visitRoute('citizen.home')
                ->assertSee('Riwayat laporan')
                ->assertSee($this->activeIncident->code)
                ->assertSee($this->rejectedIncident->code)
                ->assertDontSee($this->otherCitizenIncident->code);
        });
    }

    public function test_tc_12_02_citizen_sees_report_status_timeline_location_and_photo_area(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsCitizen()
                ->visitRoute('citizen.incidents.show', $this->activeIncident)
                ->assertSeeIn('section span.rounded-full', 'Under Verification')
                ->assertSee('Tahapan penanganan')
                ->assertSeeIn('ol', 'Reported')
                ->assertSeeIn('ol', 'Under Verification')
                ->assertSeeIn('dl', '-6.7574000, 107.6199200')
                ->assertSee('Foto bukti')
                ->assertSee('Belum ada foto bukti untuk laporan ini.');
        });
    }

    public function test_tc_12_03_rejected_report_shows_rejection_status_and_reason(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsCitizen()
                ->visitRoute('citizen.incidents.show', $this->rejectedIncident)
                ->assertSeeIn('section span.rounded-full', 'Rejected')
                ->assertSee('Laporan ditolak')
                ->assertSee('Lokasi laporan tidak berada di kawasan hutan.');
        });
    }

    public function test_tc_12_04_citizen_receives_forbidden_page_for_another_citizens_report(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAsCitizen()
                ->visitRoute('citizen.incidents.show', $this->otherCitizenIncident)
                ->assertSee('HTTP 403')
                ->assertSee('Akses ditolak')
                ->assertSee('Anda tidak memiliki izin untuk membuka halaman ini.');
        });
    }

    public function test_tc_12_05_guest_is_redirected_to_login_from_citizen_report_list(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->logout();
            $browser->driver->manage()->deleteAllCookies();

            $browser->visit('/citizen/laporan')
                ->waitForRoute('login')
                ->assertPathIs('/login')
                ->assertPresent('input#email');
        });
    }

    private function createIncident(
        User $reporter,
        IncidentType $incidentType,
        ForestArea $forestArea,
        CaseStatus $status,
        string $case,
        ?string $rejectionReason = null
    ): Incident {
        $incident = Incident::factory()->create([
            'reporter_id' => $reporter->id,
            'incident_type_id' => $incidentType->id,
            'forest_area_id' => $forestArea->id,
            'case_status_id' => $status->id,
            'description' => 'DUSK-PBI12 laporan '.$case,
            'latitude' => -6.7574,
            'longitude' => 107.61992,
            'reject_reason' => $rejectionReason,
        ]);

        return $incident;
    }

    private function recordStatus(
        Incident $incident,
        CaseStatus $fromStatus,
        CaseStatus $toStatus,
        User $changedBy
    ): void {
        $statusHistory = StatusHistory::query()->create([
            'incident_id' => $incident->id,
            'from_status_id' => $fromStatus->id,
            'to_status_id' => $toStatus->id,
            'changed_by' => $changedBy->id,
            'changed_at' => now(),
        ]);

    }
}
