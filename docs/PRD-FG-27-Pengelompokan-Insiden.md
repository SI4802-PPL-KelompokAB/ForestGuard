# PRD FG-27 — Pengelompokan Otomatis Insiden

## 1. User Story

Sebagai Admin / Forest Manager, saya ingin laporan insiden yang berdekatan dikelompokkan berdasarkan lokasi menggunakan perhitungan Haversine, sehingga laporan pada area yang sama dapat dianalisis sebagai satu kelompok dan ditampilkan melalui tabel serta peta.

## 2. Scope

- Menghitung jarak antar laporan menggunakan formula Haversine.
- Mengelompokkan laporan berdasarkan radius konfigurasi.
- Menjalankan proses clustering melalui queue job.
- Menghitung centroid latitude dan longitude setiap cluster.
- Menghitung jumlah laporan pada setiap cluster.
- Menampilkan hasil cluster dalam tabel.
- Menampilkan laporan, centroid, warna cluster, dan radius pada peta.
- Membatasi halaman clustering hanya untuk Admin / Forest Manager.

Di luar scope: prediksi AI, klasifikasi otomatis jenis insiden, perubahan isi laporan sumber, dan dashboard analitik lanjutan.

## 3. Data dan Relasi

Data sumber berasal dari tabel `incidents`, terutama kolom `latitude`, `longitude`, `incident_type_id`, `forest_area_id`, dan `created_at`.

### Tabel `incident_clusters`

| Kolom | Keterangan |
|---|---|
| `id` | Identitas cluster |
| `fingerprint` | Identitas unik kombinasi anggota cluster |
| `centroid_latitude` | Latitude titik pusat cluster |
| `centroid_longitude` | Longitude titik pusat cluster |
| `report_count` | Jumlah laporan anggota cluster |
| `radius_km` | Radius pengelompokan |
| `status` | Status proses cluster |
| `processed_at` | Waktu proses selesai |
| `created_at` | Waktu cluster dibuat |
| `updated_at` | Waktu cluster diperbarui |

### Tabel `incident_cluster_incident`

Tabel pivot untuk menghubungkan laporan dengan cluster melalui `incident_cluster_id` dan `incident_id`. Relasi ini digunakan agar proses clustering tidak mengubah data utama pada tabel `incidents`.

### Tabel pendukung

- `jobs`: menyimpan antrean `ClusterIncidentsJob`.
- `failed_jobs`: mencatat queue job yang gagal diproses.
- `incident_types`: menampilkan jenis laporan pada daftar anggota cluster.
- `forest_areas`: menampilkan kawasan laporan.

## 4. Flow

1. Citizen atau Ranger membuat laporan insiden dengan koordinat.
2. Admin membuka halaman Pengelompokan Insiden.
3. Admin menekan tombol **Proses clustering**.
4. Sistem memasukkan `ClusterIncidentsJob` ke queue.
5. Queue worker menjalankan proses clustering.
6. Sistem mengambil laporan dengan koordinat valid.
7. Sistem menghitung jarak antar laporan menggunakan Haversine.
8. Laporan yang berada dalam radius yang sama dimasukkan ke cluster yang sama.
9. Jika tidak ditemukan cluster yang sesuai, sistem membuat cluster baru.
10. Sistem menghitung centroid dan jumlah laporan.
11. Sistem menyimpan relasi laporan dan cluster.
12. Admin melihat hasilnya dalam tabel dan peta.

## 5. Aturan Bisnis

- Latitude harus berada pada rentang `-90` sampai `90`.
- Longitude harus berada pada rentang `-180` sampai `180`.
- Radius clustering menggunakan konfigurasi sistem, dengan nilai default `5 km`.
- Laporan tanpa koordinat valid tidak diproses.
- Laporan di luar radius tidak digabungkan ke cluster yang sama.
- Centroid dihitung dari rata-rata koordinat laporan anggota.
- `report_count` harus sesuai dengan jumlah relasi laporan pada cluster.
- Pemrosesan ulang harus bersifat idempotent.
- Queue job yang gagal dicatat oleh mekanisme `failed_jobs` Laravel dan dapat dijalankan ulang.
- Proses clustering tidak mengubah isi utama laporan insiden.

## 6. Mockup & Halaman yang Melingkupi PBI

