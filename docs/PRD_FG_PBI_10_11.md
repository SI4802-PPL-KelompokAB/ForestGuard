# PRD ForestGuard — PBI-10 dan PBI-11

- **Versi:** 1.0
- **Tanggal:** 31 Oktober 2026
- **Status:** Siap untuk handoff implementasi (Ready for Sprint 1 Execution).
- **Produk:** ForestGuard — Sustainable Forest Patrol & Forest Protection System.
- **Sprint:** Sprint 1.
- **Penanggung jawab pada proposal:** Jazzkord Cmajor Dahring (102022400361), Scrum Master dan Full-stack Developer.
- **Sumber Utama:**
  1. `Proposal Pengembangan Produk ForestGuard.pdf` (13 Oktober 2026).
  2. Mockup interaktif `ForestGuard.html` (Prototipe desain acuan tim).
- **Target Pembaca:** Agent implementasi, full-stack developer, QA/tester, dan reviewer (dosen pembimbing).

---

## 1. Instruksi Utama untuk Agent & Pengembang

Implementasikan modul **Pelaporan Insiden oleh Citizen dan Ranger (PBI-10)** serta **Alur Status Kasus dan Riwayat Perubahan Status (PBI-11)**. Seluruh implementasi harus berpedoman teguh pada:
1. **Arsitektur Inti Laravel MVC Murni**: Mengikuti `AGENTS.md` dan `docs/DECISIONS.md`. Tanpa Livewire, Inertia, React, Vue, atau Filament. Menggunakan Blade Components, Tailwind CSS, dan Vanilla JavaScript.
2. **Skema Basis Data Eksisting**: Gunakan tabel yang sudah ada dari migrasi PBI-01: `incidents`, `evidences`, `status_histories`, `case_statuses`, `incident_types`, `forest_areas`, dan `risk_levels`. **DILARANG MERUBAH ATAU MEMBUAT TABEL DUPLIK
AT**.
3. **Integritas Constraint & ADR**:
   - Mematuhi `ADR-04` mengenai CHECK constraint `evidences_exactly_one_parent` pada tabel `evidences`.
   - Mematuhi `ADR-03` mengenai timestamps, di mana tabel `evidences` dan `status_histories` tidak memiliki `updated_at`.
4. **Kesesuaian Pengujian**: Setiap fungsi wajib dibuktikan dengan feature tests PHPUnit (`tests/Feature/Pbi10IncidentReportingTest.php` dan `tests/Feature/Pbi11CaseStatusTransitionTest.php`) dengan target kelulusan 100% HIJAU.

---

## 2. Landasan Kebutuhan dan Pemetaan Proposal

Nomor halaman merujuk pada nomor halaman tercetak dokumen proposal (`Proposal Pengembangan Produk ForestGuard.pdf`):

| Referensi | Halaman | Keterkaitan Kebutuhan |
| :--- | :--- | :--- |
| FR-04 | 11 | Citizen mengisi laporan: tipe insiden, deskripsi, foto bukti, lokasi GPS, waktu kejadian. Validasi tipe & ukuran berkas. |
| FR-09 | 12 | Ranger membuat laporan insiden dari lapangan dengan format yang sama seperti Citizen, termasuk saat patroli aktif. |
| FR-17 | 12 | Admin meninjau laporan masuk (Verifikasi): menetapkan hasil terverifikasi (Verified) atau ditolak (Rejected) dengan alasan. Laporan diperlakukan sebagai dugaan (*suspected*) sampai terverifikasi. |
| FR-18 | 12 | Admin menugaskan Ranger pada kasus terverifikasi dan menetapkan tingkat risikonya (`risk_level_id`). |
| FR-19 | 12 | Admin memantau perkembangan kasus dan menutup kasus (`Resolved`) dengan catatan penyelesaian (`resolution_note`). |
| NFR-03 & NFR-05 | 14 | Autentikasi aman, proteksi CSRF, validasi berkas upload, penyimpanan foto di storage privat, dan perlindungan privasi data pelapor. |
| NFR-10 | 14 | *Data Integrity*: Relasi data dijaga dengan foreign key dan aturan transisi status (State Machine) untuk mencegah lompatan status yang tidak valid. |
| UC-03 | 18–19 | Use Case: Buat Laporan Insiden oleh Citizen dan Ranger (alur normal, validasi deskripsi min 15 karakter, foto maks 5MB, koordinat GPS). |
| UC-14, 15, 16 | 28–30 | Use Case: Verifikasi Laporan, Tugaskan Ranger, Pantau & Tutup Kasus. |
| ERD & Class Diagram | 33–38 | Skema `incidents`, `evidences`, `status_histories`, dan relasi antar entitas. |
| Mockup Halaman | 40–43 | Antarmuka Form Pelaporan Citizen (Hal. 42), Detail Kasus & Lini Masa Riwayat Status (Hal. 42–43), Daftar Kasus Admin (Hal. 43). |
| Backlog & SP | 46, 49 | PBI-10 (Must, 5 SP), PBI-11 (Must, 3 SP), Penanggung Jawab: Jazzkord Cmajor Dahring (JD). |

