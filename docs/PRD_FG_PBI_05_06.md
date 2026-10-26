# PRD ForestGuard — PBI-05 dan PBI-06

- **Versi:** 1.0
- **Tanggal:** 26 Oktober 2026
- **Status:** Siap untuk handoff implementasi; keputusan turunan diberi label eksplisit.
- **Produk:** ForestGuard — Sustainable Forest Patrol & Forest Protection System.
- **Sprint:** Sprint 1.
- **Penanggung jawab pada proposal:** Muhammad Daffa Izzati, Product Owner dan Full-stack Developer.
- **Sumber utama:** `Proposal PPL_Kelompok A-B.pdf` yang dilampirkan pengguna.
- **Target pembaca:** Agent implementasi, developer, reviewer, dan QA.

## 1. Instruksi utama untuk agent

Implementasikan hanya penugasan patroli oleh Admin (PBI-05) dan daftar tugas serta mulai/akhiri patroli oleh Ranger (PBI-06). Gunakan repository, autentikasi, skema database, dan komponen UI yang telah tersedia. Dokumen ini adalah spesifikasi produk dengan usulan kontrak implementasi, bukan bukti bahwa struktur kode tertentu sudah ada.

Sebelum menulis kode:

1. Baca `AGENTS.md`, dokumentasi proyek, dependency manifest, migration, model, routes, policy, layout, dan pengujian yang relevan.
2. Periksa hasil PBI-01, PBI-02, PBI-03, dan PBI-04. Catat dependency yang sudah tersedia dan yang belum tersedia.
3. Cocokkan model `Patrol`, `PatrolCheckpoint`, `User`, `ForestArea`, dan `Checkpoint` dengan ERD. Reuse implementasi yang ada; jangan membuat tabel atau sistem autentikasi duplikat.
4. Tentukan mapping nama status dan field bila repository berbeda dari usulan dokumen ini. Pertahankan perilaku bisnis, lalu dokumentasikan mapping.
5. Jangan mengimplementasikan seluruh UC-06 sebagai cakupan PBI-06: UC tersebut juga mencakup GPS, checkpoint, dan offline yang dimiliki PBI lain.
6. Setelah implementasi, laporkan file berubah, migration, langkah menjalankan, hasil pengujian, dan dependency atau keterbatasan yang masih ada. Jangan mengklaim GPS, offline, atau notifikasi selesai hanya karena hook integrasi tersedia.

## 2. Landasan kebutuhan dan aturan membaca dokumen

### 2.1 Referensi proposal

Nomor halaman berikut menggunakan **nomor tercetak**, bukan indeks PDF. Pada file sumber, nomor halaman PDF = nomor tercetak + 2.

| Referensi | Halaman tercetak | Keterkaitan |
| --- | --- | --- |
| FR-06 | 11 | Ranger melihat tugas, memulai dan mengakhiri patroli; waktu mulai dan selesai dicatat. |
| FR-16 | 12 | Admin menentukan Ranger, area, jadwal, serta checkpoint wajib kunjung. |
| NFR-01 sampai NFR-11 | 14 | Responsif, RBAC, privasi, transaksi, integritas, dan target performa. |
| TR-01 sampai TR-11 | 15 | Laravel MVC, MySQL/MariaDB, Blade, teknologi frontend, dan Laravel Dusk. |
| UC-06 | 21–22 | Prasyarat satu patroli berjalan per Ranger; alur mulai/selesai; boleh selesai meskipun checkpoint belum lengkap. |
| UC-13 | 27 | Penugasan, kode unik, pembuat, jadwal wajib, minimal satu checkpoint; urut ulang dan pembatalan masih berupa usulan. |
| ERD, subbab 4.3 | 35 | Struktur `PATROLS`, `PATROL_CHECKPOINTS`, dan relasi ke master data. |
| Mockup Ranger, subbab 4.5 | 40 | Daftar tugas, detail patroli, status, dan tampilan ponsel. |
| Mockup Admin, subbab 4.5 | 43 | Tabel patroli dan formulir penugasan pada satu halaman. |
| DoD dan backlog | 45–48 | PBI-05: Must, 5 SP; PBI-06: Must, 3 SP; pengujian Dusk per PBI. |

### 2.2 Jenis ketentuan

- **[Sumber]:** Dinyatakan di proposal; menjadi baseline kebutuhan.
- **[Keputusan PRD]:** Aturan pelengkap yang ditetapkan dokumen ini agar agent dapat bekerja tanpa menebak. Bukan kutipan atau keputusan yang sudah disahkan tim.
- **[Usulan teknis]:** Nama route, service, event, dan struktur respons yang dapat disesuaikan dengan konvensi repository.
- **[Integrasi]:** Perilaku akhir produk yang penyelesaiannya bergantung pada PBI lain.

Jika ada benturan nyata dengan repository atau kesepakatan tim, dokumentasikan dampaknya. Jangan diam-diam memperluas scope atau mengubah ERD secara destruktif.

