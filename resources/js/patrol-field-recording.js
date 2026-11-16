import { captureDeviceLocation } from './device-location';

const fieldRecording = document.querySelector('[data-field-recording]');
const checkpointsCard = document.querySelector('[data-checkpoints-card]');
const form = fieldRecording?.querySelector('[data-observation-form]');

if (fieldRecording && document.querySelector('[data-patrol-execution]')?.dataset.status === 'Berlangsung') {
    const message = fieldRecording.querySelector('[data-field-message]');
    const locationDisplay = fieldRecording.querySelector('[data-observation-location]');
    let busy = false;
    let location = null;
    const latitudeInput = form?.elements.namedItem('latitude');
    const longitudeInput = form?.elements.namedItem('longitude');
    const locationRecordedAtInput = form?.elements.namedItem('location_recorded_at');
    const showLocation = value => {
        location = value;
        if (!form || !value) return;
        latitudeInput.value = value.latitude;
        longitudeInput.value = value.longitude;
        locationRecordedAtInput.value = value.location_recorded_at;
        for (const input of [latitudeInput, longitudeInput]) {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        locationDisplay.textContent = `Lokasi GPS: ${value.latitude.toFixed(7)}, ${value.longitude.toFixed(7)}. Diambil ${new Date(value.location_recorded_at).toLocaleString('id-ID')}.`;
    };
    const updateLocationFromPicker = event => {
        const latitudeValue = String(event.detail.latitude).trim();
        const longitudeValue = String(event.detail.longitude).trim();
        const latitude = Number(latitudeValue);
        const longitude = Number(longitudeValue);

        if (latitudeValue === '' || longitudeValue === '' || !Number.isFinite(latitude) || !Number.isFinite(longitude)
            || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            location = null;
            locationRecordedAtInput.value = '';
            locationDisplay.textContent = 'Belum ada lokasi yang valid dipilih.';
            return;
        }

        location = {
            latitude,
            longitude,
            location_recorded_at: new Date().toISOString(),
        };
        locationRecordedAtInput.value = location.location_recorded_at;
        locationDisplay.textContent = `Lokasi dipilih: ${latitude.toFixed(7)}, ${longitude.toFixed(7)}.`;
    };
    const setBusy = value => {
        busy = value;
        document.querySelectorAll('[data-checkpoint-form] button, [data-observation-form] button').forEach(button => {
            button.disabled = value;
        });
        form?.setAttribute('aria-busy', String(value));
    };
    const showError = error => {
        message.textContent = error instanceof TypeError || error.name === 'TimeoutError' || error instanceof SyntaxError
            ? 'Penyimpanan belum dapat dikonfirmasi. Periksa koneksi dan coba lagi; catatan tetap ada.'
            : error.message;
        message.scrollIntoView({ block: 'nearest' });
    };
    const save = async (target, data) => {
        if (!navigator.onLine) throw new Error('Koneksi terputus. Data belum tersimpan; sambungkan internet dan coba lagi.');
        const response = await fetch(target.action, {
            method: 'POST', body: data, headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(30000),
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(response.status === 422
                ? Object.values(result.errors || {}).flat().join('\n') || result.message
                : response.status >= 500 ? 'Server gagal mengonfirmasi penyimpanan. Coba lagi; catatan tetap ada.'
                    : result.message || 'Penyimpanan ditolak. Muat ulang untuk memeriksa status patroli.');
        }
        return result;
    };

    form?.addEventListener('location-picker:change', updateLocationFromPicker);

    checkpointsCard?.addEventListener('submit', async event => {
        const target = event.target.closest('[data-checkpoint-form]');
        if (!target) return;
        event.preventDefault();
        if (busy) return;
        setBusy(true);
        message.textContent = 'Mengambil GPS aktual untuk checkpoint…';
        try {
            const point = await captureDeviceLocation();
            const data = new FormData(target);
            data.set('latitude', point.latitude);
            data.set('longitude', point.longitude);
            message.textContent = 'Menyimpan kunjungan checkpoint…';
            const result = await save(target, data);
            checkpointsCard.innerHTML = result.checkpoints_html;
            const remaining = document.querySelector('[data-unvisited-count]');
            if (remaining) remaining.textContent = `${result.unvisited_count} checkpoint`;
            const distance = document.querySelector('[data-patrol-distance]');
            if (distance && result.total_distance_km !== null) distance.textContent = `${result.total_distance_km} km`;
            window.dispatchEvent(new CustomEvent('patrol-checkpoint-recorded', { detail: { id: result.checkpoint.id } }));
            message.textContent = result.message;
        } catch (error) {
            showError(error);
        } finally {
            setBusy(false);
        }
    });

    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        setBusy(true);
        try {
            if (!location) {
                if (latitudeInput.value.trim() !== '' || longitudeInput.value.trim() !== '') {
                    throw new Error('Lokasi kejadian belum ditentukan. Ambil lokasi GPS atau pilih titik pada peta.');
                }
                message.textContent = 'Mengambil lokasi GPS…';
                showLocation(await captureDeviceLocation());
            }
            message.textContent = 'Menyimpan observasi…';
            const result = await save(form, new FormData(form));
            fieldRecording.querySelector('[data-observations-list]').innerHTML = result.observations_html;
            form.reset();
            form.elements.namedItem('client_uuid').value = result.next_client_uuid;
            showLocation(location);
            message.textContent = result.message;
        } catch (error) {
            showError(error);
        } finally {
            setBusy(false);
        }
    });
}