---

## 3. Tujuan, Peran Pengguna, dan Batasan Kewenangan

Dokumen ini menjamin alur pelaporan insiden perusakan hutan berjalan secara transparan dan memiliki status penanganan yang dapat diaudit dari awal hingga selesai.

| Pengguna (Peran) | Kebutuhan Utama | Batas Kewenangan & Hak Akses |
| :--- | :--- | :--- |
| **Citizen (Warga)** | Mengirimkan laporan dugaan perusakan hutan beserta foto & GPS, serta memantau perkembangan status laporan miliknya. | Hanya dapat membuat laporan baru dan melihat laporan milik sendiri. Tidak dapat melihat catatan teknis investigasi internal atau mengubah status kasus. |
| **Ranger (Petugas)** | Melaporkan temuan insiden di lapangan (mandiri atau saat patroli aktif) dan memperbarui status kasus tugasnya ke tahap investigasi. | Dapat membuat laporan insiden. Pada PBI-11 hanya berwenang mengubah status tugasnya menjadi `Investigation/Response`. Tidak berwenang menolak atau menutup kasus. |
| **Forest Manager / Admin** | Menerima seluruh laporan masuk, memverifikasi validitas data, menetapkan tingkat risiko, menolak laporan tidak valid, menugaskan kasus, dan menutup kasus. | Memiliki hak penuh atas mesin transisi status: memindahkan ke `Under Verification`, `Verified`, `Rejected`, `Assigned`, `Monitoring`, dan `Resolved`. |

---

## 4. Cakupan dan Ketergantungan (Scope & Dependencies)

### 4.1 Ruang Lingkup Termasuk (In-Scope)
* **PBI-10: Pelaporan Insiden oleh Citizen dan Ranger**
  - Form pelaporan insiden terstandarisasi untuk Citizen dan Ranger.
  - Dropdown dinamis tipe insiden aktif (`incident_types`) dan kawasan hutan (`forest_areas`).
  - Input waktu kejadian (`occurred_at`) dengan validasi batas waktu maksimal (tidak boleh di masa depan).
  - Textarea deskripsi temuan kejadian dengan validasi panjang minimal 15 karakter dan maksimal 2000 karakter.
  - Unggah foto bukti (JPG/PNG, maks 5 MB), penyimpanan ke storage privat, dan pembuatan rekaman di tabel `evidences`.
  - Input titik koordinat GPS (Latitude dan Longitude) dengan validasi rentang geografis.
  - Autogenerate kode registrasi tiket laporan unik (format: `INC-YYYYMM-XXXX` atau `FG-XXXX`).
  - Inisialisasi otomatis status awal laporan ke `Reported` dan pencatatan entri riwayat awal ke `status_histories`.
  - Modal konfirmasi sukses responsif pasca-submit.
* **PBI-11: Alur Status Kasus dan Riwayat Perubahan Status**
  - Implementasi State Machine siklus hidup kasus insiden (8 status):
    `Reported` → `Under Verification` → (`Verified` ATAU `Rejected`) → `Assigned` → `Investigation/Response` → `Monitoring` → `Resolved`.
  - Validasi ketat aturan transisi: pencegahan lompatan status (*skipping*), pencegahan pembalikan status ilegal, dan penguncian status terminal (`Resolved` dan `Rejected`).
  - Penegakan aturan data integritas:
    - Transisi ke `Rejected` wajib menyertakan `reject_reason` (min. 10 karakter).
    - Transisi ke `Verified` wajib menentukan `risk_level_id`.
    - Transisi ke `Resolved` wajib menyertakan `resolution_note` (min. 10 karakter) dan mencatat waktu `resolved_at`.
  - Pencatatan otomatis jejak audit transisi ke tabel `status_histories` (`incident_id`, `from_status_id`, `to_status_id`, `changed_by`, `note`, `changed_at`).
  - Visualisasi komponen lini masa riwayat status (*timeline audit trail*) kronologis pada detail kasus.

