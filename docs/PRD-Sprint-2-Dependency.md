# PRD Sprint 2 — Dependency FG-20, FG-24, dan FG-27

## 1. Pemetaan Dependency

| PBI | Dependency utama | Dampak |
|---|---|---|
| FG-20 | FG-18/19, endpoint payload, `client_uuid`, queue | Implementasi dapat dimulai dengan `sync_records`, tetapi integrasi final mengikuti kontrak offline. |
| FG-24 | FG-10/11/12 dan relasi laporan | Ekspor dapat dibuat menggunakan data laporan yang tersedia; perubahan sumber harus dikomunikasikan. |
| FG-27 | FG-10, FG-25/26, koordinat insiden, queue | Algoritma dapat dibuat sekarang; integrasi final membutuhkan koordinat valid dan aturan lokasi. |

## 2. Kontrak yang Wajib Disepakati

| Area | Kesepakatan |
|---|---|
| Payload | Nama field, tipe data, dan format tanggal. |
| Identitas data | Penggunaan `client_uuid` dan aturan idempotensi. |
| Koordinat | Nama field latitude/longitude dan satuan derajat desimal. |
| Queue | Nama job, status, retry, dan penanganan failed job. |
| Database | Migration tidak boleh menghapus data development bersama. |
| Otorisasi | Role yang boleh membaca atau menjalankan setiap fitur. |

## 3. Urutan Pengerjaan

1. Audit migration, model, route, dan data yang sudah tersedia.
2. Implementasikan algoritma dan test FG-27 secara terisolasi.
3. Implementasikan query dan template PDF FG-24.
4. Implementasikan antrean dan retry FG-20 berdasarkan kontrak FG-18/19.
5. Lakukan integrasi dan pengujian gabungan.

## 4. Aturan Kolaborasi

- Setiap PBI menggunakan branch dan PR terpisah.
- Jangan mengubah migration atau field milik PBI lain tanpa komunikasi.
- Perubahan kontrak payload, koordinat, atau status harus dicatat di PR.
- Jalankan test paling spesifik sebelum membuat PR.
- Jangan menggunakan database development utama untuk test yang melakukan reset migration.
