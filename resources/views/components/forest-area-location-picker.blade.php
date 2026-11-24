@props([
    'latitude' => null,
    'longitude' => null,
])

@php
    $initialLatitude = is_numeric($latitude) ? (float) $latitude : null;
    $initialLongitude = is_numeric($longitude) ? (float) $longitude : null;
@endphp

<div class="forest-area-location-picker rounded-xl border border-slate-200 bg-slate-50 p-4"
     data-forest-area-map-picker
     data-default-lat="-6.917464"
     data-default-lng="107.619123">
    <div>
        <p class="text-sm font-semibold text-slate-800">Pilih titik pusat kawasan di peta <span class="text-red-500">*</span></p>
        <p class="mt-1 text-xs text-slate-500">Klik peta atau geser pin untuk menentukan lokasi kawasan hutan di Jawa Barat.</p>
    </div>

    <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div data-forest-area-map class="w-full" style="height: 20rem; min-height: 20rem;"></div>
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
            <p class="text-xs font-medium text-slate-500">Latitude pusat</p>
            <p data-forest-area-latitude class="mt-1 font-mono text-sm text-slate-800">{{ $initialLatitude !== null ? number_format($initialLatitude, 7, '.', '') : 'Belum dipilih' }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
            <p class="text-xs font-medium text-slate-500">Longitude pusat</p>
            <p data-forest-area-longitude class="mt-1 font-mono text-sm text-slate-800">{{ $initialLongitude !== null ? number_format($initialLongitude, 7, '.', '') : 'Belum dipilih' }}</p>
        </div>
    </div>

    <input type="hidden" name="center_lat" data-forest-area-latitude-input value="{{ old('center_lat', $initialLatitude) }}" required>
    <input type="hidden" name="center_lng" data-forest-area-longitude-input value="{{ old('center_lng', $initialLongitude) }}" required>

    @error('center_lat')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('center_lng')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
    <p data-forest-area-map-status class="mt-2 text-xs text-slate-500">Koordinat akan terisi otomatis setelah titik dipilih.</p>
</div>