| UI | Halaman | Route Name / URL | Komponen Antarmuka |
|---|---|---|---|
| 1 | Pengelompokan Insiden | `admin.incident-clusters.index`<br>`/admin/pengelompokan-insiden` | Tombol proses clustering, peta, marker laporan, marker centroid, lingkaran radius, legenda warna, tabel cluster, status, jumlah laporan, dan laporan anggota |

### Keadaan UI

- Empty state jika belum ada hasil clustering.
- Status cluster seperti `processed`.
- Informasi centroid dan radius.
- Informasi jumlah laporan.
- Marker laporan dengan warna berdasarkan cluster.
- Popup marker berisi kode dan koordinat laporan.
- Popup centroid berisi nomor cluster dan koordinat pusat.
- Halaman hanya dapat diakses Admin.
- Tampilan responsif pada desktop dan mobile.

## 7. Kriteria Penerimaan

| ID | Kriteria |
|---|---|
| AC27-01 | Sistem menghitung jarak antar laporan menggunakan Haversine. |
| AC27-02 | Radius clustering dapat dikonfigurasi. |
| AC27-03 | Laporan dalam radius yang sama masuk ke cluster yang sama. |
| AC27-04 | Laporan di luar radius tidak digabungkan. |
| AC27-05 | Cluster baru dibuat jika tidak ada cluster terdekat. |
| AC27-06 | Centroid dihitung berdasarkan koordinat laporan anggota. |
| AC27-07 | Jumlah laporan sesuai dengan relasi aktual. |
| AC27-08 | Proses clustering dijalankan melalui queue job. |
| AC27-09 | Koordinat invalid tidak ikut dikelompokkan. |
| AC27-10 | Pemrosesan ulang tidak membuat cluster duplikat. |
| AC27-11 | Admin dapat melihat tabel dan peta hasil clustering. |
| AC27-12 | Peta menampilkan marker laporan, centroid, warna cluster, dan radius. |
| AC27-13 | User non-admin tidak dapat mengakses halaman clustering. |
| AC27-14 | Data utama laporan insiden tidak berubah akibat proses clustering. |

## 8. Test Case

### Feature Test

| Test Case | Skenario | Method |
|---|---|---|
| TC-PBI27-01 | Koordinat valid dan invalid diproses sesuai aturan | `test_invalid_coordinates_are_not_clustered` |
| TC-PBI27-02 | Jarak dihitung menggunakan Haversine | `test_haversine_returns_distance_in_kilometers` |
| TC-PBI27-03 | Laporan dalam radius masuk cluster yang sama | `test_incidents_within_radius_share_a_cluster_and_centroid_is_calculated` |
| TC-PBI27-04 | Laporan di luar radius dipisahkan | `test_incidents_within_radius_share_a_cluster_and_centroid_is_calculated` |
| TC-PBI27-05 | Cluster baru dibuat jika tidak ada cluster sesuai | `test_incidents_within_radius_share_a_cluster_and_centroid_is_calculated` |
| TC-PBI27-06 | Centroid dihitung | `test_incidents_within_radius_share_a_cluster_and_centroid_is_calculated` |
| TC-PBI27-07 | Jumlah laporan diperbarui | `test_incidents_within_radius_share_a_cluster_and_centroid_is_calculated` |
| TC-PBI27-08 | Proses dikirim ke queue | `test_clustering_is_dispatched_to_queue` |
| TC-PBI27-09 | Job gagal dicatat oleh queue Laravel | Verifikasi melalui `failed_jobs` / queue worker |
| TC-PBI27-10 | Pemrosesan ulang tidak membuat duplikasi | `test_reprocessing_is_idempotent_and_does_not_duplicate_clusters` |

### Browser Test

| Test Case | Skenario | Method |
|---|---|---|
| TC-PBI27-11 | Admin melihat tabel dan peta hasil clustering | `test_admin_can_view_incident_clusters` |
| TC-PBI27-12 | Ranger dan Citizen ditolak dari halaman clustering | `test_non_admin_cannot_access_incident_clusters` |

### File dan Perintah Pengujian

File Feature Test:

```text
tests/Feature/Pbi27IncidentClusteringTest.php
```

File Browser Test:

```text
tests/Browser/Pbi27IncidentClusteringTest.php
```

Perintah Feature Test:

```powershell
php artisan test --filter=Pbi27IncidentClusteringTest
```

Perintah Browser Test:

```powershell
$env:DUSK_HEADLESS_DISABLED="true"; php artisan dusk --filter=Pbi27IncidentClusteringTest
```

Queue worker:

```powershell
php artisan queue:work
```
