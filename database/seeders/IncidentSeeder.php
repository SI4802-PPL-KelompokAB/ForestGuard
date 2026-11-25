<?php

// PBI-24: Data dummy laporan untuk pengujian filter dan ekspor PDF.

namespace Database\Seeders;

use App\Models\CaseStatus;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        $reportedStatus = CaseStatus::query()->where('name', 'Reported')->firstOrFail();
        $underVerificationStatus = CaseStatus::query()->where('name', 'Under Verification')->firstOrFail();
        $verifiedStatus = CaseStatus::query()->where('name', 'Verified')->firstOrFail();

        $fire = IncidentType::query()->where('name', 'Kebakaran hutan')->firstOrFail();
        $illegalLogging = IncidentType::query()->where('name', 'Dugaan illegal logging')->firstOrFail();
        $wildlife = IncidentType::query()->where('name', 'Ancaman satwa liar')->firstOrFail();
        $landDegradation = IncidentType::query()->where('name', 'Degradasi lahan')->firstOrFail();

        $ciremai = ForestArea::query()->where('name', 'Taman Nasional Gunung Ciremai')->firstOrFail();
        $djuanda = ForestArea::query()->where('name', 'Taman Hutan Raya Ir. H. Djuanda')->firstOrFail();
        $gede = ForestArea::query()->where('name', 'Taman Nasional Gunung Gede Pangrango')->firstOrFail();
        $halimun = ForestArea::query()->where('name', 'Taman Nasional Gunung Halimun Salak')->firstOrFail();

        $reporters = User::query()->whereIn('email', [
            'citizen1@forestguard.test',
            'citizen2@forestguard.test',
            'ranger1@forestguard.test',
            'ranger2@forestguard.test',
        ])->get()->keyBy('email');

        $baseDate = now()->startOfMonth();
        $incidents = [
            [
                'code' => 'INC-DEMO-001',
                'client_uuid' => '00000000-0000-4000-8000-000000000001',
                'reporter_id' => $reporters['citizen1@forestguard.test']->id,
                'incident_type_id' => $fire->id,
                'forest_area_id' => $ciremai->id,
                'case_status_id' => $reportedStatus->id,
                'description' => 'Terlihat asap dan titik api kecil di sekitar jalur kawasan hutan.',
                'latitude' => -6.8931000,
                'longitude' => 108.4071000,
                'gps_accuracy' => 8.50,
                'occurred_at' => $baseDate->copy()->addDays(1)->setTime(9, 15),
            ],
            [
                'code' => 'INC-DEMO-002',
                'client_uuid' => '00000000-0000-4000-8000-000000000002',
                'reporter_id' => $reporters['ranger1@forestguard.test']->id,
                'incident_type_id' => $illegalLogging->id,
                'forest_area_id' => $djuanda->id,
                'case_status_id' => $underVerificationStatus->id,
                'description' => 'Ditemukan bekas tebangan pohon dan tumpukan kayu di area patroli.',
                'latitude' => -6.8561000,
                'longitude' => 107.6321000,
                'gps_accuracy' => 5.20,
                'occurred_at' => $baseDate->copy()->addDays(3)->setTime(11, 30),
            ],
            [
                'code' => 'INC-DEMO-003',
                'client_uuid' => '00000000-0000-4000-8000-000000000003',
                'reporter_id' => $reporters['citizen2@forestguard.test']->id,
                'incident_type_id' => $wildlife->id,
                'forest_area_id' => $gede->id,
                'case_status_id' => $verifiedStatus->id,
                'description' => 'Warga melaporkan adanya aktivitas satwa liar di dekat jalur pendakian.',
                'latitude' => -6.7801000,
                'longitude' => 106.9801000,
                'gps_accuracy' => 12.00,
                'occurred_at' => $baseDate->copy()->addDays(6)->setTime(14, 0),
            ],
            [
                'code' => 'INC-DEMO-004',
                'client_uuid' => '00000000-0000-4000-8000-000000000004',
                'reporter_id' => $reporters['ranger2@forestguard.test']->id,
                'incident_type_id' => $landDegradation->id,
                'forest_area_id' => $halimun->id,
                'case_status_id' => $reportedStatus->id,
                'description' => 'Terjadi kerusakan lahan dan erosi pada bagian lereng kawasan hutan.',
                'latitude' => -6.7101000,
                'longitude' => 106.5501000,
                'gps_accuracy' => 6.75,
                'occurred_at' => $baseDate->copy()->addDays(9)->setTime(8, 45),
            ],
            [
                'code' => 'INC-DEMO-005',
                'client_uuid' => '00000000-0000-4000-8000-000000000005',
                'reporter_id' => $reporters['citizen1@forestguard.test']->id,
                'incident_type_id' => $fire->id,
                'forest_area_id' => $djuanda->id,
                'case_status_id' => $underVerificationStatus->id,
                'description' => 'Asap terlihat dari area perbatasan kawasan dan perlu diverifikasi petugas.',
                'latitude' => -6.8559000,
                'longitude' => 107.6318000,
                'gps_accuracy' => 10.25,
                'occurred_at' => $baseDate->copy()->addDays(12)->setTime(16, 20),
            ],
        ];

        foreach ($incidents as $data) {
            Incident::query()->updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
