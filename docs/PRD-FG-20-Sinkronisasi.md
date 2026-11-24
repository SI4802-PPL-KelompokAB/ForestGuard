# PRD FG-20 — Sinkronisasi Data Offline

## 1. User Story

Sebagai Ranger/Citizen, saya ingin data yang dibuat ketika offline disimpan sementara dan dikirim ketika koneksi kembali tersedia, sehingga data tidak hilang atau terkirim ganda.

## 2. Scope

- Menyimpan data offline ke antrean lokal.
- Mengirim ulang data ketika koneksi tersedia.
- Mencatat status `pending`, `synced`, dan `failed`.
- Menyediakan retry untuk data gagal.
- Mencegah duplikasi menggunakan `client_uuid`.
- Menampilkan status sinkronisasi milik pengguna.

Di luar scope: perubahan fitur laporan, algoritma clustering, push notification, dan perubahan aturan bisnis PBI lain.

## 3. Data dan Kontrak

Gunakan tabel `sync_records` dan model `SyncRecord` yang sudah tersedia. Field utama: `user_id`, `client_uuid`, `entity_type`, `entity_id`, `payload`, `status`, `attempts`, `last_attempt_at`, `synced_at`, dan `error_message`.

`client_uuid` wajib unik. Pengiriman ulang dengan UUID yang sama harus idempotent dan tidak membuat record tujuan ganda.

## 4. Flow

1. Data dibuat ketika offline.
2. Data disimpan sebagai `pending`.
3. Koneksi kembali tersedia.
4. Queue mengirim payload ke endpoint tujuan.
5. Sukses mengubah status menjadi `synced`; gagal menjadi `failed`.
6. Pengguna dapat melakukan retry terhadap data gagal.

## 5. Acceptance Criteria

| ID | Kriteria |
|---|---|
| AC20-01 | Data offline masuk antrean dengan status `pending`. |
| AC20-02 | Setiap data memiliki `client_uuid` unik. |
| AC20-03 | Data dikirim ketika koneksi tersedia. |
| AC20-04 | Data sukses memiliki status `synced` dan `synced_at`. |
| AC20-05 | Data gagal memiliki status `failed` dan `error_message`. |
| AC20-06 | Retry menambah `attempts` dan tidak menggandakan data. |
| AC20-07 | Pengguna hanya dapat melihat antreannya sendiri. |

## 6. Pengujian

Uji Feature/Unit minimal mencakup penyimpanan pending, perubahan status, retry, idempotensi, ownership, dan failed job. Browser test hanya dibuat untuk halaman Sync Center jika halaman tersebut sudah tersedia.

Perintah contoh:

```bash
php artisan test --filter=Pbi20SynchronizationTest
```

