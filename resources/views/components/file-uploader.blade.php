@props([
    'name' => 'photo',
    'label' => 'Unggah Bukti Foto Lapangan',
    'latitude' => null,
    'longitude' => null,
])

@php
    $id = $attributes->get('id', $name . '_' . uniqid());
@endphp

<div class="space-y-2" id="{{ $id }}_wrapper">
    <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>

    {{-- Input Tersembunyi untuk Koordinat Browser Geolocation --}}
    <input type="hidden" name="latitude" id="{{ $id }}_lat" value="{{ old('latitude', $latitude) }}">
    <input type="hidden" name="longitude" id="{{ $id }}_lng" value="{{ old('longitude', $longitude) }}">

    {{-- Area Drag and Drop & Tombol Pilihan --}}
    <div
        id="{{ $id }}_dropzone"
        class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center transition-colors hover:border-forest-600 hover:bg-forest-50/30"
    >
        {{-- Input File Galeri --}}
        <input
            type="file"
            id="{{ $id }}_file"
            name="{{ $name }}"
            accept="image/jpeg,image/png"
            class="sr-only"
        >

        {{-- Input File Kamera --}}
        <input
            type="file"
            id="{{ $id }}_camera"
            accept="image/jpeg,image/png"
            capture="environment"
            class="sr-only"
        >

        {{-- Tampilan Kosong (Sebelum File Dipilih) --}}
        <div id="{{ $id }}_empty_state" class="flex flex-col items-center space-y-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-forest-100 text-forest-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-700">Seret dan lepas file foto di sini, atau</p>
                <p class="mt-0.5 text-xs text-slate-500">Format: JPG, JPEG, PNG (Maksimal 5 MB)</p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                <button
                    type="button"
                    id="{{ $id }}_gallery_btn"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-forest-600 focus:ring-offset-1"
                >
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Pilih dari Galeri
                </button>
                <button
                    type="button"
                    id="{{ $id }}_camera_btn"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-forest-600 bg-forest-50 px-3 py-1.5 text-xs font-semibold text-forest-800 shadow-sm hover:bg-forest-100 focus:outline-none focus:ring-2 focus:ring-forest-600 focus:ring-offset-1"
                >
                    <svg class="h-4 w-4 text-forest-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Ambil Kamera
                </button>
            </div>
        </div>

        {{-- Tampilan Preview (Setelah File Dipilih) --}}
        <div id="{{ $id }}_preview_state" class="hidden flex-col items-center space-y-3">
            <div class="relative max-w-xs overflow-hidden rounded-xl border border-slate-200 bg-black/5 shadow-sm">
                <img id="{{ $id }}_preview_img" src="" alt="Pratinjau Foto" class="max-h-56 w-auto object-contain">
            </div>
            <div class="text-center">
                <p id="{{ $id }}_filename" class="text-xs font-semibold text-slate-800 break-all"></p>
                <p id="{{ $id }}_filesize" class="text-xs text-slate-500"></p>
            </div>
            <button
                type="button"
                id="{{ $id }}_remove_btn"
                class="inline-flex items-center gap-1 rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Ganti Foto
            </button>
        </div>
    </div>

    {{-- Indikator Status Geolocation GPS --}}
    <p id="{{ $id }}_geo_status" class="hidden text-xs text-emerald-700 flex items-center gap-1">
        <svg class="h-3.5 w-3.5 text-emerald-600 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span id="{{ $id }}_geo_text"></span>
    </p>

    {{-- Pesan Eror Validasi Klien & Server --}}
    <p id="{{ $id }}_client_error" class="hidden text-sm font-medium text-red-600"></p>
    @error($name)
        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>

