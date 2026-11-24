# PRD FG-27 — Pengelompokan Otomatis Insiden

## 1. User Story

Sebagai Admin/Forest Manager, saya ingin laporan insiden yang berdekatan dikelompokkan otomatis berdasarkan jarak, sehingga sistem dapat menampilkan centroid dan jumlah laporan setiap kelompok.

## 2. Scope

- Menghitung jarak menggunakan formula Haversine.
- Mengelompokkan insiden berdasarkan radius konfigurasi.
- Menjalankan proses melalui queue job.
- Menghitung centroid latitude/longitude.
- Menghitung jumlah laporan tiap kelompok.
- Menampilkan detail kelompok kepada Admin.

Di luar scope: prediksi AI, klasifikasi otomatis jenis insiden, dan perubahan data laporan sumber.

## 3. Data dan Relasi

Data sumber berasal dari `incidents`, khususnya `latitude`, `longitude`, `incident_type_id`, `forest_area_id`, dan `created_at`.

Jika belum tersedia, buat tabel `incident_clusters` dengan field `centroid_latitude`, `centroid_longitude`, `report_count`, `radius_km`, `status`, dan `processed_at`. Relasi laporan terhadap kelompok tidak boleh membuat laporan duplikat.

## 4. Flow

1. Laporan insiden tersimpan dengan koordinat valid.
2. Sistem mengirim queue job clustering.
3. Job menghitung jarak antar laporan menggunakan Haversine.
4. Laporan dalam radius yang sama dimasukkan ke kelompok.
5. Jika tidak ada kelompok yang cocok, sistem membuat kelompok baru.
6. Sistem menghitung ulang centroid dan jumlah laporan.
7. Admin melihat hasil pengelompokan.

## 5. Aturan Bisnis

- Latitude harus berada pada rentang -90 sampai 90.
- Longitude harus berada pada rentang -180 sampai 180.
- Radius clustering menggunakan konfigurasi, bukan angka hard-code di banyak tempat.
- Laporan tanpa koordinat valid tidak diproses.
- Pemrosesan ulang harus idempotent.
- Job gagal harus tercatat dan dapat dijalankan ulang.

## 6. Acceptance Criteria

| ID | Kriteria |
|---|---|
| AC27-01 | Jarak antar laporan dihitung menggunakan Haversine. |
| AC27-02 | Radius pengelompokan dapat dikonfigurasi. |
| AC27-03 | Laporan dalam radius yang sama masuk kelompok yang sama. |
| AC27-04 | Laporan di luar radius tidak digabungkan. |
| AC27-05 | Kelompok baru dibuat jika tidak ada kelompok yang cocok. |
| AC27-06 | Centroid dihitung dari koordinat laporan anggota kelompok. |
| AC27-07 | Jumlah laporan sesuai dengan relasi aktual. |
| AC27-08 | Proses dijalankan melalui queue job. |
| AC27-09 | Job gagal tercatat dan dapat di-retry. |
| AC27-10 | Retry tidak menghasilkan kelompok duplikat. |

## 7. Pengujian

Gunakan Unit/Feature Test untuk formula Haversine, radius, centroid, queue, validasi koordinat, dan idempotensi. Gunakan Browser Test untuk daftar/detail kelompok setelah UI tersedia.

Perintah contoh:

```bash
php artisan test --filter=Pbi27IncidentClusteringTest
```

