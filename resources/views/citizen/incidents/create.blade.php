@extends('layouts.app')

@section('title', 'Buat Laporan Insiden')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-forest-950">Buat Laporan Insiden</h1>
        <p class="text-sm text-slate-600">Kirimkan dugaan ancaman atau perusakan hutan beserta foto bukti dan lokasi koordinat GPS.</p>
    </div>

    @if ($errors->any())
        <div class="rounded-lg bg-red-50 p-4 border border-red-200">
            <h2 class="text-sm font-semibold text-red-800">Terdapat kesalahan dalam pengisian formulir:</h2>
            <ul class="mt-2 list-disc list-inside text-xs text-red-700 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-card>
        <form method="POST" action="{{ route('citizen.incidents.store') }}" enctype="multipart/form-data" id="incidentForm" class="space-y-6">
            @csrf
            <input type="hidden" name="client_uuid" id="client_uuid" value="{{ old('client_uuid', (string) Illuminate\Support\Str::uuid()) }}">

            {{-- 1. Jenis Insiden --}}
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">Jenis Kejadian / Ancaman <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($incidentTypes as $type)
                        <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:border-forest-600 transition-all {{ old('incident_type_id') == $type->id ? 'border-forest-600 bg-forest-50/50 ring-2 ring-forest-600/20' : 'border-slate-200 bg-white' }}">
                            <input type="radio" name="incident_type_id" value="{{ $type->id }}" class="text-forest-600 focus:ring-forest-500" {{ old('incident_type_id') == $type->id ? 'checked' : '' }} required>
                            <span class="text-sm font-medium text-slate-800">{{ $type->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 2. Kawasan Hutan --}}
            <div>
                <label for="forest_area_id" class="block text-sm font-semibold text-slate-800 mb-1">Perkiraan Kawasan Hutan <span class="text-red-500">*</span></label>
                <select name="forest_area_id" id="forest_area_id" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-forest-600 focus:ring-forest-500 text-sm" required>
                    <option value="">-- Pilih Kawasan Hutan --</option>
                    @foreach ($forestAreas as $area)
                        <option value="{{ $area->id }}" {{ old('forest_area_id') == $area->id ? 'selected' : '' }}>
                            {{ $area->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- 3. Waktu Kejadian --}}
            <div>
                <label for="occurred_at" class="block text-sm font-semibold text-slate-800 mb-1">Waktu Kejadian <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="occurred_at" id="occurred_at"
                       value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}"
                       max="{{ now()->format('Y-m-d\TH:i') }}"
                       class="w-full rounded-lg border-slate-300 shadow-sm focus:border-forest-600 focus:ring-forest-500 text-sm" required>
            </div>

            {{-- 4. Uraian Kejadian --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="description" class="block text-sm font-semibold text-slate-800">Uraian / Rincian Kejadian <span class="text-red-500">*</span></label>
                    <span id="charCount" class="text-xs text-slate-500">0 / min 15 karakter</span>
                </div>
                <textarea name="description" id="description" rows="4"
                          class="w-full rounded-lg border-slate-300 shadow-sm focus:border-forest-600 focus:ring-forest-500 text-sm placeholder:text-slate-400"
                          placeholder="Jelaskan secara rinci apa yang Anda temukan (minimal 15 karakter)..." required>{{ old('description') }}</textarea>
            </div>

            {{-- 5. Foto Bukti --}}
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-1">Foto Bukti Lapangan <span class="text-red-500">*</span></label>
                <p class="text-xs text-slate-500 mb-2">Format: JPG, JPEG, PNG. Ukuran maksimal: 5MB.</p>
                <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/jpg"
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-forest-50 file:text-forest-700 hover:file:bg-forest-100" required>
                <div id="photoPreview" class="mt-3 hidden">
                    <img src="" alt="Pratinjau Foto" class="max-h-48 rounded-lg border border-slate-200 object-cover">
                </div>
            </div>

            {{-- 6. Koordinat GPS --}}
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-800">Titik Koordinat Lokasi Kejadian (GPS) <span class="text-red-500">*</span></span>
                    <button type="button" id="btnGetLocation" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-forest-700 text-white rounded-lg hover:bg-forest-800 transition">
                        Ambil Lokasi Saat Ini
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="latitude" class="block text-xs font-medium text-slate-600 mb-1">Latitude</label>
                        <input type="number" step="any" name="latitude" id="latitude" value="{{ old('latitude') }}"
                               placeholder="-6.7574000" class="w-full rounded-lg border-slate-300 shadow-sm text-sm" required>
                    </div>
                    <div>
                        <label for="longitude" class="block text-xs font-medium text-slate-600 mb-1">Longitude</label>
                        <input type="number" step="any" name="longitude" id="longitude" value="{{ old('longitude') }}"
                               placeholder="107.6199200" class="w-full rounded-lg border-slate-300 shadow-sm text-sm" required>
                    </div>
                </div>
                <p id="gpsStatus" class="text-xs text-slate-500"></p>
            </div>

            {{-- Disclaimer --}}
            <div class="rounded-lg bg-amber-50 p-3 border border-amber-200 text-xs text-amber-800">
                <strong>Catatan:</strong> Laporan diperlakukan sebagai dugaan (<em>suspected</em>) sampai diverifikasi oleh Admin. Sistem tidak menentukan kesalahan pihak mana pun.
            </div>

            {{-- Submit Button --}}
            <div class="pt-2">
                <button type="submit" id="btnSubmit" class="w-full sm:w-auto px-6 py-2.5 bg-forest-700 text-white font-semibold rounded-lg hover:bg-forest-800 shadow-sm transition flex items-center justify-center gap-2">
                    Kirim Laporan
                </button>
            </div>
        </form>
    </x-card>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const descInput = document.getElementById('description');
        const charCount = document.getElementById('charCount');
        const photoInput = document.getElementById('photo');
        const photoPreview = document.getElementById('photoPreview');
        const btnGetLocation = document.getElementById('btnGetLocation');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const gpsStatus = document.getElementById('gpsStatus');
        const form = document.getElementById('incidentForm');
        const btnSubmit = document.getElementById('btnSubmit');

        // Character counter
        const updateCharCount = () => {
            const count = descInput.value.length;
            charCount.textContent = `${count} / min 15 karakter`;
            if (count < 15) {
                charCount.classList.add('text-red-500');
                charCount.classList.remove('text-forest-600');
            } else {
                charCount.classList.remove('text-red-500');
                charCount.classList.add('text-forest-600');
            }
        };
        descInput.addEventListener('input', updateCharCount);
        updateCharCount();

        // Photo preview
        photoInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    photoPreview.querySelector('img').src = ev.target.result;
                    photoPreview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        // Geolocation fetch
        btnGetLocation.addEventListener('click', () => {
            if (!navigator.geolocation) {
                gpsStatus.textContent = 'Geolocation tidak didukung oleh browser Anda.';
                return;
            }
            gpsStatus.textContent = 'Mendeteksi titik koordinat perangkat...';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    latInput.value = pos.coords.latitude.toFixed(7);
                    lngInput.value = pos.coords.longitude.toFixed(7);
                    gpsStatus.textContent = `Koordinat berhasil diambil (Akurasi: ±${Math.round(pos.coords.accuracy)} meter).`;
                },
                (err) => {
                    gpsStatus.textContent = 'Gagal mengakses GPS: ' + err.message;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });

        // Prevent double submission
        form.addEventListener('submit', () => {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = 'Mengirim Laporan...';
        });
    });
</script>
@endsection