### 4.2 Di Luar Ruang Lingkup (Out-of-Scope)
- Algoritma AI deteksi otomatis foto atau klasifikasi citra satelit (dicatat sebagai pengembangan lanjutan pasca-MVP).
- Pengiriman push notification FCM/SMS (pengiriman notifikasi internal database ditangani di PBI-17).
- Antrean sinkronisasi offline IndexedDB di sisi service worker (diselesaikan di PBI-18–PBI-20). PBI-10 dan PBI-11 menyediakan kontrak payload idempotensi berbasis `client_uuid`.
- Form pemilihan multi-Ranger dan alokasi tim investigasi fisik (diselesaikan di PBI-14).
- Peta Heatmap GIS agregat dan analitik dashboard lanjutan (diselesaikan di PBI-22 dan PBI-23).

### 4.3 Matriks Ketergantungan (Dependencies)
| PBI Terkait | Kebutuhan yang Dibutuhkan / Disediakan |
| :--- | :--- |
| **PBI-01** | *Fondasi*: Skema database PostgreSQL/MySQL, migration, seeder master data (`CaseStatusSeeder`, `IncidentTypeSeeder`, `ForestAreaSeeder`, `RiskLevelSeeder`). |
| **PBI-02** | *Autentikasi & RBAC*: Middleware `auth`, `active`, dan pengecekan role `citizen`, `ranger`, `admin`. |
| **PBI-06** | *Konteks Patroli*: Menyediakan data patroli aktif (`patrol_id`) jika laporan dibuat oleh Ranger yang sedang patroli. |
| **PBI-12** | *Konsumen Data*: Halaman pelacakan status laporan bagi Citizen yang membaca data dari `incidents` dan `status_histories` yang dibuat pada PBI-10/11. |
| **PBI-13 & PBI-14** | *Alur Lanjutan*: Halaman UI verifikasi Admin mendalam dan penugasan Ranger yang memanfaatkan state engine dari PBI-11. |

---

## 5. Spesifikasi Fungsional PBI-10 — Pelaporan Insiden

### 5.1 Alur Utama (Normal Flow)
1. Pengguna membuka URL form laporan (`/citizen/laporan/buat` atau `/ranger/laporan/buat`).
2. Sistem menyajikan form dengan data master `incident_types` dan `forest_areas` aktif.
3. Pengguna memilih jenis kejadian, kawasan hutan, menginputkan waktu kejadian, serta uraian temuan minimal 15 karakter.
4. Pengguna mengunggah berkas foto bukti lapangan (maks. 5 MB) dan mengecek pratinjau thumbnail.
5. Pengguna menekan tombol "Ambil Lokasi Saat Ini" (HTML5 Geolocation API) atau memilih titik pada mini-map interaktif Leaflet.
6. Pengguna menekan "Kirim Laporan". Tombol otomatis berstatus *disabled* dengan spinner.
7. Backend memproses permintaan dalam 1 transaksi atomik (`DB::transaction`):
   - Validasi Form Request (`StoreIncidentRequest`).
   - Simpan file foto ke storage privat (`storage/app/private/evidences`).
   - Generate kode laporan unik: `INC-YYYYMM-XXXX`.
   - Simpan entitas `Incident` dengan status awal 'Reported'.
   - Simpan entitas `Evidence` terkait (memenuhi CHECK constraint).
   - Simpan entitas `StatusHistory` awal (`from_status_id = null`, `to_status_id = Reported`).
8. Response dialihkan (302) dengan flash message sukses atau mengembalikan modal konfirmasi memuat kode tiket.

