let latestPosition = null;

export function rememberDevicePosition(position) {
    const latitude = Number(position.coords.latitude);
    const longitude = Number(position.coords.longitude);
    const capturedAt = new Date(position.timestamp);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)
        || Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || Number.isNaN(capturedAt.getTime())) {
        throw new Error('Lokasi GPS perangkat tidak valid. Coba ambil lokasi lagi.');
    }
    latestPosition = { latitude, longitude, location_recorded_at: capturedAt.toISOString() };
    window.dispatchEvent(new CustomEvent('patrol-device-location', { detail: latestPosition }));
    return latestPosition;
}

export function latestDeviceLocation() {
    return latestPosition;
}

export function captureDeviceLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Perangkat tidak mendukung GPS. Gunakan perangkat dengan layanan lokasi.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(position => {
            try {
                resolve(rememberDevicePosition(position));
            } catch (error) {
                reject(error);
            }
        }, error => {
            const messages = {
                1: 'Akses GPS ditolak. Izinkan lokasi di browser, lalu coba lagi.',
                2: 'Lokasi GPS tidak tersedia. Aktifkan layanan lokasi, lalu coba lagi.',
                3: 'Pengambilan GPS melewati batas waktu. Coba lagi.',
            };
            reject(new Error(messages[error.code] || 'GPS gagal. Coba ambil lokasi lagi.'));
        }, { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 });
    });
}
