# PRD: FG-3 Manajemen Pengguna & FG-4 Manajemen Data Master

**Produk:** ForestGuard (Sustainable Forest Patrol & Forest Protection System)  
**Dokumen:** Product Requirements Document  
**Versi:** 1.1 (selaras Proposal PPL Kelompok A-B)  
**Tanggal:** 26 Oktober 2026  
**Status:** Acuan verifikasi implementasi (kode sudah dikerjakan; dokumen ini menjadi dasar pengecekan terhadap proposal)  
**Pemilik tugas:** Mahasiswa pengembang FG-3 & FG-4  
**Dasar isu Linear:**
- **FG-3** — Manajemen pengguna *(In Progress)* → proposal **PBI-03**, **FR-14**, **UC-11**
- **FG-4** — Manajemen data master (area, checkpoint, tipe insiden, risiko, status; soft delete) *(To Do)* → proposal **PBI-04**, **FR-15**, **UC-12**

---

## 0. Riwayat revisi dan keselarasan dengan proposal

Versi 1.1 menyelaraskan PRD v1.0 dengan Proposal_PPL_Kelompok_A-B (bab 3 kebutuhan, use case UC-11/UC-12, bab 4.5 mockup, bab 5 Definition of Done).

### 0.1 Perubahan dari v1.0

| ID | Perubahan | Alasan (rujukan proposal) |
|---|---|---|
| C-01 | Pembuatan akun: Admin mengisi **nama, email, peran** saja. Sistem membuat **kata sandi sementara** acak dan **mengirimnya lewat email**. Field password dan konfirmasi dihapus dari form buat. | UC-11 langkah 2–3 |
| C-02 | Tes **Laravel Dusk wajib** untuk FG-3 dan FG-4 (sebelumnya "opsional"). Menjadi syarat selesai. | DoD butir (c), tujuan khusus no. 6, bab 5 |
| C-03 | Daftar pengguna dan daftar master **wajib paginasi**. | NFR-02, NFR-11 |
| C-04 | Label tombol tambah pengguna: **"Tambah akun"**. | UC-11 langkah 2 |
| C-05 | **Status kasus read-only**: hanya dilihat, tidak bisa ditambah, dinonaktifkan, diubah urutannya. Opsi edit `sequence`/`is_terminal` di v1.0 dihapus. | UC-12 A2 |
| C-06 | Tingkat risiko: field "bobot" di proposal dipetakan ke kolom `sort_order`. | UC-12 langkah 3 |
| C-07 | Data nonaktif/terhapus **tidak muncul di pilihan form baru**, tetapi **tetap tampil di riwayat**. Ditambahkan sebagai aturan dan kriteria penerimaan. | UC-11 A3, UC-12 A3 |
| C-08 | Daftar master menampilkan kolom **status aktif**. | UC-12 langkah 2 |
| C-09 | Operasi tulis memakai **transaksi DB** dan **pencatatan error**. | NFR-08 |
| C-10 | Ditambahkan **matriks keterlacakan** dan **DoD proposal** (PR ditinjau, kriteria terpenuhi, Dusk lulus, dapat didemokan). | Bab 5 |
| C-11 | Ditambahkan **ADR baru** untuk pengiriman email (mengubah sebagian ADR-01). | Konsekuensi C-01 |

### 0.2 Keputusan terbuka yang perlu disepakati tim

| # | Hal | Posisi PRD v1.1 | Tindakan |
|---|---|---|---|
| Q-1 | Email kata sandi sementara bertentangan dengan non-tujuan v1.0 (tanpa email) dan ADR-01 | Ikuti proposal | Catat ADR baru di `docs/DECISIONS.md` |
| Q-2 | "Bobot" tingkat risiko di UC-12 | Dipetakan ke `sort_order` | Cek ERD revisi; jika ada kolom bobot terpisah, sesuaikan |
| Q-3 | Penanggung jawab PBI-03/PBI-04 di tabel peran proposal tidak konsisten (deskripsi peran dan daftar PBI berbeda orang) | Tidak mempengaruhi isi PRD | Rapikan tabel peran di proposal sebelum dikumpulkan |

---

## 1. Ringkasan eksekutif

ForestGuard sudah punya fondasi aplikasi (skema database lengkap, autentikasi, RBAC, seeder data master), tetapi **Admin belum bisa mengelola pengguna maupun data master lewat UI**. FG-3 dan FG-4 mengisi celah itu: layar CRUD khusus peran `admin`, tanpa mengubah skema ERD yang sudah dikunci di PBI-01, dan tanpa membangun modul patroli/insiden/GIS (itu PBI berikutnya).

**Hasil yang diharapkan:** Admin dapat membuat/menonaktifkan/menghapus lunak akun staf (akun baru menerima kata sandi sementara lewat email), serta merawat kamus data kawasan, checkpoint, tipe insiden, tingkat risiko, dan melihat status kasus, sehingga modul lapangan nanti memakai data yang konsisten.