## 3. Tujuan, pengguna, dan keberhasilan

Admin dapat membuat penugasan yang lengkap dan konsisten. Ranger yang ditugaskan dapat menemukan tugasnya, mulai patroli, melanjutkan tampilan patroli yang sedang berjalan, dan mengakhirinya dengan waktu aktual yang tersimpan.

| Pengguna | Kebutuhan | Batas kewenangan |
| --- | --- | --- |
| Forest Manager/Admin | Melihat daftar penugasan, membuat patroli, dan membaca detail untuk pengecekan. | Tidak menjalankan patroli atas nama Ranger dalam scope ini. |
| Ranger | Melihat dan menjalankan patroli miliknya sendiri. | Tidak melihat atau mengubah patroli Ranger lain. |
| Citizen | Tidak memiliki kebutuhan pada dua PBI ini. | Tidak dapat mengakses halaman maupun endpoint patroli. |

Keberhasilan dinilai dari acceptance criteria dan pengujian: penugasan tersimpan atomik, isolasi akses terjaga, transisi status valid, serta tidak ada dua patroli berjalan untuk Ranger yang sama. Tidak ada target bisnis numerik tambahan yang diasumsikan dari proposal.

## 4. Cakupan dan dependency

### 4.1 Termasuk

**PBI-05 — Penugasan patroli oleh Admin**

- Daftar patroli Admin dengan pagination.
- Form pemilihan satu Ranger, satu area hutan, tanggal/jam, dan satu atau lebih checkpoint area tersebut.
- Penyimpanan kode unik, identitas pembuat, status awal, dan urutan checkpoint.
- Validasi server, feedback berhasil/gagal, serta titik integrasi notifikasi.

**PBI-06 — Pelaksanaan patroli Ranger**

- Daftar dan detail tugas milik Ranger yang login.
- Status `Ditugaskan`, `Berlangsung`, `Selesai`.
- Mulai dan akhiri patroli, waktu aktual, durasi, serta perlindungan request bersamaan.
- Pemulihan tampilan patroli berjalan setelah reload.
- Membaca daftar checkpoint yang direncanakan dan status kunjungan jika datanya telah tersedia.

### 4.2 Di luar cakupan

- GPS, rute, jumlah titik, dan perhitungan jarak: **PBI-07**.
- Mencatat kunjungan checkpoint, observasi, dan bukti foto: **PBI-08/PBI-09**.
- Pelaporan atau penugasan kasus insiden: PBI terkait insiden, bukan penugasan patroli.
- Inbox, badge, mark-as-read, dan pengiriman notifikasi lengkap: **PBI-17**.
- PWA, IndexedDB, operasi offline, antrean/retry API sinkronisasi, dan Sync Center: **PBI-18–20**.
- Halaman riwayat lengkap beserta rute/filter dan peta: **PBI-21/PBI-22**.
- Dashboard, ekspor, pembatalan, penghapusan, reschedule, penggantian Ranger, drag-and-drop urutan checkpoint, patroli tim/multi-Ranger, dan pengulangan jadwal.

**Catatan integrasi:** Daftar boleh tetap menampilkan kartu ringkas patroli selesai agar hasil aksi dapat diverifikasi. Hal ini bukan implementasi halaman riwayat lengkap. Status patroli juga tidak menggunakan master `CASE_STATUSES`, yang merupakan status kasus insiden.

### 4.3 Dependency

| PBI | Yang dibutuhkan/disediakan |
| --- | --- |
| PBI-01 | Proyek, database, migration, seeder, dan kerangka Dusk. |
| PBI-02 | Login/logout, sesi, RBAC, serta identitas pengguna terautentikasi. |
| PBI-03 | Akun Ranger dan mekanisme aktif/nonaktif. Dapat memakai fixture valid jika UI pengelolaan belum selesai. |
| PBI-04 | Area dan checkpoint aktif serta soft delete. |
| PBI-07 | Membaca status/waktu patroli dan mengelola rekaman GPS/jarak. |
| PBI-08 | Mengisi kunjungan pada `patrol_checkpoints` yang dibuat PBI-05. |
| PBI-17 | Mengonsumsi pemberitahuan penugasan baru setelah transaksi berhasil. |
| PBI-18–20 | Menggunakan kembali aturan lifecycle saat implementasi offline/sinkronisasi. |

## 5. PBI-05 — Penugasan patroli

### 5.1 User story

Sebagai Admin, saya ingin menugaskan seorang Ranger pada area, jadwal, dan checkpoint tertentu agar kegiatan patroli memiliki penanggung jawab dan rencana kunjungan yang jelas.

### 5.2 Alur utama

