import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const setupDefaultMarkerIcons = () => {
    delete L.Icon.Default.prototype._getIconUrl;

    L.Icon.Default.mergeOptions({
        iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).toString(),
        iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).toString(),
        shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).toString(),
    });
};

const updateStatusText = (statusElement, message, tone = 'default') => {
    if (!statusElement) {
        return;
    }

    statusElement.textContent = message;
    statusElement.className = 'mt-2 text-xs';

    if (tone === 'success') {
        statusElement.classList.add('text-emerald-600');
    } else if (tone === 'error') {
        statusElement.classList.add('text-red-600');
    } else {
        statusElement.classList.add('text-slate-500');
    }
};

const normalizeCoordinate = (value, fallback) => {
    const numericValue = Number.parseFloat(value);

    return Number.isFinite(numericValue) ? numericValue : fallback;
};

const readCoordinate = (value) => {
    if (value.trim() === '') {
        return null;
    }

    const coordinate = Number(value);

    return Number.isFinite(coordinate) ? coordinate : null;
};

const hasValidCoordinates = (latitude, longitude) => (
    Number.isFinite(latitude)
    && Number.isFinite(longitude)
    && latitude >= -90
    && latitude <= 90
    && longitude >= -180
    && longitude <= 180
);

const dispatchLocationChange = (picker, latitude, longitude) => {
    picker.dispatchEvent(new CustomEvent('location-picker:change', {
        bubbles: true,
        detail: { latitude, longitude },
    }));
};

const syncMarkerFromInputs = (picker, map, marker, latInput, lngInput, statusElement, updateStatus = true) => {
    const latitude = readCoordinate(latInput.value);
    const longitude = readCoordinate(lngInput.value);

    if (!hasValidCoordinates(latitude, longitude)) {
        return marker;
    }

    const position = [latitude, longitude];
    if (marker) {
        marker.setLatLng(position);
    } else {
        marker = L.marker(position, { draggable: true }).addTo(map);
        marker.on('dragend', () => {
            const currentPosition = marker.getLatLng();
            latInput.value = currentPosition.lat.toFixed(7);
            lngInput.value = currentPosition.lng.toFixed(7);
            dispatchLocationChange(picker, latInput.value, lngInput.value);
            updateStatusText(statusElement, 'Pin dipindahkan pada peta.', 'success');
        });
    }

    map.panTo(position, { animate: false });
    if (updateStatus && statusElement && statusElement.dataset.locationStatus !== 'manual') {
        updateStatusText(statusElement, 'Lokasi dipilih dari koordinat yang diinput.', 'success');
    }

    return marker;
};

const initializePicker = (picker) => {
    if (!picker) {
        console.warn('Location picker: no picker container found.');
        return;
    }

    if (!L || !L.map || !L.tileLayer) {
        console.warn('Location picker: Leaflet is not available on this page.');
        return;
    }

    if (!picker.querySelector('[data-location-map]')) {
        console.warn('Location picker: map container not found inside picker.', picker);
        return;
    }

    const mapElement = picker.querySelector('[data-location-map]');
    const latInput = picker.querySelector('[data-location-lat]');
    const lngInput = picker.querySelector('[data-location-lng]');
    const accuracyInput = picker.querySelector('[data-location-accuracy]');
    const statusElement = picker.querySelector('[data-location-status]');
    const getLocationButton = picker.querySelector('[data-location-btn-get]');

    if (!latInput || !lngInput || !mapElement) {
        return;
    }

    const defaultLat = normalizeCoordinate(picker.dataset.defaultLat, -6.917464);
    const defaultLng = normalizeCoordinate(picker.dataset.defaultLng, 107.619123);
    const inputLat = readCoordinate(latInput.value);
    const inputLng = readCoordinate(lngInput.value);
    const hasInitialCoordinates = hasValidCoordinates(inputLat, inputLng);
    const initialLat = hasInitialCoordinates ? inputLat : defaultLat;
    const initialLng = hasInitialCoordinates ? inputLng : defaultLng;

    const map = L.map(mapElement, {
        scrollWheelZoom: true,
        zoomControl: true,
    }).setView([initialLat, initialLng], 13);

    picker.__locationMap = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);
    let marker = null;

    const updateCoordinateFields = (latitude, longitude, accuracy = null) => {
        const normalizedLat = Number.parseFloat(latitude);
        const normalizedLng = Number.parseFloat(longitude);

        if (Number.isFinite(normalizedLat) && Number.isFinite(normalizedLng)) {
            latInput.value = normalizedLat.toFixed(7);
            lngInput.value = normalizedLng.toFixed(7);
            marker = syncMarkerFromInputs(picker, map, marker, latInput, lngInput, statusElement);
            picker.__locationMarker = marker;
            dispatchLocationChange(picker, latInput.value, lngInput.value);
        }

        if (accuracyInput && accuracy !== null && accuracy !== undefined && Number.isFinite(Number(accuracy))) {
            accuracyInput.value = Number(accuracy).toFixed(2);
        }
    };

    const updateMapFromManualInput = () => {
        marker = syncMarkerFromInputs(picker, map, marker, latInput, lngInput, statusElement);
        picker.__locationMarker = marker;
        dispatchLocationChange(picker, latInput.value, lngInput.value);
    };

    latInput.addEventListener('input', updateMapFromManualInput);
    lngInput.addEventListener('input', updateMapFromManualInput);
    latInput.addEventListener('change', updateMapFromManualInput);
    lngInput.addEventListener('change', updateMapFromManualInput);

    map.on('click', (event) => {
        updateCoordinateFields(event.latlng.lat, event.latlng.lng);
        updateStatusText(statusElement, 'Lokasi terpilih dari peta.', 'success');
    });

    if (getLocationButton) {
        getLocationButton.addEventListener('click', () => {
            if (!navigator.geolocation) {
                updateStatusText(statusElement, 'Geolocation tidak didukung pada browser Anda.', 'error');
                return;
            }

            updateStatusText(statusElement, 'Mendeteksi titik koordinat perangkat...', 'default');

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    const accuracy = position.coords.accuracy;

                    map.setView([latitude, longitude], 16, { animate: true });
                    updateCoordinateFields(latitude, longitude, accuracy);
                    updateStatusText(
                        statusElement,
                        `Koordinat berhasil diambil (Akurasi: ±${Math.round(accuracy)} meter).`,
                        'success',
                    );
                },
                (error) => {
                    updateStatusText(statusElement, `Gagal mengambil lokasi: ${error.message}`, 'error');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                },
            );
        });
    }

    if (hasInitialCoordinates) {
        marker = syncMarkerFromInputs(picker, map, marker, latInput, lngInput, statusElement, false);
        picker.__locationMarker = marker;
    }

    map.invalidateSize();
    const parentDetails = picker.closest('details');
    parentDetails?.addEventListener('toggle', () => {
        if (parentDetails.open) {
            requestAnimationFrame(() => map.invalidateSize());
        }
    });
};

const initializeLocationPickers = () => {
    if (!L || !L.map || !L.tileLayer) {
        console.warn('Location picker: Leaflet failed to initialize before the page script ran.');
        return;
    }

    setupDefaultMarkerIcons();

    const pickers = document.querySelectorAll('[data-location-picker]');
    if (pickers.length === 0) {
        console.warn('Location picker: no location picker element found on this page.');
        return;
    }

    pickers.forEach((picker) => {
        initializePicker(picker);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLocationPickers);
} else {
    initializeLocationPickers();
}
