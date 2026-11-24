# PRD FG-24 — Ekspor Laporan PDF

## 1. User Story

Sebagai Admin/Forest Manager, saya ingin mengekspor laporan berdasarkan filter tertentu ke format PDF, sehingga laporan dapat digunakan untuk dokumentasi dan pelaporan.

## 2. Scope

- Menampilkan daftar laporan.
- Memfilter berdasarkan periode, status, jenis insiden, dan kawasan.
- Menghasilkan dan mengunduh PDF.
- Menjaga isi PDF sesuai hasil filter.
- Menampilkan empty state dan validasi filter.

Di luar scope: mengubah atau menghapus laporan, dashboard analitik, dan perubahan data sumber.

## 3. Data dan Relasi

Sumber data utama adalah `incidents`, dengan relasi ke `users`, `incident_types`, `forest_areas`, dan bila diperlukan `status_histories` serta `evidences`.

Proses ekspor bersifat read-only dan tidak boleh memodifikasi data sumber.

## 4. Flow

1. Admin membuka halaman laporan.
2. Sistem menampilkan data dan filter.
3. Admin memilih filter.
4. Sistem menampilkan hasil filter.
5. Admin menekan Ekspor PDF.
6. Sistem memvalidasi filter dan membentuk PDF.
7. File diunduh dengan nama informatif.

## 5. Acceptance Criteria

| ID | Kriteria |
|---|---|
| AC24-01 | Admin dapat membuka halaman laporan. |
| AC24-02 | Filter periode, status, jenis insiden, dan kawasan berfungsi. |
| AC24-03 | Isi PDF sesuai hasil filter. |
| AC24-04 | PDF memuat kode, tanggal, jenis, kawasan, status, dan deskripsi laporan. |
| AC24-05 | Filter tanpa hasil menampilkan empty state. |
| AC24-06 | Periode tidak valid ditolak dengan validasi. |
| AC24-07 | Ekspor tidak mengubah data database. |
| AC24-08 | Guest, Ranger, dan Citizen tidak dapat menggunakan fitur Admin. |

## 6. Pengujian

Test minimal mencakup halaman laporan, setiap filter, ekspor PDF, kesesuaian isi PDF, empty state, validasi tanggal, otorisasi, dan integritas data.

Perintah contoh:

```bash
php artisan test --filter=Pbi24ExportReportTest
```

