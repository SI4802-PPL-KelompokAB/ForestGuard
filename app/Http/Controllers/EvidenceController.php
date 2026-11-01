<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EvidenceController extends Controller
{
    /**
     * Akses berkas bukti foto lapangan melalui temporary signed URL berumur 5 menit.
     */
    public function __invoke(Request $request, Evidence $evidence): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Guard: Pengguna harus aktif
        if (! $user || ! $user->isUsable()) {
            abort(403, 'Akun pengguna tidak aktif.');
        }

        // 1. Admin berhak mengakses semua berkas bukti
        if ($user->isAdmin()) {
            return $this->redirectToSignedUrl($evidence);
        }

        // 2. Pengunggah asli berhak mengakses bukti yang diunggahnya
        if ($evidence->uploaded_by === $user->id) {
            return $this->redirectToSignedUrl($evidence);
        }

        // 3. Otorisasi spesifik per entitas induk (Parent)

        // Parent: Laporan Insiden
        if ($evidence->incident_id !== null) {
            $incident = $evidence->incident;

            if ($incident !== null) {
                // Pelapor insiden berhak melihat bukti insiden miliknya
                if ($incident->reporter_id === $user->id) {
                    return $this->redirectToSignedUrl($evidence);
                }

                // Ranger yang ditugaskan pada penanganan insiden
                if ($incident->assignments()->where('ranger_id', $user->id)->exists()) {
                    return $this->redirectToSignedUrl($evidence);
                }

                // Ranger yang berpatroli saat insiden ditemukan
                if ($incident->patrol !== null && $incident->patrol->ranger_id === $user->id) {
                    return $this->redirectToSignedUrl($evidence);
                }
            }
        }

        // Parent: Observasi Patroli (internal ranger, citizen dilarang)
        if ($evidence->observation_id !== null) {
            $observation = $evidence->observation;

            if ($observation !== null && $observation->patrol !== null) {
                if ($user->isRanger() && $observation->patrol->ranger_id === $user->id) {
                    return $this->redirectToSignedUrl($evidence);
                }
            }
        }

        // Parent: Pembaruan Investigasi (internal ranger/admin, citizen dilarang)
        if ($evidence->update_id !== null) {
            $update = $evidence->investigationUpdate;

            if ($update !== null) {
                // Ranger pembuat catatan investigasi
                if ($user->isRanger() && $update->ranger_id === $user->id) {
                    return $this->redirectToSignedUrl($evidence);
                }

                // Ranger lain yang ditugaskan pada kasus insiden tersebut
                if ($user->isRanger() && $update->incident !== null) {
                    if ($update->incident->assignments()->where('ranger_id', $user->id)->exists()) {
                        return $this->redirectToSignedUrl($evidence);
                    }
                }
            }
        }

        // Jika tidak memenuhi aturan akses di atas, tolak dengan 403 Forbidden
        abort(403, 'Anda tidak memiliki hak akses untuk melihat bukti ini.');
    }

    /**
     * Menghasilkan temporary signed URL berumur 5 menit dan mengarahkan pengguna.
     */
    private function redirectToSignedUrl(Evidence $evidence): RedirectResponse
    {
        $diskName = config('filesystems.evidence_disk', 's3');
        $disk = Storage::disk($diskName);

        $temporaryUrl = $disk->temporaryUrl(
            $evidence->file_path,
            now()->addMinutes(5)
        );

        return redirect()->away($temporaryUrl);
    }
}
