@props([
    'latitude' => null,
    'longitude' => null,
    'accuracy' => null,
])

@php
    $initialLatitude = is_numeric($latitude) ? (float) $latitude : null;
    $initialLongitude = is_numeric($longitude) ? (float) $longitude : null;
    $initialAccuracy = is_numeric($accuracy) ? (float) $accuracy : null;
@endphp

<div class="location-picker rounded-xl border border-slate-200 bg-slate-50 p-4" data-location-picker data-default-lat="-6.917464" data-default-lng="107.619123" data-default-accuracy="{{ $initialAccuracy ?? '' }}">
    <div class="flex items-center justify-between gap-3">
        <span class="text-sm font-semibold text-slate-800">Titik Koordinat Lokasi Kejadian (GPS) <span class="text-red-500">*</span></span>
        <button type="button" id="btnGetLocation" data-location-btn-get class="inline-flex items-center gap-1.5 rounded-lg bg-forest-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-forest-800">
            Ambil Lokasi Saat Ini
        </button>
    </div>

    <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div id="location-map" data-location-map class="w-full" style="height: 18rem; min-height: 18rem;"></div>
    </div>

    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
            <label for="latitude" class="mb-1 block text-xs font-medium text-slate-600">Latitude</label>
            <input type="number" step="any" name="latitude" id="latitude" data-location-lat value="{{ old('latitude', $initialLatitude) }}" placeholder="-6.7574000" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-forest-600 focus:ring-forest-500" required>
        </div>
        <div>
            <label for="longitude" class="mb-1 block text-xs font-medium text-slate-600">Longitude</label>
            <input type="number" step="any" name="longitude" id="longitude" data-location-lng value="{{ old('longitude', $initialLongitude) }}" placeholder="107.6199200" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-forest-600 focus:ring-forest-500" required>
        </div>
        <div>
            <label for="gps_accuracy" class="mb-1 block text-xs font-medium text-slate-600">Akurasi GPS (m)</label>
            <input type="number" step="any" min="0" name="gps_accuracy" id="gps_accuracy" data-location-accuracy value="{{ old('gps_accuracy', $initialAccuracy) }}" placeholder="15" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-forest-600 focus:ring-forest-500">
        </div>
    </div>

    <p id="gpsStatus" data-location-status class="mt-2 text-xs text-slate-500">Pilih lokasi dengan pin pada peta atau ambil lokasi perangkat.</p>
</div>