1. Admin membuka menu **Patroli**.
2. Sistem menampilkan daftar penugasan dan form **Penugasan patroli baru**.
3. Admin memilih Ranger aktif dan area yang tidak dihapus.
4. Sistem memuat checkpoint aktif untuk area terpilih beserta nomor urut tampilan.
5. Admin mengisi tanggal/jam dan memilih minimal satu checkpoint.
6. Admin menekan **Tugaskan patroli**. Tombol disabled selama request diproses.
7. Server memeriksa izin serta seluruh input, lalu menyimpan patroli dan checkpoint dalam satu transaksi.
8. Setelah commit, sistem memberi feedback sukses dan memperbarui daftar. Integrasi notifikasi menerima identitas patroli serta Ranger.
9. Ranger dapat melihat tugas melalui daftar patroli miliknya.

### 5.3 Form dan validasi

| Field | Aturan |
| --- | --- |
| Ranger | Wajib; pengguna ada, berperan Ranger, `is_active = true`, dan tidak soft-deleted. |
| Area hutan | Wajib; area ada dan tidak soft-deleted. ERD tidak memiliki `forest_areas.is_active`; jangan mengasumsikan field tersebut. |
| Tanggal dan jam | Keduanya wajib, dapat diparse, digabung menjadi satu `scheduled_at`. |
| Checkpoint | Array wajib, minimal satu, tidak duplikat, semua ada, tidak soft-deleted, dan berada dalam area yang dipilih. |
| Urutan | Dibentuk server dari urutan tampilan stabil; bukan nilai bebas yang dipercaya dari request. |
| Pembuat | Diambil dari pengguna Admin yang login; jangan menerima `created_by` dari klien. |
| Kode/status/waktu aktual | Dihasilkan atau dikendalikan server; tidak boleh diisi bebas oleh klien. |

**[Keputusan PRD]** Default urutan checkpoint berdasarkan `code` ascending, lalu `id` sebagai tie-breaker, kecuali master data repository sudah memiliki urutan resmi. Subset terpilih disimpan dengan `sequence` berurutan mulai 1. Tidak ada UI mengubah urutan pada versi ini.

**[Keputusan PRD]** Jadwal lampau tidak otomatis ditolak karena proposal tidak menentukan larangan tersebut. Jadwal juga tidak membatasi jam mulai aktual. Jangan menambah aturan anti-overlap jadwal: ERD hanya menyimpan satu waktu jadwal, tanpa durasi rencana. Beberapa tugas boleh dijadwalkan untuk Ranger yang sama; batas satu patroli berlaku saat status Berlangsung.

### 5.4 Kondisi alternatif

- Tidak ada Ranger: tampilkan alasan dan arahkan Admin ke pengelolaan pengguna jika menu tersedia.
- Tidak ada area/checkpoint: tampilkan empty state; tombol submit tidak aktif sampai data lengkap.
- Area diganti: kosongkan pilihan checkpoint area sebelumnya; abaikan hasil request pemuatan lama yang datang terlambat.
- Master dihapus/nonaktif setelah form dibuka: validasi ulang pada submit dan minta pengguna memilih ulang.
- Validasi gagal: tampilkan error di field terkait dan pertahankan input yang masih valid.
- Database gagal saat membuat salah satu checkpoint: rollback seluruh penugasan; tidak boleh ada patroli parsial.
- Notifikasi gagal setelah commit: patroli tetap valid; kegagalan dicatat/retry melalui mekanisme PBI-17, bukan membatalkan data yang sudah commit.

## 6. PBI-06 — Pelaksanaan patroli Ranger

### 6.1 User story

Sebagai Ranger, saya ingin melihat tugas saya, memulai patroli, dan mengakhirinya agar status pelaksanaan serta waktu aktual tercatat dengan benar.

### 6.2 Daftar dan detail

Daftar memuat kode, area, jadwal, status, jumlah checkpoint, dan aksi yang relevan. Detail memuat urutan checkpoint, waktu mulai/selesai, serta durasi jika sudah dimulai.

**[Keputusan PRD]** Daftar memprioritaskan patroli Berlangsung, kemudian Ditugaskan berdasarkan jadwal terdekat, lalu Selesai berdasarkan waktu selesai terbaru. Gunakan pagination mengikuti standar proyek; default usulan 15 item. Query Ranger selalu dibatasi `ranger_id` pengguna yang login.

| Status | Aksi UI Ranger |
| --- | --- |
| Ditugaskan | Buka detail; **Mulai patroli**. Jika ada patroli lain berjalan, tampilkan alasan dan tautan ke patroli tersebut. |
| Berlangsung | **Lanjutkan patroli** untuk membuka detail yang sama; **Akhiri patroli** pada detail. |
| Selesai | Detail read-only dan ringkasan waktu; tidak ada tombol mulai ulang/akhiri ulang. |

### 6.3 Mulai patroli

1. Ranger memilih tugas miliknya dan menekan **Mulai patroli**.
2. Server memastikan akun boleh menjalankan aksi, patroli miliknya masih Ditugaskan, dan tidak ada patroli lain miliknya Berlangsung.
3. Dalam transaksi yang terlindungi dari request bersamaan, server mengisi `started_at` dengan waktu server dan mengubah status ke Berlangsung.
4. UI membuka detail aktif dan menampilkan waktu mulai serta durasi.
5. Integrasi PBI-07 dapat mulai merekam GPS setelah perubahan berhasil. Ketiadaan modul GPS tidak memblokir fungsi dasar PBI-06.

