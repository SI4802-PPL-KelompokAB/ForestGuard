<section data-field-recording class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="font-semibold text-slate-800">Observasi lapangan</h2>
    <p data-field-message role="status" aria-live="polite" class="whitespace-pre-line text-sm text-slate-700"></p>
    <div data-observations-list class="space-y-3">
        @include('patrols._observations')
    </div>

    @if (auth()->user()->isRanger() && $patrol->ranger_id === auth()->id() && $isInProgress)
        <details data-observation-details class="rounded-xl border border-slate-200 p-4">
            <summary class="cursor-pointer py-2 text-sm font-semibold text-forest-800">Tambah observasi</summary>
            <form method="POST" action="{{ route('ranger.patrols.observations.store', $patrol) }}" enctype="multipart/form-data" data-observation-form novalidate class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="client_uuid" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="location_recorded_at">
                <div>
                    <label for="observation-category" class="block text-sm font-medium text-slate-700">Kategori</label>
                    <select id="observation-category" name="category" required class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">
                        @foreach (\App\Enums\ObservationCategory::cases() as $category)
                            <option value="{{ $category->value }}">{{ $category->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="observation-description" class="block text-sm font-medium text-slate-700">Catatan (minimal 5 karakter setelah trim)</label>
                    <textarea id="observation-description" name="description" rows="4" required minlength="5" class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea>
                </div>
                <p data-observation-location class="break-words text-xs text-slate-600">Belum ada lokasi yang dipilih.</p>
                <x-location-picker label="Titik Koordinat Observasi" :show-accuracy="false" />
                <x-file-uploader id="observation-photo" label="Foto observasi (opsional)" :capture-location="false" />
                <x-button data-observation-submit class="min-h-11">Simpan observasi</x-button>
            </form>
        </details>
        <noscript><p class="text-sm text-slate-600">Aktifkan JavaScript dan akses lokasi untuk mencatat checkpoint dan observasi.</p></noscript>
    @endif
</section>