---

## 2. Konteks produk

ForestGuard adalah aplikasi web Laravel (MVC + Blade + Tailwind, tanpa React/Vue/Livewire/Inertia/Filament) untuk patroli konservasi dan pelaporan perusakan hutan (SDG 15).

Tiga peran:

| Peran | Kode `roles.name` | Tanggung jawab terkait dokumen ini |
|---|---|---|
| Forest Manager / Admin | `admin` | Satu-satunya aktor FG-3 dan FG-4 |
| Ranger | `ranger` | Pengguna yang dibuat/dikelola Admin; tidak mengakses CRUD master |
| Citizen (Warga) | `citizen` | Registrasi mandiri sudah ada (FR-03); Admin dapat melihat/menonaktifkan, **bukan** mendaftarkan warga sebagai alur utama |

---

## 3. Audit kondisi saat ini (fondasi yang sudah ada)

### 3.1 Sudah selesai (PBI-01 Setup & Database)

- Stack: Laravel 11/12/13, PHP 8.2+, Blade components, Tailwind via Vite, MySQL, PHPUnit, Dusk, locale `id`, timezone `Asia/Jakarta`.
- Migrasi 18 tabel domain sesuai ERD, plus cache/jobs/sessions.
- Model Eloquent untuk seluruh entitas, termasuk relasi.
- Seeder idempotent:
  - 3 peran: `admin`, `ranger`, `citizen`
  - 8 status kasus (Reported … Resolved/Rejected)
  - 6 tipe insiden
  - 4 tingkat risiko (Rendah → Kritis)
  - 3 kawasan hutan + 13 checkpoint
  - 5 akun dev (1 admin, 2 ranger, 2 warga), kata sandi `Password123!`
- Soft delete **sudah di model + kolom `deleted_at`** untuk: `users`, `incident_types`, `forest_areas`, `checkpoints`.
- `risk_levels` memakai `is_active` (bukan `deleted_at`).
- `case_statuses` memakai `sequence` + `is_terminal` (tanpa `deleted_at`, tanpa `is_active`).
- `roles` hanya 3 baris tetap; tidak ada CRUD peran.
- Constraint FK hampir semua `restrictOnDelete` (hapus keras gagal jika masih dipakai; NFR-10).
- ADR tercatat di `docs/DECISIONS.md` (auth manual, timestamps, SoftDeletes, mass assignment).

### 3.2 Sudah selesai (PBI-02 Auth & RBAC)

| Metode | Path | Siapa | Fungsi |
|---|---|---|---|
| GET/POST | `/login` | Tamu | Masuk |
| GET/POST | `/register` | Tamu | Daftar **warga saja** |
| POST | `/logout` | Auth | Keluar |
| GET | `/citizen/laporan` | `citizen` aktif | Placeholder beranda warga |
| GET | `/ranger` | `ranger` aktif | Placeholder beranda ranger |
| GET | `/admin/dashboard` | `admin` aktif | Placeholder dasbor |

Perilaku keamanan yang **wajib dipertahankan**:

- Redirect setelah login sesuai peran (`RoleHome`).
- Registrasi publik **selalu** peran `citizen`; injeksi `role_id` diabaikan (`role_id` tidak `$fillable`).
- Akun `is_active = false` atau soft-deleted tidak bisa login; sesi aktif diputus middleware `active`.
- Pesan login gagal generik (anti enumerasi email).
- Rate limit 5 percobaan login.
- Cross-role → HTTP 403; tamu → redirect login.
- Tidak ada remember-me, reset password mandiri, atau verifikasi email (ADR-01). **Catatan v1.1:** ADR-01 dilonggarkan hanya untuk pengiriman email kata sandi sementara saat Admin membuat akun (lihat 7.3 dan ADR baru).

### 3.3 Belum ada (celah FG-3 / FG-4)

Controller, Form Request, Action, view, rute, dan tes untuk: daftar / tambah / ubah / nonaktifkan / hapus lunak / pulihkan pengguna; CRUD kawasan, checkpoint, tipe insiden; pengelolaan tingkat risiko; tampilan status kasus; filter, pencarian, dan restore data soft-deleted.

### 3.4 Implikasi untuk tugas ini

| Lapisan | Status | Tindakan FG-3/FG-4 |
|---|---|---|
| Tabel & model master | Ada | **Reuse**, jangan buat migrasi baru kecuali keputusan produk memaksa (lihat 7.2) |
| Seeder data awal | Ada | Tetap; CRUD harus kompatibel dengan `updateOrCreate` seeder |
| Auth / middleware `auth`+`active`+`role:admin` | Ada | Pasang di grup rute `/admin/...` baru |
| UI layout + komponen `card`, `button`, `input`, `alert` | Ada | Perluas navigasi admin + halaman CRUD |
| Tes PBI-01 / PBI-02 | Ada | Jangan dipecahkan; tambah tes `Pbi03…` / `Pbi04…` dan Dusk |
| Konfigurasi mail Laravel | Perlu diatur | Driver lewat `.env`; tanpa secret di kode |