### 5.2 Aturan Validasi Form (`StoreIncidentRequest`)
| Field | Tipe Data | Aturan Validasi | Pesan Kegagalan |
| :--- | :--- | :--- | :--- |
| `incident_type_id` | Integer | `required`, `exists:incident_types,id,deleted_at,NULL` | Kategori insiden wajib dipilih dan harus valid. |
| `forest_area_id` | Integer | `required`, `exists:forest_areas,id,deleted_at,NULL` | Kawasan hutan wajib dipilih dan harus valid. |
| `occurred_at` | DateTime | `required`, `date`, `before_or_equal:now` | Waktu kejadian wajib diisi dan tidak boleh melebihi waktu sekarang. |
| `description` | String | `required`, `string`, `min:15`, `max:2000` | Uraian kejadian wajib diisi minimal 15 karakter. |
| `latitude` | Decimal | `required`, `numeric`, `between:-90,90` | Koordinat lintang wajib diisi dalam rentang -90 hingga 90. |
| `longitude` | Decimal | `required`, `numeric`, `between:-180,180` | Koordinat bujur wajib diisi dalam rentang -180 hingga 180. |
| `gps_accuracy` | Decimal | `nullable`, `numeric`, `min:0` | Nilai akurasi GPS harus berupa angka positif. |
| `photo` | File | `required`, `file`, `image`, `mimes:jpg,jpeg,png`, `max:5120` | Foto bukti wajib dilampirkan, format JPG/PNG, ukuran maks. 5MB. |
| `client_uuid` | UUID | `required`, `uuid` | Client UUID wajib disertakan untuk idempotensi. |
| `patrol_id` | Integer | `nullable`, `exists:patrols,id` | ID patroli tidak valid. |

---

## 6. Spesifikasi Fungsional PBI-11 — Alur Status Kasus & Riwayat

### 6.1 Mesin Status (State Machine Lifecycle)
Siklus kasus insiden dikendalikan secara kaku melalui model transisi state berikut:

```
                  ┌────────────────┐
                  │    Reported    │ (Awal - PBI-10)
                  └───────┬────────┘
                          │ (Admin: Mulai Verifikasi)
                          ▼
              ┌───────────────────────┐
              │  Under Verification   │
              └─────┬───────────┬─────┘
   (Admin: Valid)   │           │   (Admin: Ditolak + Alasan Wajib)
                    ▼           ▼
             ┌───────────┐ ┌───────────┐
             │ Verified  │ │ Rejected  │ [TERMINAL]
             └─────┬─────┘ └───────────┘
(Admin: Tugaskan)  │
                   ▼
             ┌───────────┐
             │ Assigned  │
             └─────┬─────┘
 (Ranger: Mulai)   │
                   ▼
     ┌────────────────────────┐
     │ Investigation/Response │
     └─────────────┬──────────┘
 (Tindakan Selesai)│
                   ▼
             ┌────────────┐
             │ Monitoring │
             └─────┬──────┘
   (Admin: Tutup)  │ + Catatan Penyelesaian Wajib
                   ▼
             ┌────────────┐
             │  Resolved  │ [TERMINAL]
             └────────────┘
```

### 6.2 Matriks Matang Transisi yang Diizinkan (Allowed Transitions Matrix)
| Dari Status (`from_status`) | Ke Status yang Diizinkan (`to_status`) | Aktor yang Berwenang | Kondisi Prasyarat Wajib |
| :--- | :--- | :--- | :--- |
| `Reported` | `Under Verification` | Admin | - |
| `Under Verification` | `Verified` | Admin | `risk_level_id` wajib ditetapkan. |
| `Under Verification` | `Rejected` | Admin | `reject_reason` wajib diisi (min. 10 karakter). Status menjadi terminal. |
| `Verified` | `Assigned` | Admin | Minimal 1 Ranger ditugaskan pada kasus (`incident_assignments`). |
| `Assigned` | `Investigation/Response`| Ranger yang ditugaskan | Ranger memulai tindakan di lokasi kejadian. |
| `Investigation/Response`| `Monitoring` | Admin / Ranger | Pembaruan investigasi lapangan awal telah dicatat. |
| `Monitoring` | `Resolved` | Admin | `resolution_note` wajib diisi (min. 10 karakter). Stempel `resolved_at = now()`. Status menjadi terminal. |

Setiap transisi di luar tabel di atas (termasuk upaya mengubah status dari `Rejected` atau `Resolved`, serta lompatan langsung dari `Reported` ke `Resolved`) adalah **ILEGAL** dan wajib ditolak server dengan HTTP 422.

---

## 7. Desain Data dan Kontrak Implementasi

### 7.1 Tabel Basis Data Terkait

