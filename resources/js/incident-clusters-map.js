import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const mapElement = document.querySelector('#incident-cluster-map');
const dataElement = document.querySelector('#incident-cluster-data');

if (mapElement) {
    const clusters = JSON.parse(dataElement?.textContent || '[]');
    const map = L.map(mapElement).setView([-6.917464, 107.619123], 9);
    const bounds = [];
    const colors = ['#166534', '#2563eb', '#dc2626', '#d97706', '#7c3aed', '#0891b2'];

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));

    clusters.forEach((cluster, index) => {
        const color = colors[index % colors.length];
        const center = [Number(cluster.centroid_latitude), Number(cluster.centroid_longitude)];
        const radius = Number(cluster.radius_km) * 1000;

        if (!Number.isFinite(center[0]) || !Number.isFinite(center[1])) {
            return;
        }

        L.circle(center, {
            color,
            fillColor: color,
            fillOpacity: 0.08,
            radius,
            weight: 2,
        }).addTo(map).bindPopup(`<strong>Cluster #${escapeHtml(cluster.id)}</strong><br>${escapeHtml(cluster.report_count)} laporan<br>Radius ${escapeHtml(cluster.radius_km)} km`);

        L.circleMarker(center, {
            color: '#ffffff',
            fillColor: color,
            fillOpacity: 1,
            radius: 10,
            weight: 3,
        }).addTo(map).bindPopup(`<strong>Centroid Cluster #${escapeHtml(cluster.id)}</strong><br>${center[0].toFixed(7)}, ${center[1].toFixed(7)}`);

        bounds.push(center);

        (cluster.incidents || []).forEach((incident) => {
            const point = [Number(incident.latitude), Number(incident.longitude)];

            if (!Number.isFinite(point[0]) || !Number.isFinite(point[1])) {
                return;
            }

            L.circleMarker(point, {
                color,
                fillColor: color,
                fillOpacity: 0.85,
                radius: 7,
                weight: 2,
            }).addTo(map).bindPopup(`<strong>${escapeHtml(incident.code)}</strong><br>Anggota Cluster #${escapeHtml(cluster.id)}<br>${point[0].toFixed(7)}, ${point[1].toFixed(7)}`);

            bounds.push(point);
        });
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [30, 30] });
    }

    window.setTimeout(() => map.invalidateSize(), 0);
}