---

## 4. Masalah yang diselesaikan

Tanpa FG-3/FG-4, Admin tidak bisa:

1. Menambahkan ranger/admin baru tanpa menyentuh seeder/database.
2. Menonaktifkan akun yang disalahgunakan (meski flag `is_active` dan middleware sudah siap).
3. Menambah kawasan/checkpoint baru sebelum modul patroli hidup.
4. Menyesuaikan kamus tipe insiden / risiko tanpa migrasi manual, atau melihat daftar status kasus yang berlaku.

Modul PBI berikutnya (penugasan patroli, laporan warga, verifikasi) **bergantung** pada data ini.

---

## 5. Tujuan & non-tujuan

### 5.1 Tujuan

- Admin mengelola siklus hidup akun (buat, ubah, aktif/nonaktif, hapus lunak, pulihkan) lewat UI berbahasa Indonesia.
- Akun baru menerima kata sandi sementara lewat email (UC-11).
- Admin mengelola kamus data: kawasan, checkpoint, tipe insiden, tingkat risiko; dan melihat status kasus (read-only).
- Soft delete dipakai **hanya** pada tabel yang sudah punya `deleted_at` (selaras ADR-03).
- Semua aksi dilindungi RBAC: hanya `admin` aktif.
- Feature test **dan** Dusk menutup alur utama, validasi, otorisasi, dan aturan hapus.

### 5.2 Non-tujuan (jangan dikerjakan di FG-3/FG-4)

- Patroli, checkpoint visit, GPS track, observasi
- Laporan insiden warga, verifikasi, penugasan, investigasi
- Dasbor GIS / peta interaktif (cukup input lat/lng dan JSON boundary sederhana)
- Notifikasi dalam aplikasi (FR-02), sync offline/PWA, unggah bukti
- CRUD tabel `roles`
- Registrasi ranger/admin lewat halaman publik `/register`
- Reset password mandiri ("lupa password"), verifikasi email, 2FA
- Paksa ganti password saat login pertama (usulan backlog, bukan bagian proposal)
- CRUD status kasus (tambah/ubah/hapus)
- API JSON / mobile native
- Mengubah skema timestamps atau menambah kolom di luar keputusan di bagian 7.2

---

## 6. Pengguna & use case

### 6.1 Aktor

- **Admin (primer):** seluruh use case di bawah.
- **Ranger / Citizen (negatif):** tidak boleh mengakses rute `/admin/pengguna*` dan `/admin/master*`.
- **Sistem:** middleware `active` memutus sesi jika Admin menonaktifkan akun yang sedang login; sistem mengirim email kata sandi sementara.

### 6.2 FG-3 — Manajemen pengguna (FR-14, UC-11)

| ID | Use case | Deskripsi |
|---|---|---|
| UC-03-01 | Lihat daftar pengguna | Tabel berpaginasi: nama, email, peran, status aktif, terhapus/tidak. Filter peran + status. Pencarian nama/email. |
| UC-03-02 | Tambah akun staf | Admin mengisi **nama, email, peran** lalu menekan **"Tambah akun"**. Sistem memvalidasi, membuat akun (aktif), membuat kata sandi sementara, dan **mengirimkannya ke email** pengguna. Peran: `admin` atau `ranger` (opsional `citizen` untuk data uji). |
| UC-03-03 | Ubah pengguna | Ubah nama, peran, status aktif. Email **tidak diubah** (unik + histori laporan). Password opsional (kosong = tidak ganti); dipakai Admin sebagai jalur pemulihan bila email gagal terkirim. |
| UC-03-04 | Nonaktifkan / aktifkan | Toggle `is_active`. Akun nonaktif tidak bisa login (dijamin PBI-02). Riwayat laporan/patroli tetap tersimpan. |
| UC-03-05 | Soft delete | Set `deleted_at`. User hilang dari daftar default, tidak bisa login. Riwayat tetap tersimpan. |
| UC-03-06 | Pulihkan | Clear `deleted_at` (default aktifkan kembali, atau biarkan Admin memilih). |
| UC-03-07 | Proteksi diri | Admin **tidak boleh** menonaktifkan, menghapus, atau menurunkan peran akunnya sendiri (UC-11 A2). |

**Alur alternatif (dari UC-11):**
- A1: email sudah terdaftar (termasuk milik akun soft-deleted) → pembuatan ditolak; arahkan Admin memulihkan akun lama.
- A2: Admin tidak dapat menonaktifkan akunnya sendiri.
- A3: akun nonaktif tidak dapat login, tetapi laporan, patroli, dan riwayatnya tetap tersimpan.