### 6.4 Akhiri patroli

1. Ranger menekan **Akhiri patroli** pada patroli aktif miliknya.
2. UI meminta konfirmasi agar patroli tidak selesai karena salah tekan.
3. Jika ada checkpoint belum dikunjungi, tampilkan jumlah dan peringatan bahwa patroli tetap boleh diselesaikan.
4. Server memeriksa status masih Berlangsung dan kepemilikan; lalu menyimpan `ended_at` dan status Selesai secara atomik.
5. UI menampilkan waktu selesai, durasi akhir, dan checkpoint belum dikunjungi. Tidak ada perubahan otomatis `visited_at` untuk checkpoint tersebut.
6. PBI-07 dapat menghentikan tracking dan menghitung jarak. Jangan menulis jarak palsu jika modul tersebut belum tersedia.

### 6.5 Aturan status

| Dari | Aksi | Ke | Prasyarat | Efek |
| --- | --- | --- | --- | --- |
| Belum ada | Admin membuat | Ditugaskan | Input penugasan valid | Jadwal, pembuat, Ranger, dan checkpoint tersimpan. |
| Ditugaskan | Ranger mulai | Berlangsung | Pemilik tugas; tidak ada patroli lain berjalan | `started_at` terisi; `ended_at` null. |
| Berlangsung | Ranger akhiri | Selesai | Pemilik tugas | `ended_at` terisi; waktu mulai dipertahankan. |
| Selesai | Aksi mutasi apa pun | Ditolak | Status terminal pada scope ini | Tidak ada perubahan data. |

Tidak ada status Draft, Dibatalkan, Terlambat, atau Ditolak dalam lifecycle ini. Status Ditugaskan melewati jadwal tetap Ditugaskan.

### 6.6 Keandalan dan waktu

- **[Sumber]** Maksimal satu patroli Berlangsung per Ranger (UC-06).
- **[Keputusan PRD]** Semua aksi online memakai waktu server, disimpan UTC dan ditampilkan sesuai timezone aplikasi. Jangan mengasumsikan timezone proyek dari lokasi pengguna; jika belum dikonfigurasi, gunakan `Asia/Jakarta` sebagai default yang didokumentasikan.
- Durasi = `ended_at - started_at` untuk Selesai, atau waktu server terkini dikurangi `started_at` untuk Berlangsung. Reload tidak mereset durasi.
- Nilai timer di browser hanya tampilan; kebenaran akhir dari timestamp database. Durasi tidak boleh negatif.
- Request mulai/akhir yang berulang ditolak sebagai konflik status dan mengembalikan kondisi terbaru; waktu yang sudah tercatat tidak ditimpa.
- Jika koneksi hilang atau request timeout, jangan tampilkan sukses sebelum ada konfirmasi server. Ketika tersambung kembali, ambil kondisi terbaru sebelum retry. Implementasi penyimpanan dan mulai/akhir offline menjadi pekerjaan PBI-18–20.
- Data area/checkpoint historis yang kemudian soft-deleted tetap dapat ditampilkan read-only melalui relasi historis. Penugasan valid yang sudah ada tetap dapat dijalankan; tidak otomatis dihapus karena perubahan master.

## 7. Data dan kontrak implementasi

### 7.1 Mapping ERD

Nama berikut terbaca dari ERD halaman 35. Tipe rinci, nullability, dan indeks tetap harus dicocokkan dengan migration PBI-01.

| Entitas | Field relevan | Kepemilikan pada scope ini |
| --- | --- | --- |
| `patrols` | `id`, `code`, `client_uuid`, `ranger_id`, `forest_area_id`, `created_by`, `scheduled_at`, `started_at`, `ended_at`, `total_distance_km`, `status`, `created_at` | PBI-05 membuat penugasan; PBI-06 mengubah status dan waktu aktual. |
| `patrol_checkpoints` | `id`, `client_uuid`, `patrol_id`, `checkpoint_id`, `sequence`, `visited_at`, `latitude`, `longitude` | PBI-05 membuat rencana; PBI-08 mengisi kunjungan dan koordinat. |
| `users` | `id`, `role_id`, `name`, `is_active`, `deleted_at` | Dependency; tidak membuat manajemen pengguna baru. |
| `forest_areas` | `id`, `name`, `deleted_at` | Dependency master area. |
| `checkpoints` | `id`, `forest_area_id`, `code`, `name`, `latitude`, `longitude`, `deleted_at` | Dependency master checkpoint. |

**[Usulan teknis]** Nilai status internal: `assigned`, `in_progress`, `completed`; label UI: Ditugaskan, Berlangsung, Selesai. Jika repository menggunakan nilai lain, buat mapping konsisten.

