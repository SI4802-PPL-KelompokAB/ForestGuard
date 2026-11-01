<?php

namespace App\Actions\Incidents;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\CaseStatus;
use App\Models\Incident;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionIncidentStatus
{
    /**
     * Allowed status transitions matrix.
     *
     * @var array<string, array<string>>
     */
    protected array $allowedTransitions = [
        'Reported' => ['Under Verification'],
        'Under Verification' => ['Verified', 'Rejected'],
        'Verified' => ['Assigned'],
        'Assigned' => ['Investigation/Response'],
        'Investigation/Response' => ['Monitoring'],
        'Monitoring' => ['Resolved'],
    ];

    /**
     * Handle transition of incident case status.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidStatusTransitionException|ValidationException
     */
    public function handle(User $actor, Incident $incident, string $targetStatusName, array $data = []): Incident
    {
        abort_unless($actor->isUsable(), 403, 'Akun pengguna tidak aktif.');

        return DB::transaction(function () use ($actor, $incident, $targetStatusName, $data): Incident {
            $lockedIncident = Incident::with(['caseStatus'])->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $currentStatus = $lockedIncident->caseStatus;

            if ($currentStatus->is_terminal) {
                throw new InvalidStatusTransitionException('Kasus yang telah selesai atau ditolak tidak dapat diubah statusnya lagi.');
            }

            $targetStatus = CaseStatus::where('name', $targetStatusName)->first();
            if (! $targetStatus) {
                throw new InvalidStatusTransitionException("Status tujuan '{$targetStatusName}' tidak ditemukan.");
            }

            // Check if transition is in the allowed matrix
            $allowedTargets = $this->allowedTransitions[$currentStatus->name] ?? [];
            if (! in_array($targetStatusName, $allowedTargets, true)) {
                throw new InvalidStatusTransitionException(
                    "Transisi status dari {$currentStatus->name} ke {$targetStatusName} tidak diizinkan."
                );
            }

            // Validate Role Permissions
            $this->validateRolePermission($actor, $lockedIncident, $currentStatus->name, $targetStatusName);

            // Validate Specific Transition Requirements
            $this->validateRequirements($targetStatusName, $data);

            // Apply updates to incident
            $lockedIncident->case_status_id = $targetStatus->id;

            if ($targetStatusName === 'Verified' && ! empty($data['risk_level_id'])) {
                $lockedIncident->risk_level_id = $data['risk_level_id'];
            }

            if ($targetStatusName === 'Rejected') {
                $lockedIncident->reject_reason = $data['reject_reason'];
            }

            if ($targetStatusName === 'Resolved') {
                $lockedIncident->resolution_note = $data['resolution_note'];
                $lockedIncident->resolved_at = now();
            }

            $lockedIncident->save();

            // Create immutable status history record
            $note = $data['note'] ?? $data['reject_reason'] ?? $data['resolution_note'] ?? null;
            StatusHistory::create([
                'incident_id' => $lockedIncident->id,
                'from_status_id' => $currentStatus->id,
                'to_status_id' => $targetStatus->id,
                'changed_by' => $actor->id,
                'note' => $note,
                'changed_at' => now(),
            ]);

            return $lockedIncident->fresh(['caseStatus', 'statusHistories', 'riskLevel']);
        });
    }

    /**
     * Validate role authorization for status transition.
     */
    protected function validateRolePermission(User $actor, Incident $incident, string $fromStatus, string $toStatus): void
    {
        if ($actor->isCitizen()) {
            abort(403, 'Warga tidak memiliki hak untuk mengubah status kasus insiden.');
        }

        if ($actor->isRanger()) {
            // Ranger can only start investigation from Assigned or move to Monitoring
            $allowedForRanger = [
                'Assigned' => 'Investigation/Response',
                'Investigation/Response' => 'Monitoring',
            ];

            if (($allowedForRanger[$fromStatus] ?? null) !== $toStatus) {
                abort(403, 'Petugas Ranger tidak memiliki wewenang untuk transisi status ini.');
            }

            return;
        }

        if (! $actor->isAdmin()) {
            abort(403, 'Hanya Administrator yang memiliki wewenang mengelola status ini.');
        }
    }

    /**
     * Validate specific data requirements for the target status.
     *
     * @param  array<string, mixed>  $data
     */
    protected function validateRequirements(string $targetStatus, array $data): void
    {
        if ($targetStatus === 'Rejected') {
            $reason = trim((string) ($data['reject_reason'] ?? ''));
            if (empty($reason)) {
                throw ValidationException::withMessages([
                    'reject_reason' => ['Alasan penolakan laporan wajib diisi.'],
                ]);
            }
            if (mb_strlen($reason) < 10) {
                throw ValidationException::withMessages([
                    'reject_reason' => ['Alasan penolakan minimal harus 10 karakter.'],
                ]);
            }
        }

        if ($targetStatus === 'Verified') {
            if (empty($data['risk_level_id'])) {
                throw ValidationException::withMessages([
                    'risk_level_id' => ['Tingkat risiko wajib ditentukan saat verifikasi laporan.'],
                ]);
            }
        }

        if ($targetStatus === 'Resolved') {
            $resolution = trim((string) ($data['resolution_note'] ?? ''));
            if (empty($resolution)) {
                throw ValidationException::withMessages([
                    'resolution_note' => ['Catatan penyelesaian wajib diisi saat menutup kasus.'],
                ]);
            }
            if (mb_strlen($resolution) < 10) {
                throw ValidationException::withMessages([
                    'resolution_note' => ['Catatan penyelesaian minimal harus 10 karakter.'],
                ]);
            }
        }
    }
}
