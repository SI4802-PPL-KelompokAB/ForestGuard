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
    const allBounds = [];

    // Render planned checkpoints on map if available
    if (mapContainer.dataset.checkpoints) {
        try {
            const checkpoints = JSON.parse(mapContainer.dataset.checkpoints);
            if (Array.isArray(checkpoints)) {
                checkpoints.forEach(cp => {
                    const lat = Number(cp.lat);
                    const lng = Number(cp.lng);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        allBounds.push([lat, lng]);
                        const isVisited = Boolean(cp.visited);

                        const markerHtml = `
                            <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold text-white shadow-md border-2 border-white ${isVisited ? 'bg-emerald-600' : 'bg-amber-500'}">
                                ${cp.sequence || '•'}
                            </div>
                        `;

                        const customIcon = L.divIcon({
                            html: markerHtml,
                            className: 'custom-cp-marker',
                            iconSize: [28, 28],
                            iconAnchor: [14, 14],
                            popupAnchor: [0, -14],
                        });

                        L.marker([lat, lng], { icon: customIcon })
                            .addTo(map)
                            .bindPopup(`
                                <div class="p-1 text-xs">
                                    <strong class="text-sm font-semibold text-slate-800">${cp.name}</strong>
                                    <span class="text-slate-400">(${cp.code})</span><br>
                                    <span class="text-slate-500">Urutan Checkpoint #${cp.sequence}</span><br>
                                    <span class="inline-block mt-1.5 px-2 py-0.5 rounded text-[11px] font-medium ${isVisited ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
                                        ${isVisited ? '✓ Sudah Dikunjungi' : 'Belum Dikunjungi'}
                                    </span>
                                </div>
                            `);
                    }
                });
            }
        } catch (e) {
            console.warn('Gagal memuat checkpoint pada peta:', e);
        }
    }

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
                    latLngs.forEach(coord => allBounds.push(coord));

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

    // Adjust zoom if bounds exist
    if (allBounds.length > 0) {
        map.fitBounds(L.latLngBounds(allBounds), { padding: [35, 35] });
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
                let msg = error.message;
                if (error.code === 1) {
                    msg = 'Akses lokasi/GPS ditolak di browser.';
                } else if (error.code === 2) {
                    msg = 'Sinyal GPS tidak tersedia pada perangkat.';
                } else if (error.code === 3) {
                    msg = 'Pencarian sinyal GPS timeout.';
                }
                console.warn('Geolocation error:', msg);
                updateGpsStatus(`GPS bermasalah: ${msg}`, false);
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