**[Keputusan PRD]** `started_at`, `ended_at`, dan field kunjungan mula-mula null. `total_distance_km` menyesuaikan migration, tetapi UI menampilkan `—` ketika belum dihitung; jangan menganggap nilai default 0 sebagai hasil GPS yang terukur. Durasi dihitung dari timestamp, tidak membutuhkan kolom tambahan.

**[Usulan teknis]** Buat `client_uuid` unik saat membuat patroli dan setiap baris rencana checkpoint jika diwajibkan schema. UUID ini disediakan untuk integrasi mendatang; keberadaannya saja belum mengimplementasikan deduplikasi offline.

### 7.2 Integritas dan konkurensi

- `code` unik pada database; format manusiawi mengikuti generator proyek. Contoh `P-000101` hanya ilustrasi. Jangan memakai `count() + 1` tanpa proteksi benturan.
- Foreign key mengikuti ERD. Tambahkan constraint unik `(patrol_id, checkpoint_id)` dan `(patrol_id, sequence)` bila belum ada dan sesuai migration.
- Satu penugasan menyimpan tepat satu Ranger. Jangan memakai tabel penugasan kasus `incident_assignments`.
- Simpan patroli dan seluruh rencana checkpoint dalam satu transaksi.
- Untuk mulai patroli, **[usulan teknis]** lock baris User Ranger terlebih dahulu dalam transaksi, kemudian lock patroli dan periksa patroli aktif. Mengunci patroli target saja tidak mencegah dua tugas berbeda dimulai bersamaan.
- Untuk mengakhiri, pakai transaksi dan urutan lock yang konsisten. Seluruh jalur start/end, termasuk integrasi sinkronisasi mendatang, harus memakai aturan/service yang sama.
- Validasi master ulang sebelum penyimpanan; gunakan transaksi/lock yang sesuai agar perubahan master bersamaan tidak meloloskan referensi yang tidak valid.
- Batasi field yang dapat dimutasi; `ranger_id`, `created_by`, status, dan waktu aktual tidak boleh diubah melalui mass assignment request Ranger.

### 7.3 Usulan route dan aksi

Route berikut adalah kontrak usulan, bukan mandat untuk mengganti route proyek. Operasi perubahan data tidak boleh menggunakan GET.

| Method | Route usulan | Peran | Fungsi |
| --- | --- | --- | --- |
| GET | `/admin/patrols` | Admin | Daftar penugasan dan form. |
| GET | `/admin/forest-areas/{area}/checkpoints` | Admin | Pilihan checkpoint aktif dalam area. |
| POST | `/admin/patrols` | Admin | Membuat penugasan. |
| GET | `/admin/patrols/{patrol}` | Admin | Detail penugasan read-only. |
| GET | `/ranger/patrols` | Ranger | Daftar tugas sendiri. |
| GET | `/ranger/patrols/{patrol}` | Ranger pemilik | Detail tugas sendiri. |
| POST | `/ranger/patrols/{patrol}/start` | Ranger pemilik | Mulai patroli. |
| POST | `/ranger/patrols/{patrol}/finish` | Ranger pemilik | Akhiri patroli. |

Payload create minimal: `ranger_id`, `forest_area_id`, `scheduled_at` beserta timezone yang jelas, `checkpoint_ids[]`. UI tanggal/jam boleh terpisah lalu dinormalisasi di server. Aksi start/finish tidak menerima timestamp atau status bebas dari browser.

Untuk Blade gunakan redirect dan flash message sesuai pola proyek. Untuk request JSON gunakan status sukses 201 untuk create, 200 untuk aksi/read; 422 untuk validasi; 403 untuk peran terlarang; 404 untuk objek tidak ada atau patroli Ranger lain; 409 untuk konflik transisi atau patroli lain masih aktif. Request belum login mengikuti kontrak autentikasi proyek: redirect login untuk web atau 401 untuk JSON. Jangan mengembalikan metadata patroli orang lain pada error.

### 7.4 Titik integrasi

**[Usulan teknis]** Service/domain action setara `AssignPatrol`, `StartPatrol`, `FinishPatrol` memusatkan aturan agar web dan sinkronisasi mendatang tidak berbeda perilaku.

Event setara `PatrolAssigned(patrol_id, ranger_id, created_by)`, `PatrolStarted(patrol_id, ranger_id, started_at)`, dan `PatrolCompleted(patrol_id, ranger_id, ended_at)` boleh disediakan setelah commit. Nama disesuaikan repository. Jangan membuat kontrak event wajib jika proyek telah memiliki mekanisme lain yang setara.

Notifikasi penugasan adalah hasil akhir UC-13, tetapi implementasi kanal/database notification berada di PBI-17. Jika belum tersedia, gunakan titik integrasi yang teruji dan tandai penerimaan notifikasi sebagai tertunda integrasi. Jangan membuat inbox duplikat.