**Catatan registrasi warga:** alur `/register` tetap satu-satunya cara publik menjadi `citizen`. FG-3 tidak mengganti itu.

### 6.3 FG-4 — Manajemen data master (FR-15, UC-12)

| ID | Entitas | Create | Read | Update | Delete | Catatan skema |
|---|---|---|---|---|---|---|
| UC-04-A | Kawasan (`forest_areas`) | Ya | Ya | Ya | Soft delete + restore | name, description, area_ha, center_lat, center_lng, boundary (GeoJSON opsional) |
| UC-04-B | Checkpoint | Ya | Ya | Ya | Soft delete + restore | Wajib terikat `forest_area_id`; `code` unik |
| UC-04-C | Tipe insiden | Ya | Ya | Ya | Soft delete + restore | `name` unik |
| UC-04-D | Tingkat risiko | Ya | Ya | Ya | **Nonaktifkan** (`is_active=false`), bukan soft delete | `name` unik, `sort_order` (= "bobot" di proposal) |
| UC-04-E | Status kasus | **Tidak** | Ya | **Tidak** | **Tidak** | **Read-only** (UC-12 A2) |

Status kasus adalah kamus alur bisnis (8 status seeder menjadi kontrak tes PBI-01 dan dasar aturan transisi status di NFR-10). Karena itu halaman status hanya **menampilkan** daftar; tidak ada tombol tambah, ubah, nonaktif, hapus, maupun ubah urutan.

**Alur alternatif (dari UC-12):**
- A1: nama kosong atau luas bukan angka → input ditolak.
- A2: status kasus bersifat tetap (lihat di atas).
- A3: data nonaktif/terhapus tidak muncul pada pilihan form baru, tetapi tetap ditampilkan dalam riwayat.

---

## 7. Keputusan produk yang mengikat implementasi

### 7.1 Soft delete vs nonaktif (wajib selaras ADR-03)

> SoftDeletes **hanya** pada tabel yang punya `deleted_at`: `users`, `incident_types`, `forest_areas`, `checkpoints`.

FR-15 menyebut "penghapusan memakai soft delete" untuk seluruh master. **Itu tidak boleh diterjemahkan menjadi migrasi `deleted_at` baru** tanpa kesepakatan tim, karena bertentangan dengan ADR-03 dan tes skema.

| Entitas | Cara "menghilangkan dari pilihan aktif" |
|---|---|
| User | `is_active` (blokir login) **dan** `deleted_at` (hapus lunak dari daftar) |
| Forest area | `deleted_at` |
| Checkpoint | `deleted_at` |
| Incident type | `deleted_at` |
| Risk level | `is_active = false` (tetap di DB, tidak muncul di dropdown form baru) |
| Case status | Tidak dihapus, tidak diubah (read-only) |

**Perilaku pilihan form vs riwayat (UC-12 A3, UC-11 A3):**
- Sediakan scope/helper (mis. `active()`) pada model master agar modul berikutnya mengisi dropdown hanya dari data aktif.
- Relasi untuk tampilan riwayat memakai `withTrashed()` (atau tidak memfilter `is_active`) agar data lama tetap terbaca.

Checkpoint milik kawasan yang di-soft-delete: **ikut disembunyikan di UI**. Rekomendasi: soft-delete checkpoint anak saat kawasan dihapus (dalam satu transaksi), dan pulihkan bersama saat kawasan dipulihkan, agar patroli tidak menunjuk titik "yatim".

### 7.2 Penanganan jika dosen menuntut soft delete harfiah untuk risiko/status

1. **Preferensi A (dipakai):** jelaskan di demo bahwa ERD tidak punya `deleted_at` di tabel itu; yang setara adalah `is_active` (risiko) dan larangan hapus/ubah (status).
2. **Dilarang tanpa rapat:** menambah `deleted_at` atau kolom baru ke tabel yang ADR-03 nyatakan tidak boleh.

### 7.3 Aturan bisnis pengguna

- **Kata sandi sementara (UC-11 langkah 3):**
  - Dibuat server-side secara acak (mis. `Str::password(12)`), disimpan **ter-hash**, tidak pernah disimpan atau ditampilkan polos di UI.
  - Dikirim ke email pengguna lewat Laravel Mail (`Mailable` + view email berbahasa Indonesia) berisi nama, tautan login, dan kata sandi sementara.
  - Pembuatan akun dalam **transaksi DB**; email dikirim **setelah commit**.
  - Jika pengiriman gagal: akun **tetap dibuat**, error dicatat di log (NFR-08), Admin melihat peringatan "Akun dibuat, tetapi email gagal dikirim", dan dapat menetapkan password lewat form Ubah.
  - Driver mail dari `.env` (`MAIL_MAILER` dll.); tanpa secret di kode. Driver `log` hanya untuk pengembangan, karena isi email (termasuk kata sandi sementara) tertulis di log; jangan dipakai di produksi.
