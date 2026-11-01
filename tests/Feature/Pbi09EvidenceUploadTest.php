<?php

namespace Tests\Feature;

use App\Models\CaseStatus;
use App\Models\Evidence;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Database\Seeders\CaseStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Pbi09EvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CaseStatusSeeder::class);
    }

    /**
     * TC-09-01 (Normal): Unggah foto valid (JPG/PNG <= 5MB)
     */
    public function test_user_can_upload_valid_evidence_image(): void
    {
        $this->fakeEvidenceDisk();
        $user = User::factory()->admin()->create();
        $incident = $this->createIncident($user);
        $file = UploadedFile::fake()->image('bukti_lapangan.jpg', 800, 600)->size(2000);

        $response = $this->actingAs($user)->post(route('evidence.test.store'), $this->evidenceData($incident, $file));

        $response->assertRedirect(route('evidence.test'))->assertSessionHasNoErrors();
        $evidence = Evidence::query()->sole();
        $this->assertDatabaseHas('evidences', [
            'incident_id' => $incident->id,
            'mime_type' => 'image/jpeg',
            'latitude' => '-6.8000000',
            'longitude' => '107.6000000',
            'captured_at' => null,
        ]);
        $this->assertNotNull($evidence->created_at);
        $this->assertNotNull($evidence->updated_at);
        $this->assertGreaterThan(0, $evidence->file_size);
        $this->assertSame($evidence->file_size, Storage::disk('private')->size($evidence->file_path));
        $this->assertLessThan($file->getSize(), $evidence->file_size);
        Storage::disk('private')->assertExists($evidence->file_path);
    }

    /**
     * TC-09-02 (Exception): Validasi gagal jika ukuran file > 5 MB (5120 KB)
     */
    public function test_upload_fails_when_file_size_exceeds_5mb(): void
    {
        $this->fakeEvidenceDisk();
        $user = User::factory()->admin()->create();
        $incident = $this->createIncident($user);
        $file = UploadedFile::fake()->create('foto_besar.jpg', 6000, 'image/jpeg'); // 6 MB

        $response = $this->actingAs($user)->post(route('evidence.test.store'), $this->evidenceData($incident, $file));

        $response->assertSessionHasErrors(['photo']);
        $this->assertDatabaseCount('evidences', 0);
    }

    /**
     * TC-09-03 (Exception): Validasi gagal jika format file bukan JPG/PNG
     */
    public function test_upload_fails_when_file_format_is_invalid(): void
    {
        $this->fakeEvidenceDisk();
        $user = User::factory()->admin()->create();
        $incident = $this->createIncident($user);
        $file = UploadedFile::fake()->create('dokumen.pdf', 1000, 'application/pdf');

        $response = $this->actingAs($user)->post(route('evidence.test.store'), $this->evidenceData($incident, $file));

        $response->assertSessionHasErrors(['photo']);
        $this->assertDatabaseCount('evidences', 0);
    }

    private function fakeEvidenceDisk(): void
    {
        Storage::fake('private');
        config()->set('filesystems.evidence_disk', 'private');
    }

    private function evidenceData(Incident $incident, UploadedFile $file): array
    {
        return [
            'parent_type' => 'incident',
            'parent_id' => $incident->id,
            'photo' => $file,
        ];
    }

    private function createIncident(User $reporter): Incident
    {
        $forestArea = ForestArea::factory()->create();
        $incidentType = IncidentType::query()->create(['name' => 'Jenis '.Str::uuid()]);
        $caseStatus = CaseStatus::query()->where('name', 'Reported')->firstOrFail();

        return Incident::query()->create([
            'code' => 'INC-'.Str::uuid(),
            'client_uuid' => (string) Str::uuid(),
            'reporter_id' => $reporter->id,
            'incident_type_id' => $incidentType->id,
            'forest_area_id' => $forestArea->id,
            'case_status_id' => $caseStatus->id,
            'description' => 'Uji unggah bukti',
            'latitude' => -6.8,
            'longitude' => 107.6,
            'occurred_at' => now(),
        ]);
    }
}