## 8. UI/UX dan keadaan sistem

- Ikuti layout mockup: Admin memiliki sidebar, tabel patroli dan form di sisi kanan; Ranger memakai kartu dan navigasi ponsel. Reuse tema serta design system repository.
- Tabel Admin minimal memuat kode, Ranger, area, jadwal, status, jarak jika tersedia, serta akses detail.
- Detail Ranger minimal memuat kode/area, jadwal, badge status, waktu aktual, durasi, daftar checkpoint berurutan, serta tombol sesuai lifecycle.
- Peta, jarak, jumlah titik GPS, dan tombol catat checkpoint hanya aktif jika PBI pemiliknya telah terintegrasi. Jangan menampilkan angka demo atau tombol seolah sudah berfungsi.
- Status disampaikan dengan teks selain warna. Gunakan label form, error yang terhubung ke field, fokus keyboard, dan konfirmasi yang dapat dioperasikan tanpa mouse.
- **[Keputusan PRD]** Verifikasi layout ponsel sekitar 360 px dan desktop sekitar 1280 px. Tabel boleh scroll horizontal; aksi utama tidak boleh terpotong.
- Sediakan loading, empty, validation error, forbidden, konflik status, dan gangguan server. Saat gagal submit, input valid dipertahankan.
- Gunakan eager loading dan pagination untuk target NFR-02 ≤ 3 detik. Laporkan lingkungan/data uji jika mengukur performa; jangan mengklaim target tercapai tanpa pengukuran.

Contoh pesan:

| Kondisi | Pesan |
| --- | --- |
| Penugasan berhasil | `Patroli {kode} berhasil ditugaskan kepada {nama}.` |
| Tidak ada checkpoint dipilih | `Pilih minimal satu checkpoint.` |
| Checkpoint salah area | `Checkpoint yang dipilih tidak sesuai area hutan.` |
| Sudah ada patroli aktif | `Selesaikan patroli yang sedang berlangsung sebelum memulai tugas lain.` |
| Transisi tidak valid | `Status patroli sudah berubah. Muat ulang untuk melihat kondisi terbaru.` |
| Kunjungan belum lengkap | `Masih ada {n} checkpoint belum dikunjungi. Patroli tetap dapat diakhiri.` |
| Server belum mengonfirmasi | `Perubahan belum dapat dikonfirmasi. Periksa kembali status patroli setelah koneksi tersedia.` |

## 9. Acceptance criteria

Setiap ID harus dapat dipetakan ke bukti pengujian. Ketentuan bertanda integrasi tidak dianggap selesai hanya dengan mock UI.

### 9.1 PBI-05

| ID | Given | When | Then |
| --- | --- | --- | --- |
| AC05-01 | Admin login dan master tersedia | Membuka Patroli | Daftar dan form penugasan tampil; pilihan hanya Ranger/area yang valid. |
| AC05-02 | Form terbuka | Memilih/mengganti area | Hanya checkpoint aktif area tersebut tampil; pilihan area sebelumnya dihapus. |
| AC05-03 | Input lengkap dan valid | Submit | Satu patroli Ditugaskan dibuat dengan kode unik, pembuat dari sesi, Ranger/area/jadwal benar, dan seluruh rencana checkpoint berurutan. |
| AC05-04 | Input wajib kosong atau checkpoint kosong | Submit | Validasi menolak, error field tampil, tidak ada patroli/checkpoint baru. |
| AC05-05 | Payload berisi checkpoint duplikat, salah area, dihapus, atau Ranger tidak valid/nonaktif | Submit | Server menolak meskipun payload dimanipulasi di luar UI. |
| AC05-06 | Master berubah sejak form dibuka | Submit | Server tidak menyimpan penugasan dengan pilihan yang kini tidak valid. |
| AC05-07 | Penyimpanan salah satu checkpoint gagal | Transaksi dijalankan | Seluruh perubahan rollback; tidak ada penugasan parsial. |
| AC05-08 | Ranger/Citizen/belum login | Mengakses endpoint Admin atau submit langsung | Akses ditolak sesuai kontrak autentikasi; tidak ada perubahan data. |
| AC05-09 | Penugasan sukses | Ranger tujuan membuka daftar | Tugas muncul hanya pada Ranger tujuan, dengan jadwal dan checkpoint yang benar. |
| AC05-10 | Penugasan commit/rollback | Mekanisme integrasi dijalankan | Pemberitahuan penugasan hanya dipicu untuk commit; tidak dipicu pada rollback. Penerimaan notifikasi nyata menunggu PBI-17. |

### 9.2 PBI-06

