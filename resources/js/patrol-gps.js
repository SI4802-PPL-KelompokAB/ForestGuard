/**
 * PBI-07: Pelacakan Rute GPS Selama Patroli (FR-07)
 * Merekam titik koordinat GPS perangkat Ranger secara berkala saat patroli berlangsung,
 * menghitung jarak akumulasi, dan memvisualisasikan rute di peta Leaflet.
 */

const patrolExecution = document.querySelector('[data-patrol-execution]');
const mapContainer = document.getElementById('patrol-map');
const distanceDisplay = document.querySelector('[data-patrol-distance]');
const gpsIndicator = document.querySelector('[data-gps-indicator]');

if (mapContainer) {
    initPatrolTrackingAndMap();
}

async function initPatrolTrackingAndMap() {
    const isRanger = Boolean(patrolExecution);
    const isRunning = patrolExecution?.dataset.status === 'Berlangsung';
    const tracksUrl = mapContainer.dataset.patrolTracksUrl;
    const recordTrackUrl = mapContainer.dataset.recordTrackUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value;

    // Load Leaflet dynamically if not already present
    await ensureLeafletLoaded();

    if (!window.L) {
        console.warn('Leaflet could not be loaded.');
        return;
    }

    // Default center (Bandung / Indonesia region if empty)
    const map = L.map(mapContainer).setView([-6.917464, 107.619123], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    const polyline = L.polyline([], {
        color: '#15803d',
        weight: 4,
        opacity: 0.85,
        smoothFactor: 1,
    }).addTo(map);

    let currentMarker = null;

    // Fetch existing tracks
    if (tracksUrl) {
        try {
            const response = await fetch(tracksUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (response.ok) {
                const data = await response.json();
                if (Array.isArray(data.tracks) && data.tracks.length > 0) {
                    const latLngs = data.tracks.map(t => [Number(t.latitude), Number(t.longitude)]);
                    polyline.setLatLngs(latLngs);
                    map.fitBounds(polyline.getBounds(), { padding: [30, 30] });

                    // Marker on start point
                    L.circleMarker(latLngs[0], {
                        radius: 6,
                        color: '#047857',
                        fillColor: '#10b981',
                        fillOpacity: 1,
                    }).addTo(map).bindPopup('Titik Awal');

                    // Marker on latest point
                    const latest = latLngs[latLngs.length - 1];
                    currentMarker = L.marker(latest).addTo(map).bindPopup('Posisi Terakhir');
                }
            }
        } catch (e) {
            console.warn('Gagal memuat track sebelumnya:', e);
        }
    }

    // If patrol is in progress and user is ranger, start tracking GPS
    if (isRunning && recordTrackUrl && navigator.geolocation) {
        let lastSentLat = null;
        let lastSentLon = null;
        let pendingQueue = JSON.parse(localStorage.getItem('pending_patrol_tracks_' + recordTrackUrl) || '[]');

        const updateGpsStatus = (text, isSuccess) => {
            if (gpsIndicator) {
                gpsIndicator.innerHTML = `
                    <span class="inline-block h-2 w-2 rounded-full ${isSuccess ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'}"></span>
                    <span>${text}</span>
                `;
            }
        };

        const flushPendingQueue = async () => {
            if (pendingQueue.length === 0 || !navigator.onLine) return;
            try {
                const response = await fetch(recordTrackUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ tracks: pendingQueue }),
                });
                if (response.ok) {
                    pendingQueue = [];
                    localStorage.removeItem('pending_patrol_tracks_' + recordTrackUrl);
                }
            } catch {
                // Keep queue for next attempt
            }
        };

        // Try flushing pending queue on startup & online
        window.addEventListener('online', flushPendingQueue);
        flushPendingQueue();

        const sendTrackPoint = async (lat, lon, recordedAt) => {
            const trackItem = {
                client_uuid: crypto.randomUUID ? crypto.randomUUID() : 'track-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9),
                latitude: Number(lat.toFixed(7)),
                longitude: Number(lon.toFixed(7)),
                recorded_at: recordedAt || new Date().toISOString(),
            };

            // Update UI Map immediately
            polyline.addLatLng([trackItem.latitude, trackItem.longitude]);
            if (!currentMarker) {
                currentMarker = L.marker([trackItem.latitude, trackItem.longitude]).addTo(map).bindPopup('Posisi Terkini');
            } else {
                currentMarker.setLatLng([trackItem.latitude, trackItem.longitude]);
            }
            map.panTo([trackItem.latitude, trackItem.longitude]);

            // Attempt sending to server
            if (!navigator.onLine) {
                pendingQueue.push(trackItem);
                localStorage.setItem('pending_patrol_tracks_' + recordTrackUrl, JSON.stringify(pendingQueue));
                updateGpsStatus('GPS disimpan lokal (Offline)', false);
                return;
            }

            try {
                const response = await fetch(recordTrackUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(trackItem),
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.total_distance_km !== null && distanceDisplay) {
                        distanceDisplay.textContent = `${result.total_distance_km} km`;
                    }
                    updateGpsStatus('GPS Aktif & Terhubung', true);
                    flushPendingQueue();
                } else {
                    pendingQueue.push(trackItem);
                    localStorage.setItem('pending_patrol_tracks_' + recordTrackUrl, JSON.stringify(pendingQueue));
                    updateGpsStatus('GPS disimpan antrean lokal', false);
                }
            } catch {
                pendingQueue.push(trackItem);
                localStorage.setItem('pending_patrol_tracks_' + recordTrackUrl, JSON.stringify(pendingQueue));
                updateGpsStatus('GPS disimpan antrean lokal', false);
            }
        };

        // Start GPS Watcher
        updateGpsStatus('Mencari sinyal GPS...', false);
        const watchId = navigator.geolocation.watchPosition(
            position => {
                const { latitude, longitude, accuracy } = position.coords;

                // Threshold movement to avoid excessive noise (approx > 5 meters)
                if (lastSentLat === null || Math.abs(latitude - lastSentLat) > 0.00005 || Math.abs(longitude - lastSentLon) > 0.00005) {
                    lastSentLat = latitude;
                    lastSentLon = longitude;
                    sendTrackPoint(latitude, longitude);
                } else {
                    updateGpsStatus(`GPS Aktif (Akurasi: ±${Math.round(accuracy)}m)`, true);
                }
            },
            error => {
                console.warn('Geolocation error:', error.message);
                updateGpsStatus(`Sinyal GPS lemah: ${error.message}`, false);
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 5000,
            }
        );

        // Clear watcher when page is unloaded
        window.addEventListener('beforeunload', () => {
            navigator.geolocation.clearWatch(watchId);
        });
    }
}

function ensureLeafletLoaded() {
    if (window.L) return Promise.resolve();

    return new Promise(resolve => {
        // Inject CSS if missing
        if (!document.querySelector('link[href*="leaflet"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        }

        // Inject JS if missing
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve();
        script.onerror = () => {
            console.error('Failed to load Leaflet from CDN.');
            resolve();
        };
        document.head.appendChild(script);
    });
}
