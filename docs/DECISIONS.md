# Architectural Decision Records (ADR) - ForestGuard

Dokumen ini mencatat keputusan teknis, arsitektur, dan konsensus konvensi yang diambil pada pengerjaan PBI-01 dan PBI-02 untuk menjadi pedoman bagi seluruh anggota tim pengembang ForestGuard.

---

## ADR-01: Autentikasi Manual Ramping Tanpa Package Pihak Ketiga
- **Konteks**: Sistem membutuhkan autentikasi (login, logout, registrasi warga) dengan alur spesifik (tanpa fitur "ingat saya", tanpa reset password mandiri, tanpa verifikasi email, redirect dinamis berbasis peran, pencegahan user enumeration).
- **Keputusan**: Autentikasi dibangun secara manual menggunakan fitur inti Laravel (`Auth::guard()`, `LoginRequest`, `RegisterRequest`, Action classes `AttemptLogin`, `RegisterCitizen`) tanpa menginstal Laravel Breeze, Jetstream, Filament, ataupun Livewire.
- **Konsekuensi**: Kode tetap bersih, ramping, tidak memiliki dead code (kode mati yang tidak terpakai), serta sepenuhnya dapat dikontrol dan diuji secara akurat.

---

## ADR-02: Skema Tabel `notifications` Kustom & Catatan untuk PBI-17
- **Konteks**: ERD FINAL ForestGuard menetapkan skema tabel `notifications` dengan kolom:
  - `id` (UUID, Primary Key)
  - `user_id` (Foreign Key ke `users`)
  - `type` (string, jenis notifikasi)
  - `data` (JSON, payload)
  - `read_at` (timestamp, nullable)
  - `created_at` (timestamp)
  Struktur ini berbeda dari tabel notifikasi bawaan Laravel yang memakai `notifiable_type` dan `notifiable_id` (polymorphic).
- **Keputusan**: Dibuat migrasi dan model Eloquent kustom `App\Models\AppNotification` yang menggunakan trait `HasUuids` dan relasi langsung `user()`.
- **Catatan untuk Pengembang PBI-17**: PBI-17 yang mengimplementasikan pengiriman notifikasi harus menggunakan model `AppNotification` atau mengimplementasikan custom notification channel Laravel (misalnya `DatabaseChannel` kustom) yang menuliskan data ke tabel `notifications` sesuai skema ini, bukan menggunakan skema polymorphic bawaan Laravel.

---

## ADR-03: Aturan Timestamps, SoftDeletes, dan Remember Token
- **Konteks**: ERD FINAL secara presisi menentukan kolom waktu dan status soft-delete pada setiap tabel. Penambahan timestamps bawaan yang tidak tertera pada ERD dilarang keras.
- **Keputusan**:
  1. Tabel tanpa timestamps (`created_at`/`updated_at`) wajib menyetel `public $timestamps = false;` di modelnya.
  2. Tabel yang hanya memiliki `created_at` menyetel `const UPDATED_AT = null;` atau diatur lewat database default `useCurrent()`.
  3. Tabel dengan kolom waktu spesifik (seperti `scheduled_at`, `started_at`, `ended_at`, `visited_at`, `recorded_at`, `observed_at`, `occurred_at`, `resolved_at`, `changed_at`, `assigned_at`, `captured_at`, `last_attempt_at`, `synced_at`) diisi secara eksplisit atau default database.
  4. `SoftDeletes` HANYA diaktifkan pada tabel yang memiliki kolom `deleted_at`: `users`, `incident_types`, `forest_areas`, dan `checkpoints`.
  5. Pada model `User`: tidak ada kolom `remember_token`. Method `getRememberTokenName()` di-override agar mengembalikan string kosong `''` sehingga sesi autentikasi Laravel tidak berusaha membaca atau menulis kolom remember token.

---

## ADR-04: Integritas Relasi Eksklusif pada Tabel `evidences` (CHECK Constraint)
- **Konteks**: Sebuah bukti foto/media lapangan (`evidences`) harus terikat ke TEPAT SATU entitas induk: laporan insiden (`incident_id`), observasi patroli (`observation_id`), atau catatan pembaruan investigasi (`update_id`).
- **Keputusan**: Menerapkan perlindungan ganda (defense in depth):
  1. **Tingkat Basis Data**: Migration `create_evidences_table` menambahkan CHECK constraint `evidences_exactly_one_parent` melalui `DB::statement`:
     ```sql
     CHECK (
         (CASE WHEN incident_id IS NOT NULL THEN 1 ELSE 0 END)
         + (CASE WHEN observation_id IS NOT NULL THEN 1 ELSE 0 END)
         + (CASE WHEN update_id IS NOT NULL THEN 1 ELSE 0 END)
         = 1
     )
     ```
  2. **Tingkat Model Eloquent**: Model `App\Models\Evidence` memanfaatkan lifecycle hook `saving` untuk memvalidasi bahwa jumlah parent yang terisi tepat 1, dan melempar `InvalidArgumentException` jika melanggar.
- **Pengecualian Driver**: Pada SQLite diuji keamanan model, sedangkan pada MySQL/MariaDB constraint database aktif sepenuhnya.

---

## ADR-05: Isolasi Database Pengujian (Dusk & PHPUnit)
- **Konteks**: Pengujian otomatis (Unit, Feature, dan End-to-End browser Dusk) tidak boleh merusak atau bergantung pada basis data pengembangan (`forestguard`).
- **Keputusan**:
  - **Pengembangan (Dev)**: Menggunakan database `forestguard`.
  - **Feature Tests (PHPUnit)**: Menggunakan database `forestguard_test` (dikonfigurasi pada `phpunit.xml` dengan trait `RefreshDatabase`).
  - **Browser Tests (Laravel Dusk)**: Menggunakan database `forestguard_dusk` (dikonfigurasi pada `.env.dusk.local`).

---

## ADR-06: Keamanan Mass Assignment dan Hak Akses Registrasi
- **Konteks**: Parameter `role_id` pada request registrasi tidak boleh dimanipulasi oleh klien (misalnya injeksi `role_id=1` untuk mendapatkan hak akses admin).
- **Keputusan**:
  1. Atribut `role_id` sengaja TIDAK dimasukkan ke dalam `$fillable` model `User`.
  2. Action `RegisterCitizen` mengambil ID peran `citizen` langsung dari server/database dan menetapkannya secara eksplisit sebelum penyimpanan data.
  3. Setiap user baru dari form registrasi memiliki `is_active = true`.