#### Tabel `incidents`
```sql
CREATE TABLE incidents (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(255) NOT NULL UNIQUE,
    client_uuid UUID NOT NULL UNIQUE,
    reporter_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    incident_type_id BIGINT NOT NULL REFERENCES incident_types(id) ON DELETE RESTRICT,
    forest_area_id BIGINT NOT NULL REFERENCES forest_areas(id) ON DELETE RESTRICT,
    patrol_id BIGINT NULL REFERENCES patrols(id) ON DELETE RESTRICT,
    risk_level_id BIGINT NULL REFERENCES risk_levels(id) ON DELETE RESTRICT,
    case_status_id BIGINT NOT NULL REFERENCES case_statuses(id) ON DELETE RESTRICT,
    description TEXT NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    gps_accuracy DECIMAL(8, 2) NULL,
    occurred_at TIMESTAMP NOT NULL,
    reject_reason TEXT NULL,
    resolution_note TEXT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX incidents_occurred_at_index ON incidents(occurred_at);
CREATE INDEX incidents_code_index ON incidents(code);
```

#### Tabel `evidences` (Mematuhi ADR-03 & ADR-04)
```sql
CREATE TABLE evidences (
    id BIGSERIAL PRIMARY KEY,
    client_uuid UUID NOT NULL UNIQUE,
    incident_id BIGINT NULL REFERENCES incidents(id) ON DELETE RESTRICT,
    observation_id BIGINT NULL REFERENCES observations(id) ON DELETE RESTRICT,
    update_id BIGINT NULL REFERENCES investigation_updates(id) ON DELETE RESTRICT,
    uploaded_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    captured_at TIMESTAMP NOT NULL,
    CONSTRAINT evidences_exactly_one_parent CHECK (
        (CASE WHEN incident_id IS NOT NULL THEN 1 ELSE 0 END) +
        (CASE WHEN observation_id IS NOT NULL THEN 1 ELSE 0 END) +
        (CASE WHEN update_id IS NOT NULL THEN 1 ELSE 0 END) = 1
    )
);
```

#### Tabel `status_histories` (Append-Only Log)
```sql
CREATE TABLE status_histories (
    id BIGSERIAL PRIMARY KEY,
    incident_id BIGINT NOT NULL REFERENCES incidents(id) ON DELETE RESTRICT,
    from_status_id BIGINT NULL REFERENCES case_statuses(id) ON DELETE RESTRICT,
    to_status_id BIGINT NOT NULL REFERENCES case_statuses(id) ON DELETE RESTRICT,
    changed_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    note TEXT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX status_histories_incident_changed_idx ON status_histories(incident_id, changed_at DESC);
```

---

## 8. Desain Antarmuka & UX (Berdasarkan `ForestGuard.html` & Proposal)

Sesuai mockup interaktif `ForestGuard.html` dan Subbab 4.5 Proposal Halaman 42:
1. **Palet Warna & Status Tokens**:
   - `Reported`: Badge abu-abu netral (`--ink2` / `#52665A`)
   - `Under Verification`: Badge kuning peringatan (`--warn` / `#A8730F`)
   - `Verified`: Badge biru informasi (`--info` / `#2A66B0`)
   - `Assigned`: Badge biru penugasan (`--info` / `#2A66B0`)
   - `Investigation/Response`: Badge oranye sinyal (`--signal` / `#E4570C`)
   - `Monitoring`: Badge violet observasi (`--violet` / `#6D4FB3`)
   - `Resolved`: Badge hijau sukses (`--ok` / `#2E7D4F`)
   - `Rejected`: Badge merah bahaya (`--bad` / `#C0392B`)
2. **Indikator Progres 7 Segmen**:
   - Progress bar menampilkan 7 strip segmen horizontal. Strip terisi warna hijau tua (`--pine`) sampai indeks status saat ini.
   - Bila kasus berstatus `Rejected`, strip segmen kedua berubah warna merah (`--bad`) dengan teks keterangan: *"Tidak lolos verifikasi · Kasus ditutup"*.
3. **Komponen Form Buat Laporan**:
   - Pilihan Jenis Insiden: 6 opsi pill radio dengan icon badge singkatan (*KH, IL, SL, DL, PB, KL*).
   - Dropdown Kawasan Hutan aktif.
   - Live character counter pada textarea: jika karakter < 15, teks berwarna merah; jika >= 15, berubah hijau.
   - File upload drop-zone dengan thumbnail preview instan dan label nama file + ukuran.
   - Tombol "Ambil lokasi saat ini" dengan preview titik pada mini-map Leaflet.
