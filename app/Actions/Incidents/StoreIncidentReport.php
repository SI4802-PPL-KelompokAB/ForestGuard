<?php

namespace App\Actions\Incidents;

use App\Models\CaseStatus;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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

        $fileSize = $photo->getSize();
        $mimeType = $photo->getMimeType() ?: 'image/jpeg';
        $diskName = config('filesystems.evidence_disk', 's3');
        $filePath = $photo->store('evidences/incident-reports', ['disk' => $diskName, 'visibility' => 'private']);
        if ($filePath === false) {
            throw new RuntimeException('Gagal menyimpan bukti foto ke storage privat.');
        }

        try {
            return $this->persist($reporter, $validated, $filePath, $fileSize, $mimeType);
        } catch (Throwable $exception) {
            rescue(fn () => Storage::disk($diskName)->delete($filePath));

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persist(User $reporter, array $validated, string $filePath, int $fileSize, string $mimeType): Incident
    {
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
