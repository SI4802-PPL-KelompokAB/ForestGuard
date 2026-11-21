// PBI-04: Pemilihan titik pusat kawasan hutan melalui peta Jawa Barat.

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const westJavaBounds = L.latLngBounds(
    [-7.90, 106.35],
    [-5.90, 108.85],
);

const setupMarkerIcons = () => {
    delete L.Icon.Default.prototype._getIconUrl;

    L.Icon.Default.mergeOptions({
        iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).toString(),
        iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).toString(),
        shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).toString(),
    });
};

const readCoordinate = (value) => {
    const coordinate = Number.parseFloat(value);

    return Number.isFinite(coordinate) ? coordinate : null;
};

const isValidCoordinate = (latitude, longitude) => (
    latitude !== null
    && longitude !== null
    && westJavaBounds.contains([latitude, longitude])
);

const initializePicker = (picker) => {
    const mapElement = picker.querySelector('[data-forest-area-map]');
    const latitudeInput = picker.querySelector('[data-forest-area-latitude-input]');
    const longitudeInput = picker.querySelector('[data-forest-area-longitude-input]');
    const latitudeOutput = picker.querySelector('[data-forest-area-latitude]');
    const longitudeOutput = picker.querySelector('[data-forest-area-longitude]');
    const statusElement = picker.querySelector('[data-forest-area-map-status]');

    if (!mapElement || !latitudeInput || !longitudeInput) {
        return;
    }

    const defaultLatitude = readCoordinate(picker.dataset.defaultLat) ?? -6.917464;
    const defaultLongitude = readCoordinate(picker.dataset.defaultLng) ?? 107.619123;
    const existingLatitude = readCoordinate(latitudeInput.value);
    const existingLongitude = readCoordinate(longitudeInput.value);
    const initialPosition = isValidCoordinate(existingLatitude, existingLongitude)
        ? [existingLatitude, existingLongitude]
        : [defaultLatitude, defaultLongitude];

    const map = L.map(mapElement, {
        maxBounds: westJavaBounds,
        maxBoundsViscosity: 0.7,
        scrollWheelZoom: true,
    }).setView(initialPosition, existingLatitude !== null ? 13 : 9);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    let marker = null;

    const setLocation = (latitude, longitude, message = 'Titik pusat berhasil dipilih dari peta.') => {
        if (!isValidCoordinate(latitude, longitude)) {
            statusElement.textContent = 'Pilih titik yang masih berada di wilayah Jawa Barat.';
            statusElement.className = 'mt-2 text-xs text-red-600';
            return;
        }

        const formattedLatitude = latitude.toFixed(7);
        const formattedLongitude = longitude.toFixed(7);
        latitudeInput.value = formattedLatitude;
        longitudeInput.value = formattedLongitude;
        latitudeOutput.textContent = formattedLatitude;
        longitudeOutput.textContent = formattedLongitude;
        statusElement.textContent = message;
        statusElement.className = 'mt-2 text-xs text-emerald-600';

        if (marker === null) {
            marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const position = marker.getLatLng();
                setLocation(position.lat, position.lng, 'Pin dipindahkan pada peta.');
            });
        } else {
            marker.setLatLng([latitude, longitude]);
        }

        map.panTo([latitude, longitude], { animate: false });
    };

    if (isValidCoordinate(existingLatitude, existingLongitude)) {
        setLocation(existingLatitude, existingLongitude, 'Lokasi kawasan tersimpan ditampilkan pada peta.');
    }

    map.on('click', (event) => setLocation(event.latlng.lat, event.latlng.lng));
    map.invalidateSize();
};

const initializePickers = () => {
    setupMarkerIcons();
    document.querySelectorAll('[data-forest-area-map-picker]').forEach(initializePicker);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePickers);
} else {
    initializePickers();
}