4. **Komponen Lini Masa Riwayat Status (Audit Trail)**:
   - Terletak di panel detail kasus (Drawer / Halaman Detail).
   - Disajikan dalam bentuk timeline vertikal bergaris menghubungkan titik status dari waktu kejadian pertama hingga status terakhir.
   - Memuat: Nama status, stempel tanggal & jam lokal (WIB), identitas pengguna pengubah, dan catatan ringkas.

---

## 9. Acceptance Criteria (Kriteria Penerimaan)

### PBI-10 (Pelaporan Insiden):
* **AC-PBI10-01**: Form pelaporan dapat diakses oleh akun Citizen dan Ranger yang aktif.
* **AC-PBI10-02**: Sistem men-generate kode unik tiket laporan (`code`) otomatis dan tidak boleh duplikat (format: `INC-YYYYMM-XXXX`).
* **AC-PBI10-03**: Laporan baru otomatis berstatus `Reported` dan mencatat 1 baris entri di `status_histories`.
* **AC-PBI10-04**: Deskripsi laporan wajib diisi minimal 15 karakter dan maksimal 2000 karakter.
* **AC-PBI10-05**: Lokasi GPS wajib tervalidasi dalam rentang koordinat geografis yang valid (Latitude: -90 s/d 90, Longitude: -180 s/d 180) dengan presisi 7 desimal.
* **AC-PBI10-06**: Foto bukti wajib berupa gambar valid (`jpg`, `jpeg`, `png`), maksimal 5 MB, tersimpan ke storage privat, dan tercatat di tabel `evidences`.
* **AC-PBI10-07**: Jika dibuat oleh Ranger yang sedang dalam patroli aktif (`in_progress`), kolom `patrol_id` otomatis terisi ID patroli tersebut.
* **AC-PBI10-08**: Operasi penyimpanan `incidents`, `evidences`, dan `status_histories` berjalan atomik dalam 1 transaksi DB.

### PBI-11 (Alur Status Kasus & Riwayat):
* **AC-PBI11-01**: Sistem memberlakukan validasi alur transisi status sesuai mesin status (State Machine) yang ditetapkan pada Subbab 6.2.
* **AC-PBI11-02**: Status `Resolved` dan `Rejected` berstatus terminal (`is_terminal = true`) dan ditolak dari segala bentuk transisi status lanjutan.
* **AC-PBI11-03**: Transisi ke `Rejected` wajib menyertakan alasan penolakan (`reject_reason`) minimal 10 karakter.
* **AC-PBI11-04**: Transisi ke `Resolved` wajib menyertakan catatan penyelesaian (`resolution_note`) minimal 10 karakter dan otomatis mengisi `resolved_at = now()`.
* **AC-PBI11-05**: Setiap perpindahan status wajib mencatat 1 rekaman baru pada `status_histories` secara atomik (`incident_id`, `from_status_id`, `to_status_id`, `changed_by`, `note`, `changed_at`).
* **AC-PBI11-06**: Rekaman pada tabel `status_histories` bersifat *append-only* (tidak boleh dapat diubah atau dihapus).
* **AC-PBI11-07**: Otorisasi RBAC ditegakkan di sisi server: Citizen ditolak saat mencoba mengubah status; Ranger hanya dapat memindahkan tugasnya ke `Investigation/Response`.
* **AC-PBI11-08**: Lini masa riwayat status disajikan terurut kronologis menurun (*newest first*) pada antarmuka detail kasus.

---

## 10. Matriks Uji Lengkap (Input & Output yang Diharapkan)