- Password yang diset Admin lewat form Ubah: min 8 karakter, konfirmasi cocok, mengikuti aturan `RegisterRequest`.
- Email unik di tingkat database (termasuk baris soft-deleted). Membuat user baru dengan email yang masih di-soft-delete harus ditolak; pulihkan akun lama.
- Peran yang boleh di-assign Admin: `admin`, `ranger`, `citizen` (divalidasi `Rule::in` terhadap nama peran).
- Default user baru dari Admin: `is_active = true`, `deleted_at = null`.
- `role_id` **hanya** di-set di Action server-side (bukan mass assignment dari request mentah), konsisten ADR-06.
- Semua form memakai `@csrf` (NFR-03).

### 7.4 Aturan bisnis master

- `checkpoints.code` unik (termasuk yang soft-deleted) → kode tidak didaur ulang sebelum restore.
- `incident_types.name` dan `risk_levels.name` unik.
- `forest_areas.name` wajib; `area_ha` harus angka dan > 0; lat/lng dalam rentang valid.
- `boundary` opsional; jika diisi harus JSON objek GeoJSON Polygon sederhana (seeder sudah memakai format itu).
- Tidak hard-delete baris yang masih direferensikan (FK `restrictOnDelete`). Soft delete tidak memicu FK, jadi aman secara DB; UI tetap menampilkan peringatan "data masih bisa dipakai histori lama".
- Jangan nonaktifkan tingkat risiko jika itu satu-satunya yang aktif (minimal 1 risiko aktif).
- Tidak ada rute tulis (POST/PUT/PATCH/DELETE) untuk status kasus; 8 status seeder dianggap **data referensi sistem**.
- Semua operasi tulis dalam transaksi DB; kegagalan dicatat di log.

### 7.5 UI / UX

- Bahasa Indonesia, termasuk pesan validasi (NFR-01).
- Layout `layouts.app` + sidebar admin: Dasbor, Pengguna, Data Master (sub: Kawasan, Checkpoint, Tipe Insiden, Risiko, Status).
- Komponen yang sudah ada: `x-card`, `x-button`, `x-input`, `x-alert`.
- Form POST klasik (bukan SPA). Method spoofing `PUT`/`DELETE` Laravel.
- Tombol tambah pengguna berlabel **"Tambah akun"**.
- Daftar pengguna dan daftar master **berpaginasi** (mis. 15 baris per halaman; `withQueryString()` agar filter tetap) (NFR-02, NFR-11).
- Daftar master menampilkan kolom **status aktif** (UC-12 langkah 2).
- Konfirmasi sebelum soft delete / nonaktif.
- Flash `session('status')` untuk sukses; flash peringatan untuk email gagal terkirim.
- Daftar default **menyembunyikan** soft-deleted; toggle "Tampilkan terhapus" untuk restore.
- Responsif, mengutamakan layar ponsel; bottom nav admin boleh hanya tautan utama (Dasbor + Pengguna + Master).

### 7.6 Arsitektur kode (mengikuti PBI-02)

```
routes/web.php          grup middleware auth, active, role:admin
app/Http/Controllers/Admin/...
app/Http/Requests/Admin/...
app/Actions/Admin/...   (satu aksi tulis per operasi, seperti AttemptLogin)
app/Mail/TemporaryPasswordMail.php
resources/views/admin/...
resources/views/emails/temporary-password.blade.php
tests/Feature/Pbi03UserManagementTest.php
tests/Feature/Pbi04MasterDataTest.php
tests/Browser/Pbi03UserManagementTest.php   (Dusk)
tests/Browser/Pbi04MasterDataTest.php       (Dusk)
```

Jangan pasang Breeze/Filament/Livewire/Inertia.

### 7.7 ADR baru (tambahkan ke `docs/DECISIONS.md`)

> **ADR-0X: Email kata sandi sementara untuk akun yang dibuat Admin.**  
> Konteks: UC-11 mensyaratkan sistem mengirim kata sandi sementara lewat email; ADR-01 sebelumnya menolak reset password dan verifikasi email.  
> Keputusan: email hanya dipakai untuk satu tujuan, yaitu mengirim kata sandi sementara saat Admin membuat akun. Reset password mandiri, verifikasi email, dan paksa-ganti-password tetap di luar cakupan MVP.  
> Konsekuensi: perlu konfigurasi mail per lingkungan; kegagalan kirim tidak membatalkan pembuatan akun.

---

## 8. Spesifikasi layar (tingkat PRD, bukan wireframe piksel)

### 8.1 FG-3