| ID | Given | When | Then |
| --- | --- | --- | --- |
| AC06-01 | Dua Ranger memiliki tugas berbeda | Ranger A membuka daftar/detail | Hanya tugas milik A terlihat; URL tugas B tidak membuka detail. |
| AC06-02 | Tugas Ditugaskan milik sendiri dan tidak ada patroli aktif | Menekan Mulai | Status Berlangsung, waktu mulai server tersimpan, waktu selesai null, detail aktif tampil. |
| AC06-03 | Patroli sudah Berlangsung | Reload/login ulang | Status, waktu mulai, dan durasi terpulihkan dari server tanpa membuat patroli baru. |
| AC06-04 | Ranger mempunyai satu patroli Berlangsung | Memulai tugas lain | Ditolak dan tugas kedua tetap Ditugaskan. |
| AC06-05 | Dua start untuk tugas berbeda dari Ranger sama tiba bersamaan | Server memproses keduanya | Tepat satu tugas menjadi Berlangsung; request lain konflik. |
| AC06-06 | Patroli Berlangsung milik sendiri | Konfirmasi Akhiri | Status Selesai, waktu selesai terisi, waktu mulai tetap, durasi akhir benar. |
| AC06-07 | Ada checkpoint belum dikunjungi | Konfirmasi Akhiri | Penyelesaian diizinkan; kunjungan yang belum ada tetap null dan ditampilkan belum dikunjungi. |
| AC06-08 | Patroli Ditugaskan/Selesai | Mengirim finish, atau start ulang pada Selesai | Ditolak tanpa mengubah status/waktu aktual. |
| AC06-09 | Start/finish telah berhasil | Request sama dikirim ulang | Konflik dikembalikan; timestamp pertama tidak ditimpa. |
| AC06-10 | Admin/Citizen/Ranger lain | Mengirim start/finish untuk tugas | Ditolak server; tidak ada mutasi. |
| AC06-11 | Patroli aktif pernah dimulai | Waktu berlalu lalu halaman di-refresh | Durasi berasal dari timestamp tersimpan, bukan timer yang dimulai ulang. |
| AC06-12 | Koneksi gagal atau timeout | Pengguna mencoba mutasi | UI tidak mengklaim sukses; setelah reconnect kondisi terbaru dibaca sebelum retry. |
| AC06-13 | Penugasan valid mengacu master yang kemudian soft-deleted | Membuka dan menjalankan tugas | Detail historis tetap terbaca dan lifecycle tidak gagal hanya karena relasi default menyembunyikan master. |

## 10. Rencana pengujian

**Wajib dari proposal:** Laravel Dusk untuk alur utama setiap PBI. Tambahkan feature/service test untuk validasi, policy, transaksi, dan concurrency yang lebih tepat diuji di server. Jangan menggantikan Dusk seluruhnya dengan unit test.

Fixture minimal: satu Admin, Ranger A dan B aktif, satu Ranger nonaktif, satu Citizen, dua area dengan beberapa checkpoint berbeda, satu checkpoint soft-deleted, tugas Ditugaskan, tugas Berlangsung, serta tugas Selesai. Gunakan database test terisolasi.

| Jenis | Skenario utama | Bukti yang diharapkan |
| --- | --- | --- |
| Dusk PBI-05 | Login Admin → buat penugasan → login Ranger tujuan → lihat tugas/detail. | Form, status, jadwal, urutan, dan isolasi tugas sesuai AC. |
| Dusk validasi | Ubah area, kosongkan jadwal/checkpoint, submit invalid. | Pilihan usang hilang; error tampil; data tidak bertambah. |
| Dusk PBI-06 | Mulai → reload → akhiri → buka detail selesai. | Timestamp persist, aksi sesuai status, hasil akhir read-only. |
| Dusk patroli aktif | Saat satu patroli berjalan, coba mulai tugas lain. | Penolakan dan tautan menuju patroli aktif. |
| Dusk kunjungan | Selesaikan patroli dengan checkpoint belum lengkap. | Konfirmasi tampil; selesai diizinkan; data kunjungan tidak dipalsukan. |
| Feature/security | Peran salah, IDOR, tampering field, master invalid/soft-deleted. | Tidak ada kebocoran data dan mutasi terlarang. |
| Feature/transaction | Simulasikan kegagalan saat insert checkpoint. | Tidak ada data parsial maupun event penugasan terpublikasi. |
| Integration/concurrency | Dua start berbeda untuk Ranger sama melalui koneksi/transaksi terpisah. | Hanya satu Berlangsung. Pakai MySQL/MariaDB; SQLite tidak cukup membuktikan row-lock produksi. |
| Feature/time | Freeze waktu untuk mulai, lanjutkan waktu, akhiri. | Waktu dan durasi benar, retry tidak menimpa timestamp. |
| Responsif | Ponsel dan desktop; keyboard dan error state. | Aksi utama terbaca/dapat digunakan. |

Nama test mengikuti repository. Jika browser/database test belum dapat dijalankan, laporkan blocker dan perintah yang dicoba; jangan menyatakan pengujian lulus.

## 11. Urutan implementasi dan hasil handoff