### A. Matriks Pengujian PBI-10 (Pelaporan Insiden)
| No | ID Test | Kategori | Input (Endpoint / Payload / Aksi) | Respon HTTP | Output yang Diharapkan (Assertion) |
| :- | :--- | :--- | :--- | :--- | :--- |
| 1 | TC-PBI10-01 | Exception | POST `/citizen/laporan`<br>Header: Tanpa Login (Guest) | 302 Redirect | Dialihkan ke `/login`. Request ditolak middleware auth. |
| 2 | TC-PBI10-02a | Exception | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `description` = null / kosong | 302 / 422 | Session error: *"Deskripsi laporan wajib diisi."* DB: 0 baris bertambah. |
| 3 | TC-PBI10-02b | Exception | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `latitude` = null, `longitude` = null | 302 / 422 | Session error: *"Titik koordinat lokasi kejadian wajib ditentukan."* DB: 0 baris bertambah. |
| 4 | TC-PBI10-03a | Exception | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `description` = "Ada kayu" *(8 char)* | 302 / 422 | Session error: *"Uraian kejadian minimal harus 15 karakter."* DB: 0 baris bertambah. |
| 5 | TC-PBI10-03b | Boundary | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `description` = "123456789012345" *(Tepat 15 char)* | 302 / 200 | Lolos validasi. Laporan berhasil tersimpan ke database. |
| 6 | TC-PBI10-04 | Exception | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `latitude` = 110.5, `longitude` = 200.0 | 302 / 422 | Session error: *"Format koordinat lintang/bujur tidak valid."* |
| 7 | TC-PBI10-05a | Exception | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `photo` = `besar.jpg` *(6.5 MB > 5 MB)* | 302 / 422 | Session error: *"Ukuran file foto bukti maksimal 5MB."* File tidak disimpan. |
| 8 | TC-PBI10-05b | Exception | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `photo` = `surat.pdf` *(Non-image)* | 302 / 422 | Session error: *"File bukti harus berupa format gambar (jpeg, png, jpg)."* |
| 9 | TC-PBI10-06 | Normal | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: Data lengkap valid, foto JPG 1MB, GPS valid | 302 / 200 | • `incidents`: Bertambah 1 record, status: `Reported`, `code` terisi unik.<br>• `evidences`: Bertambah 1 record terikat `incident_id`.<br>• `status_histories`: Bertambah 1 record (`to_status_id` = Reported). |
| 10 | TC-PBI10-07 | Normal | POST `/ranger/laporan`<br>Auth: Ranger (patroli aktif ID: 5)<br>Payload: Data lengkap valid | 302 / 200 | Laporan tersimpan di DB dengan `patrol_id` = 5. |
| 11 | TC-PBI10-08 | Exception | POST `/citizen/laporan`<br>Auth: Citizen<br>Payload: `incident_type_id` = 99999 *(Fiktif)* | 302 / 422 | Session error: *"Kategori insiden tidak valid."* |
| 12 | TC-PBI10-09 | Normal | Mengirim request ulang dengan `client_uuid` identik | 302 / 200 | Idempotent safe: Jumlah record di database tidak bertambah ganda. |

### B. Matriks Pengujian PBI-11 (Alur Status Kasus & Riwayat)
| No | ID Test | Kategori | Input (Endpoint / Payload / Aksi) | Respon HTTP | Output yang Diharapkan (Assertion) |
| :- | :--- | :--- | :--- | :--- | :--- |
| 1 | TC-PBI11-01a | Exception | POST `/admin/kasus/{id}/status`<br>Auth: Role Citizen | 403 Forbidden | Request ditolak otorisasi server. Status tidak berubah. |
| 2 | TC-PBI11-01b | Exception | POST `/admin/kasus/{id}/status`<br>Header: Guest | 302 Redirect | Dialihkan ke `/login`. |
| 3 | TC-PBI11-02a | Normal | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Reported`<br>Payload: `to_status` = "Under Verification" | 302 / 200 | Status berubah menjadi `Under Verification`. `status_histories` bertambah 1 baris. |
| 4 | TC-PBI11-02b | Normal | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Under Verification`<br>Payload: `to_status` = "Verified", `risk_level_id` = 2 | 302 / 200 | Status berubah menjadi `Verified`. `risk_level_id` tersimpan. `status_histories` bertambah. |
| 5 | TC-PBI11-03 | Exception | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Reported`<br>Payload: `to_status` = "Resolved" *(Lompat)* | 422 Unprocessable | Validation error: *"Transisi status dari Reported ke Resolved tidak diizinkan."* |
| 6 | TC-PBI11-04a | Exception | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Resolved`<br>Payload: `to_status` = "Monitoring" | 422 Unprocessable | Validation error: *"Kasus yang telah selesai tidak dapat diubah statusnya lagi."* |
| 7 | TC-PBI11-04b | Exception | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Rejected`<br>Payload: `to_status` = "Under Verification" | 422 Unprocessable | Validation error: *"Laporan yang telah ditolak tidak dapat diubah statusnya."* |
| 8 | TC-PBI11-05a | Exception | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Under Verification`<br>Payload: `to_status` = "Rejected", `reject_reason` = null | 422 Unprocessable | Validation error: *"Alasan penolakan laporan wajib diisi."* Status tidak berubah. |
| 9 | TC-PBI11-05b | Normal | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Under Verification`<br>Payload: `to_status` = "Rejected", `reject_reason` = "Data tidak sesuai fakta lapangan" | 302 / 200 | Status berubah ke `Rejected`. `reject_reason` terisi. Kasus dikunci terminal. |
| 10 | TC-PBI11-06a | Exception | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Monitoring`<br>Payload: `to_status` = "Resolved", `resolution_note` = null | 422 Unprocessable | Validation error: *"Catatan penyelesaian wajib diisi saat menutup kasus."* |
| 11 | TC-PBI11-06b | Normal | POST `/admin/kasus/{id}/status`<br>Auth: Admin<br>Kasus: `Monitoring`<br>Payload: `to_status` = "Resolved", `resolution_note` = "Pelaku telah diamankan dan batas hutan dipulihkan" | 302 / 200 | Status berubah ke `Resolved`. `resolution_note` dan `resolved_at` terisi. Kasus dikunci terminal. |
| 12 | TC-PBI11-07 | Normal | GET `/admin/kasus/{id}`<br>Auth: Admin | 200 OK | Respon memuat daftar riwayat status terurut kronologis descending (`changed_at` terbaru di atas). |