**`GET /admin/pengguna`**
- Kolom: Nama, Email, Peran, Aktif, Aksi. Berpaginasi.
- Filter: semua / admin / ranger / citizen; aktif / nonaktif / terhapus. Pencarian nama/email.
- Tombol **"Tambah akun"**.
- Aksi: Ubah, Nonaktifkan/Aktifkan, Hapus, Pulihkan.

**`GET /admin/pengguna/buat`** dan **`POST /admin/pengguna`**
- Field: nama, email, peran. **Tanpa** field password.
- Sukses: kembali ke daftar dengan flash "Akun dibuat, kata sandi sementara dikirim ke email."

**`GET|PUT /admin/pengguna/{user}`**
- Field: nama, peran, is_active, password opsional (+ konfirmasi).
- Email ditampilkan read-only.

**`DELETE /admin/pengguna/{user}`** — soft delete.  
**`POST /admin/pengguna/{user}/pulihkan`** — restore.  
**`POST /admin/pengguna/{user}/status`** — toggle aktif (boleh digabung ke form ubah).

### 8.2 FG-4

Prefix: `/admin/master/...`

| Resource | Indeks | Form field pokok | Tulis? |
|---|---|---|---|
| Kawasan | `/admin/master/kawasan` | name, description, area_ha, center_lat, center_lng, boundary (textarea JSON) | Ya |
| Checkpoint | `/admin/master/checkpoint` | forest_area_id (select kawasan aktif), code, name, latitude, longitude | Ya |
| Tipe insiden | `/admin/master/tipe-insiden` | name | Ya |
| Risiko | `/admin/master/risiko` | name, sort_order (bobot), is_active | Ya (tanpa hapus) |
| Status | `/admin/master/status` | — (hanya tampilan: name, sequence, is_terminal) | **Tidak** |

Kawasan, checkpoint, tipe insiden: tombol Hapus (lunak) + Pulihkan. Risiko: toggle aktif/nonaktif. Status: hanya `GET`.

---

## 9. Kriteria penerimaan (Definition of Done)

### FG-3

1. Admin login dapat membuka daftar pengguna (berpaginasi) dan melihat 5 akun seeder.
2. Admin menekan **"Tambah akun"**, mengisi nama, email, peran `ranger`; akun dibuat dan email berisi kata sandi sementara terkirim; ranger dapat login ke `/ranger` dengan kata sandi tersebut.
3. Admin dapat membuat admin kedua dengan alur yang sama.
4. Warga/ranger yang membuka URL pengguna mendapat 403; tamu diarahkan login.
5. Nonaktifkan user → login ditolak dengan pesan yang sama seperti PBI-02; riwayat data tetap ada.
6. Soft delete user → tidak di daftar default, tidak bisa login, bisa dipulihkan.
7. Admin tidak dapat menghapus/nonaktifkan/menurunkan peran dirinya sendiri (validasi server + tes).
8. Registrasi publik tetap hanya citizen; tes PBI-02 masih hijau.
9. Email duplikat (termasuk yang terhapus lunak) ditolak.
10. Jika email gagal terkirim, akun tetap dibuat dan Admin mendapat peringatan; error tercatat di log.
11. Kata sandi sementara tersimpan ter-hash dan tidak tampil di UI.

### FG-4

1. Admin CRUD kawasan; data seeder tetap tampil.
2. Admin CRUD checkpoint terikat kawasan; kode duplikat ditolak.
3. Admin CRUD tipe insiden; soft delete menyembunyikan dari daftar default; restore mengembalikan.
4. Admin dapat menonaktifkan tingkat risiko; tidak ada tombol hard delete; risiko aktif terakhir tidak bisa dinonaktifkan.
5. Admin dapat **melihat** 8 status seeder; tidak ada tombol/rute tambah, ubah, urutan, nonaktif, atau hapus (request tulis ditolak 404/405).
6. Ranger/citizen 403 ke semua rute master.
7. Soft delete kawasan/checkpoint/tipe **tidak** menambah kolom baru dan tidak merusak tes PBI-01 (jumlah seeder setelah `migrate:fresh --seed` tetap).
8. Validasi nama wajib, `area_ha` numerik > 0, lat/lng valid, ditampilkan dalam Bahasa Indonesia.
9. Data nonaktif/terhapus tidak muncul di dropdown form baru (mis. checkpoint hanya memilih kawasan aktif) tetapi tetap tampil pada relasi riwayat.
10. Daftar master menampilkan kolom status aktif dan berpaginasi.

### Kualitas dan DoD proposal (bab 5)

