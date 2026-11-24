<?php

namespace App\Actions\Incidents;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\CaseStatus;
use App\Models\Incident;
use App\Models\RiskLevel;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
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
        if (in_array($targetStatusName, ['Under Verification', 'Verified', 'Rejected'], true)) {
            abort_unless($actor->isAdmin(), 403, 'Hanya Administrator yang dapat memverifikasi laporan.');
        }

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
            if ($targetStatusName === 'Rejected' && is_string($data['reject_reason'] ?? null)) {
                $data['reject_reason'] = trim($data['reject_reason']);
            }
            $this->validateRequirements($targetStatusName, $data);

            // Apply updates to incident
            $lockedIncident->case_status_id = $targetStatus->id;

            if ($targetStatusName === 'Verified') {
                $lockedIncident->risk_level_id = $this->verificationRisk($data)->id;
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
            $note = match ($targetStatusName) {
                'Rejected' => $data['reject_reason'],
                'Resolved' => $data['note'] ?? $data['resolution_note'],
                default => $data['note'] ?? null,
            };
            StatusHistory::create([
                'incident_id' => $lockedIncident->id,
                'from_status_id' => $currentStatus->id,
                'to_status_id' => $targetStatus->id,
                'changed_by' => $actor->id,
                'note' => $note,
                'changed_at' => now(),
            ]);

            if (in_array($targetStatusName, ['Verified', 'Rejected'], true)) {
                DB::afterCommit(fn () => rescue(fn () => $lockedIncident->reporter->appNotifications()->create([
                    'type' => 'incident.verification-decided',
                    'data' => [
                        'incident_id' => $lockedIncident->id,
                        'code' => $lockedIncident->code,
                        'status' => $targetStatusName,
                    ],
                ])));
            }

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
            $reason = $data['reject_reason'] ?? null;
            if (! is_string($reason) || $reason === '') {
                throw ValidationException::withMessages([
                    'reject_reason' => ['Alasan penolakan laporan wajib diisi.'],
                ]);
            }
            if (mb_strlen($reason) < 10) {
                throw ValidationException::withMessages([
                    'reject_reason' => ['Alasan penolakan minimal harus 10 karakter.'],
                ]);
            }
            if (mb_strlen($reason) > 1000) {
                throw ValidationException::withMessages([
                    'reject_reason' => ['Alasan penolakan maksimal 1000 karakter.'],
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function verificationRisk(array $data): RiskLevel
    {
        $riskId = filter_var($data['risk_level_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $risk = $riskId ? RiskLevel::active()->sharedLock()->find($riskId) : null;

        if (! $risk) {
            throw ValidationException::withMessages(['risk_level_id' => 'Pilih tingkat risiko yang aktif.']);
        }

        if (isset($data['risk_snapshot'])) {
            try {
                $options = json_decode(Crypt::decryptString($data['risk_snapshot']), true);
            } catch (DecryptException) {
                $options = [];
            }

            if (($options[$riskId] ?? null) !== $risk->attributesToArray()) {
                throw ValidationException::withMessages([
                    'risk_level_id' => 'Pilihan risiko sudah berubah. Pilih kembali tingkat risiko dari daftar terbaru.',
                ]);
            }
        }

        return $risk;
    }
}
