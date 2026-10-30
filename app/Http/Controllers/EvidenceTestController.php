<?php

namespace App\Http\Controllers;

use App\Actions\Evidence\StoreEvidence;
use App\Http\Requests\StoreEvidenceRequest;
use App\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvidenceTestController extends Controller
{
    /**
     * Memeriksa otorisasi halaman uji (hanya Admin atau environment lokal).
     */
    private function authorizeTestAccess(Request $request): void
    {
        if (! app()->environment('local') && ! $request->user()?->isAdmin()) {
            abort(403, 'Halaman uji coba ini hanya dapat diakses pada environment lokal atau oleh Administrator.');
        }
    }

    /**
     * Menampilkan form uji coba uploader bukti foto lapangan (PBI 9).
     *
     * CATATAN: Halaman ini diperuntukkan sebagai test bench mandiri untuk PBI 9
     * dan akan dinonaktifkan setelah PBI 10 (Submit Laporan Insiden Terintegrasi) selesai.
     */
    public function index(Request $request): View
    {
        $this->authorizeTestAccess($request);

        // Pengguna memilih dari insiden yang SUDAH ADA (tanpa membuat data dummy)
        $incidents = Incident::query()
            ->with(['incidentType', 'forestArea', 'reporter'])
            ->latest('id')
            ->limit(30)
            ->get();

        return view('evidence.test-uploader', compact('incidents'));
    }

    /**
     * Memproses pengunggahan berkas bukti uji coba.
     */
    public function store(StoreEvidenceRequest $request, StoreEvidence $action): RedirectResponse
    {
        $this->authorizeTestAccess($request);

        $file = $request->uploadedFile();
        $originalSize = $file->getSize();

        // Resolusi entitas induk dari insiden yang dipilih
        $parent = $request->resolveParent();

        $evidence = $action->handle(
            parent: $parent,
            file: $file,
            uploader: $request->user(),
            clientUuid: $request->input('client_uuid'),
            browserLatitude: $request->filled('latitude') ? (float) $request->input('latitude') : null,
            browserLongitude: $request->filled('longitude') ? (float) $request->input('longitude') : null,
        );

        $savedBytes = max(0, $originalSize - $evidence->file_size);
        $savedPercent = $originalSize > 0 ? round(($savedBytes / $originalSize) * 100, 1) : 0;

        return redirect()->route('evidence.test')->with('evidence_result', [
            'id' => $evidence->id,
            'client_uuid' => $evidence->client_uuid,
            'parent_type' => class_basename($parent),
            'parent_code' => $parent->code ?? ('ID #'.$parent->id),
            'original_size' => $originalSize,
            'compressed_size' => $evidence->file_size,
            'saved_percent' => $savedPercent,
            'latitude' => $evidence->latitude,
            'longitude' => $evidence->longitude,
            'captured_at' => $evidence->captured_at ? CarbonImmutable::parse($evidence->captured_at)->format('Y-m-d H:i:s') : null,
            'file_path' => $evidence->file_path,
            'access_url' => route('evidence.show', $evidence),
        ])->with('status', 'Bukti foto berhasil diunggah, dikompresi, dan tersimpan di storage privat!');
    }
}