- (a) Kode digabung ke branch utama lewat **pull request yang ditinjau anggota lain**.
- (b) Seluruh kriteria penerimaan di atas terpenuhi.
- (c) **Tes Laravel Dusk untuk FG-3 dan FG-4 lulus.**
- (d) Fitur dapat didemonstrasikan pada Sprint Review.
- `php artisan test` lulus termasuk tes lama + tes baru; regresi Dusk minggu terakhir sprint.
- Pint/PSR-12.
- Tidak ada secret di kode (`.env` untuk kredensial mail).
- Navigasi admin memuat tautan modul baru.

---

## 10. Rencana pengujian

| Tes | Isi |
|---|---|
| Feature FG-3 | list + paginasi, store validation, store ranger/admin (`Mail::fake()`: email terkirim ke alamat baru), password tersimpan ter-hash, email gagal kirim tetap membuat akun, update, deactivate, soft delete, restore, cannot delete/deactivate/demote self, email duplikat termasuk soft-deleted ditolak, 403 ranger/citizen, guest redirect |
| Feature FG-4 | CRUD tiap resource, unique code/name, soft delete + restore area/checkpoint/type, deactivate risk (dan larangan menonaktifkan risiko aktif terakhir), status hanya `GET` (tulis → 404/405), dropdown hanya data aktif, 403 |
| Regresi | `Pbi01SetupAndDatabaseTest`, `Pbi02AuthAndRbacTest` |
| **Dusk (wajib)** | **D-03-01** admin login → Pengguna → "Tambah akun" → buat ranger → baris baru muncul. **D-03-02** nonaktifkan ranger → login ranger ditolak. **D-03-03** hapus lunak lalu pulihkan user. **D-03-04** admin tidak bisa menonaktifkan akun sendiri. **D-04-01** buka Kawasan → tambah satu baris → muncul di daftar. **D-04-02** hapus lunak kawasan dummy → pulihkan. **D-04-03** halaman Status tidak punya tombol tambah/ubah/hapus. |

Catatan Dusk: set `MAIL_MAILER=array` (atau `log`) di `.env.dusk.local` agar tes tidak mengirim email sungguhan. Data uji memakai seeder; jangan bergantung pada ID hardcode.

---

## 11. Risiko & dependensi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Email gagal terkirim / mail belum dikonfigurasi | Pengguna baru tidak menerima kata sandi | Akun tetap dibuat, peringatan ke Admin, Admin set password lewat Ubah, log error |
| Kata sandi sementara bocor lewat log mail | Akun baru bisa diambil alih | Driver `log` hanya di dev; produksi pakai SMTP; tanpa paksa-ganti-password dicatat sebagai backlog |
| Dosen menuntut soft delete pada risiko/status | Konflik ADR-03 | Dokumentasikan mapping di demo (7.2) |
| Unique email vs user terhapus | Gagal insert | Tolak create; arahkan restore |
| Unique `checkpoints.code` vs terhapus | Sama | Sama |
| Status kasus diubah/dihapus | Alur status insiden dan NFR-10 rusak | Status read-only, tanpa rute tulis |
| Soft delete kawasan yang punya checkpoint | UI membingungkan | Hide/cascade lunak checkpoint anak dalam satu transaksi |
| Mass assignment `role_id` | Privilege escalation | Action server-side, validasi `Rule::in` nama peran |
| Dusk dikerjakan terlambat | PBI tidak memenuhi DoD | Dusk dikerjakan bersamaan dengan fitur, bukan di akhir |
| Scope terlalu besar dua isu sekaligus | Slip deadline | Selesaikan FG-3 dulu, lalu FG-4 per resource |

Dependensi hulu: PBI-01 dan PBI-02 **selesai** (sudah di repo).  
Dependensi hilir: PBI patroli & insiden akan membaca kawasan, checkpoint, tipe, risiko, status, dan akun ranger hasil FG-3/FG-4.

---

## 12. Milestone pengerjaan

1. **Kontrak:** PRD v1.1 disepakati (terutama Q-1 sampai Q-3 di 0.2, dan ADR baru 7.7).
2. **FG-3:** rute + Action + views pengguna + Mailable + Feature test + Dusk D-03-xx.
3. **FG-4a:** kawasan + checkpoint (paling dibutuhkan patroli) + Dusk D-04-01/02.
4. **FG-4b:** tipe insiden (soft delete).
5. **FG-4c:** risiko (`is_active`) + status (read-only) + Dusk D-04-03.
6. **Regresi PBI-01/02 + Pint + regresi Dusk.**
7. **PR ditinjau anggota lain, lalu merge** (DoD a).
8. **Demo:** akun admin seeder → tambah ranger (tunjukkan email) → nonaktifkan → hapus lunak kawasan dummy → tunjukkan restore → tunjukkan status read-only.

Urutan branch mengikuti README: `feature/pbi-03-manajemen-pengguna` lalu `feature/pbi-04-data-master` (atau satu branch jika tim menggabungkan FG-3+FG-4).

---

## 13. Lampiran

### 13.1 Peta file