<script>
(function() {
    const id = @json($id);
    const dropzone = document.getElementById(id + '_dropzone');
    const fileInput = document.getElementById(id + '_file');
    const cameraInput = document.getElementById(id + '_camera');
    const galleryBtn = document.getElementById(id + '_gallery_btn');
    const cameraBtn = document.getElementById(id + '_camera_btn');
    const removeBtn = document.getElementById(id + '_remove_btn');
    const emptyState = document.getElementById(id + '_empty_state');
    const previewState = document.getElementById(id + '_preview_state');
    const previewImg = document.getElementById(id + '_preview_img');
    const filenameEl = document.getElementById(id + '_filename');
    const filesizeEl = document.getElementById(id + '_filesize');
    const clientError = document.getElementById(id + '_client_error');
    const latInput = document.getElementById(id + '_lat');
    const lngInput = document.getElementById(id + '_lng');
    const geoStatus = document.getElementById(id + '_geo_status');
    const geoText = document.getElementById(id + '_geo_text');

    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB (5,242,880 bytes)
    const ALLOWED_MIME = ['image/jpeg', 'image/jpg', 'image/png'];
    const ALLOWED_EXT = ['.jpg', '.jpeg', '.png'];

    // 1. Geolocation API: Ambil koordinat browser secara background
    if (navigator.geolocation && (!latInput.value || !lngInput.value)) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                latInput.value = position.coords.latitude.toFixed(7);
                lngInput.value = position.coords.longitude.toFixed(7);
                geoText.textContent = 'Lokasi perangkat terdeteksi (' + latInput.value + ', ' + lngInput.value + ')';
                geoStatus.classList.remove('hidden');
            },
            function(err) {
                // Pengguna menolak atau lokasi mati: JANGAN memblokir upload
                console.info('Geolocation tidak diizinkan atau tidak tersedia:', err.message);
            },
            { enableHighAccuracy: true, timeout: 7000, maximumAge: 60000 }
        );
    } else if (latInput.value && lngInput.value) {
        geoText.textContent = 'Lokasi perangkat (' + latInput.value + ', ' + lngInput.value + ')';
        geoStatus.classList.remove('hidden');
    }

    // 2. Tombol Pemicu Input File
    galleryBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        fileInput.click();
    });

    cameraBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        cameraInput.click();
    });

    removeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        clearFile();
    });

    // 3. Listener Perubahan File
    fileInput.addEventListener('change', function() {
        if (fileInput.files && fileInput.files[0]) {
            handleFileSelection(fileInput.files[0]);
        }
    });

    cameraInput.addEventListener('change', function() {
        if (cameraInput.files && cameraInput.files[0]) {
            const capturedFile = cameraInput.files[0];
            // Sinkronkan ke input file utama menggunakan DataTransfer
            try {
                const dt = new DataTransfer();
                dt.items.add(capturedFile);
                fileInput.files = dt.files;
            } catch (err) {
                // Fallback browser lama
            }
            handleFileSelection(capturedFile);
        }
    });

    // 4. Drag and Drop Handlers
    ['dragenter', 'dragover'].forEach(function(eventName) {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('border-forest-600', 'bg-forest-50/50');
        }, false);
    });

    ['dragleave', 'drop'].forEach(function(eventName) {
        dropzone.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('border-forest-600', 'bg-forest-50/50');
        }, false);
    });

    dropzone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            const droppedFile = dt.files[0];
            try {
                const transfer = new DataTransfer();
                transfer.items.add(droppedFile);
                fileInput.files = transfer.files;
            } catch (err) {}
            handleFileSelection(droppedFile);
        }
    });

    // 5. Validasi Sisi Klien & Preview
    function handleFileSelection(file) {
        hideError();

        // Cek file kosong (0 byte)
        if (file.size === 0) {
            showError('Berkas gambar tidak valid atau rusak');
            clearFile();
            return;
        }

        // Cek format berkas (MIME & Ekstensi)
        const nameLower = file.name.toLowerCase();
        const hasValidExt = ALLOWED_EXT.some(ext => nameLower.endsWith(ext));
        const hasValidMime = ALLOWED_MIME.includes(file.type);

        if (!hasValidExt && !hasValidMime) {
            showError('Format berkas harus berupa JPG, JPEG, atau PNG');
            clearFile();
            return;
        }

        // Cek ukuran berkas (Maks 5 MB)
        if (file.size > MAX_FILE_SIZE) {
            showError('Ukuran berkas maksimal adalah 5 MB');
            clearFile();
            return;
        }

        // Tampilkan pratinjau gambar via FileReader
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            filenameEl.textContent = file.name;
            filesizeEl.textContent = formatBytes(file.size);
            emptyState.classList.add('hidden');
            previewState.classList.remove('hidden');
            previewState.classList.add('flex');
        };
        reader.onerror = function() {
            showError('Berkas gambar tidak valid atau rusak');
            clearFile();
        };
        reader.readAsDataURL(file);
    }

    function clearFile() {
        fileInput.value = '';
        cameraInput.value = '';
        previewImg.src = '';
        filenameEl.textContent = '';
        filesizeEl.textContent = '';
        previewState.classList.add('hidden');
        previewState.classList.remove('flex');
        emptyState.classList.remove('hidden');
    }

    function showError(msg) {
        clientError.textContent = msg;
        clientError.classList.remove('hidden');
    }

    function hideError() {
        clientError.textContent = '';
        clientError.classList.add('hidden');
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
})();
</script>

