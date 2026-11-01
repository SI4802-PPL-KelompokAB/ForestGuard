<?php

namespace App\Actions\Incidents;

use App\Models\CaseStatus;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreIncidentReport
{
    /**
     * Store a new incident report with its photo evidence and initial status history.
     *
     * @param  array<string, mixed>  $validated
     */
    public function handle(User $reporter, array $validated, UploadedFile $photo): Incident
    {
        abort_unless($reporter->isUsable(), 403, 'Akun pengguna tidak aktif.');

        // Idempotency check: if client_uuid already exists, return existing record
        $existing = Incident::with(['evidences', 'caseStatus', 'statusHistories', 'incidentType', 'forestArea'])
            ->where('client_uuid', $validated['client_uuid'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $filePath = $photo->store('evidences/incidents', 'public');
        $fileSize = $photo->getSize();
        $mimeType = $photo->getMimeType() ?: 'image/jpeg';

        return DB::transaction(function () use ($reporter, $validated, $filePath, $fileSize, $mimeType): Incident {
            $reportedStatus = CaseStatus::where('name', 'Reported')->firstOrFail();

            $code = $this->generateUniqueCode();

            $incident = Incident::create([
                'code' => $code,
                'client_uuid' => $validated['client_uuid'],
                'reporter_id' => $reporter->id,
                'incident_type_id' => $validated['incident_type_id'],
                'forest_area_id' => $validated['forest_area_id'],
                'patrol_id' => $validated['patrol_id'] ?? null,
                'case_status_id' => $reportedStatus->id,
                'description' => $validated['description'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'gps_accuracy' => $validated['gps_accuracy'] ?? null,
                'occurred_at' => $validated['occurred_at'],
            ]);

            Evidence::create([
                'client_uuid' => (string) Str::uuid(),
                'incident_id' => $incident->id,
                'observation_id' => null,
                'update_id' => null,
                'uploaded_by' => $reporter->id,
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'captured_at' => $validated['occurred_at'],
            ]);

            StatusHistory::create([
                'incident_id' => $incident->id,
                'from_status_id' => null,
                'to_status_id' => $reportedStatus->id,
                'changed_by' => $reporter->id,
                'note' => 'Laporan insiden berhasil dibuat',
                'changed_at' => now(),
            ]);

            return $incident->fresh(['evidences', 'caseStatus', 'statusHistories', 'incidentType', 'forestArea']);
        });
    }

    /**
     * Generate unique incident code.
     */
    protected function generateUniqueCode(): string
    {
        $prefix = 'INC-'.now()->format('Ym').'-';

        do {
            $number = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix.$number;
        } while (Incident::where('code', $candidate)->exists());

        return $candidate;
    }
}
