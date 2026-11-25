<?php

namespace Tests\Feature;

use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\ForestAreaSeeder;
use Database\Seeders\IncidentTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pbi24ExportReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RoleSeeder::class,
            CaseStatusSeeder::class,
            IncidentTypeSeeder::class,
            ForestAreaSeeder::class,
        ]);
    }

    public function test_admin_can_open_report_index_and_filter_incidents(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createIncident($admin, 'INC-REPORT-01');

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['status' => 'Reported']))
            ->assertOk()
            ->assertSee('Ekspor Laporan PDF')
            ->assertSee('INC-REPORT-01');
    }

    public function test_admin_can_download_a_pdf_with_filtered_report_data(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($admin, 'INC-REPORT-02');

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.pdf', [
                'date_from' => $incident->occurred_at->format('Y-m-d'),
                'date_to' => $incident->occurred_at->format('Y-m-d'),
                'status' => 'Reported',
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('laporan-insiden-', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('INC-REPORT-02', $response->getContent());
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.reports.pdf', [
                'date_from' => '2026-11-20',
                'date_to' => '2026-11-01',
            ]))
            ->assertSessionHasErrors('date_to');
    }

    public function test_non_admin_users_cannot_export_reports(): void
    {
        $ranger = User::factory()->ranger()->create();

        $this->actingAs($ranger)
            ->get(route('admin.reports.pdf'))
            ->assertForbidden();
    }

    public function test_export_does_not_modify_incident_data(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = $this->createIncident($admin, 'INC-REPORT-03');
        $before = $incident->fresh()->toArray();

        $this->actingAs($admin)->get(route('admin.reports.pdf'));

        $this->assertSame($before, $incident->fresh()->toArray());
    }

    private function createIncident(User $reporter, string $code): Incident
    {
        return Incident::factory()
            ->for($reporter, 'reporter')
            ->for(IncidentType::firstOrFail(), 'incidentType')
            ->for(ForestArea::firstOrFail(), 'forestArea')
            ->state([
                'code' => $code,
                'occurred_at' => now()->subDay(),
            ])
            ->create();
    }
}