1. Audit dependency, mapping ERD, status, dan konvensi proyek.
2. Tambahkan hanya migration/constraint yang belum tersedia dan diperlukan; jangan menghapus data lama.
3. Implementasikan policy, validasi, service transaksi, dan proteksi satu patroli aktif.
4. Selesaikan halaman Admin PBI-05 dan pengujian penugasan.
5. Selesaikan halaman Ranger PBI-06 dan pengujian lifecycle.
6. Pasang titik integrasi PBI-07/08/17/18–20 sesuai kesiapan modul, tanpa mengambil alih implementasinya.
7. Jalankan Dusk, feature test relevan, dan pemeriksaan format/lint yang diwajibkan proyek; tinjau regresi akses pengguna.
8. Siapkan ringkasan implementasi dan petunjuk demonstrasi untuk reviewer.

Deliverable implementasi yang diharapkan:

- Model/relasi, migration tambahan bila perlu, request validation, authorization, dan logic lifecycle.
- Route/controller serta Blade/JavaScript sesuai pola repository.
- Factory/seeder untuk demo/test yang terisolasi dari data produksi.
- Pengujian Dusk dan pengujian server relevan, beserta hasil aktualnya.
- Dokumentasi setup, migration, kontrak yang dipakai, dan status integrasi PBI lain.

## 12. Definition of Done

- [ ] Semua acceptance criteria inti PBI-05 dan PBI-06 terpenuhi dan memiliki bukti.
- [ ] Pengujian Dusk fitur lulus dan pengujian server relevan lulus.
- [ ] Isolasi akses Admin/Ranger/Citizen ditegakkan di server.
- [ ] Pembuatan penugasan atomik dan dua patroli aktif untuk Ranger sama tidak mungkin terjadi pada jalur yang diimplementasikan.
- [ ] Timestamp tidak berubah akibat request ganda; reload mempertahankan kondisi.
- [ ] UI mengikuti mockup dan dapat digunakan pada ponsel/desktop.
- [ ] Tidak ada data GPS, jarak, kunjungan, atau keberhasilan offline palsu.
- [ ] Integrasi yang belum tersedia disebutkan eksplisit. End-to-end notifikasi/offline tidak diklaim selesai sebelum PBI pemiliknya siap.
- [ ] Perubahan diajukan melalui pull request, ditinjau anggota lain, dan digabung ke branch utama sesuai DoD proposal. Agent tidak melakukan merge tanpa otorisasi yang berlaku.
- [ ] Fitur dapat didemonstrasikan pada Sprint Review.

## 13. Keputusan turunan dan hal yang perlu dicocokkan

Bagian ini tidak menghambat implementasi dengan default di atas, tetapi harus muncul dalam catatan handoff agar tim mengetahui keputusan yang bukan berasal langsung dari proposal.

| Topik | Default dokumen | Yang perlu dicocokkan |
| --- | --- | --- |
| Status internal | `assigned`, `in_progress`, `completed`. | Enum/konstanta pada PBI-01. |
| Urutan checkpoint | `code`, lalu `id`; subset diberi sequence mulai 1. | Urutan resmi pada master bila sudah disepakati. |
| Jadwal lampau/awal | Diizinkan; tidak ada time gate. | Kebijakan operasional bila nanti dibutuhkan. |
| Zona waktu | UTC untuk simpan; timezone aplikasi untuk tampil; fallback Asia/Jakarta. | Konfigurasi proyek dan lokasi operasi. |
| Perubahan master | Master dihapus tidak dapat dipilih baru; relasi historis tetap terbaca. | Implementasi soft delete PBI-04. |
| Kode patroli | Generator unik mengikuti repository. | Format kode resmi jika tersedia. |
| Notifikasi | Titik integrasi setelah commit. | Kesiapan listener/channel PBI-17. |
| Offline | Disiapkan agar logic dapat dipakai ulang; belum diimplementasikan pada dua PBI ini. | Kontrak waktu kejadian, UUID, urutan mutasi, dan konflik lintas perangkat PBI-18–20. |
| Ubah urutan/batal | Tidak diimplementasikan. | UC-13 A2 masih usulan, bukan baseline MVP dua PBI ini. |

## 14. Prompt singkat untuk agent implementasi

> Baca PRD ini dan panduan repository, lalu implementasikan PBI-05 dan PBI-06 ForestGuard menggunakan struktur proyek yang tersedia. Mulai dengan audit dependency dan mapping ERD/status. Terapkan penugasan Admin dengan minimal satu checkpoint valid, lifecycle Ditugaskan → Berlangsung → Selesai, isolasi tugas Ranger, transaksi atomik, dan proteksi concurrent start agar satu Ranger hanya memiliki satu patroli berjalan. Ikuti acceptance criteria serta pengujian Dusk. Jangan memperluas pekerjaan ke GPS, pencatatan kunjungan, notifikasi lengkap, offline, atau riwayat/peta lengkap; integrasikan hanya jika modulnya sudah tersedia. Laporkan perubahan, hasil test aktual, keputusan turunan, dan dependency yang masih tertunda.