---

## 11. Urutan Langkah Implementasi

Pengembangan PBI-10 dan PBI-11 dilakukan secara bertahap:
1. **Langkah 1 (Service / Action State Machine)**:
   - Buat class transisi status insiden (contoh: `App\Actions\Incidents\TransitionIncidentStatus`) yang memvalidasi transisi yang diizinkan dan mencatat atomik ke `status_histories`.
2. **Langkah 2 (Backend PBI-10 Pelaporan Insiden)**:
   - Buat `StoreIncidentRequest` dengan seluruh aturan validasi sesuai Subbab 5.2.
   - Buat Action `App\Actions\Incidents\StoreIncidentReport` untuk menangani upload bukti foto, penomoran kode tiket unik (`INC-YYYYMM-XXXX`), pembuatan model `Incident`, `Evidence`, dan pemanggilan state machine untuk status awal `Reported`.
   - Daftarkan route pelaporan insiden untuk Citizen dan Ranger di `routes/web.php`.
   - Buat controller penanganan (`App\Http\Controllers\Citizen\IncidentController` dan `App\Http\Controllers\Ranger\IncidentController`).
3. **Langkah 3 (Frontend Blade & UI Components)**:
   - Buat form view pelaporan insiden mengacu pada layout `ForestGuard.html` (radio type pills, Leaflet mini-map, file upload dropzone, live character counter).
   - Buat komponen modal konfirmasi sukses responsif.
   - Buat komponen lini masa riwayat status (`components/incident-timeline.blade.php`) dan progress bar 7 segmen untuk halaman detail kasus.
4. **Langkah 4 (Pengujian Otomatis Feature Test PHPUnit)**:
   - Tulis seluruh skenario pengujian di `tests/Feature/Pbi10IncidentReportingTest.php`.
   - Tulis seluruh skenario pengujian di `tests/Feature/Pbi11CaseStatusTransitionTest.php`.
   - Jalankan `php artisan test --filter=Pbi10` dan `php artisan test --filter=Pbi11` hingga 100% HIJAU.
5. **Langkah 5 (Code Formatting & Pint)**:
   - Jalankan `vendor/bin/pint --dirty --format agent` untuk menjaga kerapian kode sesuai standar PSR-12.

---

## 12. Definition of Done (DoD)

Sebuah PBI dinyatakan selesai dan siap dipindahkan ke status **IN REVIEW / DONE** apabila:
1. Seluruh Acceptance Criteria (AC) pada PBI-10 dan PBI-11 telah terpenuhi seutuhnya.
2. Tidak ada pelanggaran schema/ADR database: relasi `evidences` mematuhi CHECK constraint, dan `status_histories` mencatat jejak audit dengan benar.
3. Seluruh skenario pengujian otomatis pada `Pbi10IncidentReportingTest` dan `Pbi11CaseStatusTransitionTest` berhasil lulus 100% HIJAU.
4. Kode telah diformat menggunakan Laravel Pint tanpa meninggalkan syntax error atau dead code.
5. Antarmuka form dan detail kasus telah teruji responsif di layar mobile dan desktop sesuai mockup acuan.