**Reuse:** `app/Models/{User,Role,ForestArea,Checkpoint,IncidentType,RiskLevel,CaseStatus}.php`, middleware `role`/`active`, `layouts/app.blade.php`, komponen Blade, seeder, ADR.

**Baru:** controller/request/action/view/tes di 7.6, `TemporaryPasswordMail` + view email, item nav di layout, ADR di 7.7.

**Jangan sentuh kecuali bug blocker:** migrasi `2026_01_01_00000x_*`, alur `RegisterCitizen`, pesan login generik.

### 13.2 Matriks keterlacakan proposal → PRD

| Ketentuan proposal | Isi | Dipenuhi oleh |
|---|---|---|
| FR-14 / PBI-03 | Admin menambah, mengubah, menonaktifkan akun, menetapkan peran (termasuk Ranger) | UC-03-02 s.d. 04; AC FG-3 no. 2–3, 5 |
| UC-11 langkah 1 | Daftar akun beserta peran dan status | UC-03-01; AC FG-3 no. 1 |
| UC-11 langkah 2–3 | Form nama, email, peran; tombol "Tambah akun"; kata sandi sementara lewat email | UC-03-02; 7.3; 8.1; AC FG-3 no. 2, 10–11 |
| UC-11 langkah 4 | Ubah peran, nonaktifkan, aktifkan kembali | UC-03-03/04 |
| UC-11 A1 | Email terdaftar → tolak | AC FG-3 no. 9 |
| UC-11 A2 | Tidak bisa menonaktifkan diri sendiri | UC-03-07; AC FG-3 no. 7 |
| UC-11 A3 | Nonaktif tidak bisa login, riwayat tersimpan (soft delete) | UC-03-04/05; AC FG-3 no. 5–6 |
| Hak akses Admin (3.1.2) | Insert, update, soft delete pengguna dan master | 6.2, 6.3 |
| FR-15 / PBI-04 | Kelola area, checkpoint, tipe insiden, risiko, status; soft delete | 6.3; 7.1; AC FG-4 no. 1–5 |
| UC-12 langkah 2 | Daftar dengan status aktif | 7.5; AC FG-4 no. 10 |
| UC-12 langkah 3 | Area: nama, luas, GeoJSON opsional; checkpoint: nama, area; risiko: nama, bobot | 7.4; 8.2 (bobot = `sort_order`) |
| UC-12 A1 | Nama kosong / luas bukan angka ditolak | AC FG-4 no. 8 |
| UC-12 A2 | Status kasus tetap | 6.3 UC-04-E; AC FG-4 no. 5 |
| UC-12 A3 | Nonaktif tidak di pilihan form baru, tetap di riwayat | 7.1; AC FG-4 no. 9 |
| NFR-01 | Antarmuka konsisten dan responsif | 7.5 |
| NFR-02, NFR-11 | Halaman ≤ 3 detik, paginasi | 7.5; AC FG-4 no. 10 |
| NFR-03 | Password di-hash, CSRF | 7.3; AC FG-3 no. 11 |
| NFR-04 | RBAC di sisi server | `role:admin`; AC FG-3 no. 4, FG-4 no. 6 |
| NFR-08 | Transaksi DB dan pencatatan error | 7.3, 7.4; AC FG-3 no. 10 |
| NFR-10 | Integritas data: FK, aturan status | 3.1; 7.4 |
| Mockup 4.5 | Halaman master data dan master pengguna | 8.1, 8.2 |
| DoD bab 5 | PR ditinjau, kriteria terpenuhi, Dusk lulus, demo | Bagian 9 (Kualitas) |
| Tujuan khusus no. 6 | Dusk pada setiap fitur fungsional | Bagian 10 |

---

## 14. Ringkasan satu halaman untuk dosen/asisten

Aplikasi ForestGuard **sudah** memiliki database lengkap, data master tersimpan, login/RBAC, dan proteksi akun nonaktif — hasil PBI-01 dan PBI-02. Tugas FG-3 (PBI-03, FR-14, UC-11) membangun manajemen pengguna: Admin menambah akun ranger/admin dengan mengisi nama, email, dan peran; sistem mengirim kata sandi sementara lewat email; Admin dapat mengubah peran, menonaktifkan, menghapus lunak, dan memulihkan akun, serta dilarang menonaktifkan akunnya sendiri. Tugas FG-4 (PBI-04, FR-15, UC-12) membangun pengelolaan kawasan, checkpoint, tipe insiden (soft delete memakai kolom `deleted_at` yang sudah ada), tingkat risiko (nonaktif lewat `is_active`), dan tampilan status kasus yang bersifat tetap, agar tetap patuh ERD/ADR. Seluruh fitur diuji dengan Feature test dan Laravel Dusk sesuai Definition of Done. Patroli, laporan, dan peta **di luar** lingkup sprint ini.
