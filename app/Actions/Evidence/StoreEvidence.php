<?php

namespace App\Actions\Evidence;

use App\Exceptions\InvalidImageException;
use App\Models\Evidence;
use App\Models\Incident;
use App\Models\InvestigationUpdate;
use App\Models\Observation;
use App\Models\User;
use App\Support\ImageProcessor;
use App\Support\ProcessedImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreEvidence
{
    /**
     * Menyimpan bukti foto lapangan:
     * - Memvalidasi pengguna pengunggah
     * - Idempotent berdasarkan client_uuid
     * - Memproses EXIF dan kompresi
     * - Menentukan koordinat dan captured_at
     * - Mengunggah ke private storage
     * - Menyimpan metadata ke tabel evidences dengan perlindungan rollback berkas
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(
        Incident|Observation|InvestigationUpdate $parent,
        UploadedFile $file,
        User $uploader,
        ?string $clientUuid = null,
        ?float $browserLatitude = null,
        ?float $browserLongitude = null,
        string $fileField = 'photo'
    ): Evidence {
        // 1. Guard: Pengguna yang mengunggah harus aktif dan tidak dihapus
        if (! $uploader->isUsable()) {
            abort(403, 'Akun pengguna tidak aktif.');
        }

        // 2. Idempotent: Jika client_uuid sudah ada, kembalikan record yang ada tanpa upload ulang
        if ($clientUuid !== null && trim($clientUuid) !== '') {
            $existing = Evidence::query()->where('client_uuid', trim($clientUuid))->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        // 3. Proses gambar: Ekstraksi EXIF sebelum kompresi, lalu kompresi via Intervention Image v3
        /** @var ProcessedImage|null $processed */
        $processed = null;

        try {
            $processor = new ImageProcessor;
            $processed = $processor->process($file);
        } catch (InvalidImageException $e) {
            throw ValidationException::withMessages([
                $fileField => 'Berkas gambar tidak valid atau rusak',
            ]);
        }

        try {
            // 4. Tentukan koordinat (urutan: EXIF -> Browser Geolocation -> Koordinat Parent -> Tolak)
            $finalLatitude = null;
            $finalLongitude = null;

            if ($processed->latitude !== null && $processed->longitude !== null) {
                $finalLatitude = $processed->latitude;
                $finalLongitude = $processed->longitude;
            } elseif ($browserLatitude !== null && $browserLongitude !== null) {
                $finalLatitude = (float) $browserLatitude;
                $finalLongitude = (float) $browserLongitude;
            } else {
                // Koordinat dari entitas induk
                [$parentLat, $parentLon] = $this->resolveParentCoordinates($parent);
                if ($parentLat !== null && $parentLon !== null) {
                    $finalLatitude = $parentLat;
                    $finalLongitude = $parentLon;
                }
            }

            $finalCapturedAt = $processed->capturedAt;

            // 6. Siapkan penyimpanan ke private storage (Supabase Storage disk)
            $diskName = config('filesystems.evidence_disk', 's3');
            $disk = Storage::disk($diskName);

            $year = now()->format('Y');
            $month = now()->format('m');
            $randomName = Str::random(40);
            $extension = $processed->extension();
            $filePath = "evidences/{$year}/{$month}/{$randomName}.{$extension}";

            $uploadAttempted = false;

            try {
                $stream = fopen($processed->tempPath, 'r');
                if ($stream === false) {
                    throw new RuntimeException('Gagal membaca berkas sementara untuk diunggah.');
                }

                try {
                    $uploadAttempted = true;
                    if (! $disk->put($filePath, $stream, ['visibility' => 'private'])) {
                        throw new RuntimeException('Gagal menyimpan bukti foto ke storage privat.');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                // 7. Simpan record Evidence dalam DB::transaction
                // Memetakan tepat satu parent sesuai CHECK constraint
                $incidentId = $parent instanceof Incident ? $parent->id : null;
                $observationId = $parent instanceof Observation ? $parent->id : null;
                $updateId = $parent instanceof InvestigationUpdate ? $parent->id : null;

                $evidence = DB::transaction(function () use (
                    $clientUuid,
                    $incidentId,
                    $observationId,
                    $updateId,
                    $uploader,
                    $filePath,
                    $processed,
                    $finalLatitude,
                    $finalLongitude,
                    $finalCapturedAt
                ): Evidence {
                    return Evidence::query()->create([
                        'client_uuid' => $clientUuid ?: (string) Str::uuid(),
                        'incident_id' => $incidentId,
                        'observation_id' => $observationId,
                        'update_id' => $updateId,
                        'uploaded_by' => $uploader->id,
                        'file_path' => $filePath,
                        'mime_type' => $processed->mimeType,
                        'file_size' => $processed->fileSize,
                        'latitude' => $finalLatitude,
                        'longitude' => $finalLongitude,
                        'captured_at' => $finalCapturedAt,
                    ]);
                });

                return $evidence;
            } catch (Throwable $e) {
                // Jika insert ke basis data gagal, hapus berkas yang terlanjur diunggah ke storage
                if ($uploadAttempted) {
                    try {
                        $disk->delete($filePath);
                    } catch (Throwable) {
                        // Abaikan kesalahan penghapusan storage agar exception utama terlempar
                    }
                }

                throw $e;
            }
        } finally {
            // Selalu bersihkan berkas sementara lokal
            $processed->cleanup();
        }
    }

    /**
     * Dapatkan koordinat dari entitas induk.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function resolveParentCoordinates(Incident|Observation|InvestigationUpdate $parent): array
    {
        if ($parent instanceof Incident || $parent instanceof Observation) {
            $lat = $parent->latitude !== null ? (float) $parent->latitude : null;
            $lon = $parent->longitude !== null ? (float) $parent->longitude : null;

            return [$lat, $lon];
        }

        if ($parent instanceof InvestigationUpdate) {
            $incident = $parent->incident;
            if ($incident !== null) {
                $lat = $incident->latitude !== null ? (float) $incident->latitude : null;
                $lon = $incident->longitude !== null ? (float) $incident->longitude : null;

                return [$lat, $lon];
            }
        }

        return [null, null];
    }
}
