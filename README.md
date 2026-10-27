# ForestGuard (Sustainable Forest Patrol & Forest Protection System)

ForestGuard adalah aplikasi web berbasis Laravel dengan pendekatan Progressive Web App (PWA) yang dirancang untuk mendukung patroli konservasi, pelaporan insiden perusakan hutan, dan pengawasan kawasan lindung guna mendukung pencapaian SDG 15 (Life on Land).

Aplikasi ini melayani tiga peran utama:
- **Forest Manager / Admin**: Mengelola data master kawasan, penugasan patroli, verifikasi laporan insiden, dan pemantauan dasbor GIS.
- **Ranger**: Petugas lapangan yang melakukan patroli berkala, perekaman checkpoint & track GPS, observasi kondisi hutan, dan investigasi lapangan.
- **Citizen (Warga)**: Masyarakat yang melaporkan insiden lingkungan (kebakaran, illegal logging, perambahan, perburuan satwa) dan memantau status penanganannya.

---

## 🛠️ Stack Teknologi

- **Backend**: PHP 8.2+ (PHP 8.4 didukung), Laravel 11/12/13 (MVC murni tanpa React/Vue/Livewire/Inertia/Filament).
- **Frontend**: Blade Components, Tailwind CSS (via Vite), Vanilla JavaScript.
- **Basis Data**: PostgreSQL 15+ melalui [Supabase](https://supabase.com/) (lokal CLI atau proyek hosted).
- **Pengujian**: PHPUnit (Feature & Unit tests) dan Laravel Dusk (Headless Chrome browser automation).
- **Gaya Kode**: PSR-12, diformat rapi dengan Laravel Pint.
- **Lokalisasi & Waktu**: Locale `id` (Bahasa Indonesia), Timezone `Asia/Jakarta`.

---

## 🚀 Panduan Setup Lokal

### 1. Prasyarat Sistem
Pastikan perangkat Anda telah terpasang:
- PHP >= 8.2 (dengan ekstensi `pdo_pgsql`, `mbstring`, `xml`, `curl`, `bcmath`, `fileinfo`)
- Composer >= 2.x
- Node.js >= 18.x dan npm
- Docker Desktop (untuk `npx supabase start`)
- Google Chrome browser (untuk Laravel Dusk)

### 2. Kloning dan Instalasi Dependensi
```bash
git clone <url-repository>
cd ForestGuard

# Pasang dependensi PHP
composer install

# Pasang dependensi Frontend
npm install
```

### 3. Konfigurasi Lingkungan (.env)
Salin berkas contoh konfigurasi lingkungan:
```bash
cp .env.example .env
php artisan key:generate
```

Jalankan instance Supabase lokal (PostgreSQL pada port `54322`):
```bash
npx supabase start
```

Buat basis data terpisah untuk pengujian:
```bash
docker exec supabase_db_ForestGuard psql -U postgres -c "CREATE DATABASE forestguard_test;"
docker exec supabase_db_ForestGuard psql -U postgres -c "CREATE DATABASE forestguard_dusk;"
```

Kredensial default lokal sudah tercantum di `.env.example`. Untuk proyek hosted, isi host `db.<project-ref>.supabase.co`, password database dari dashboard, dan `DB_SSLMODE=require`.

### 4. Migrasi dan Seeding Basis Data
Jalankan migrasi skema lengkap dan seeder idempotent:
```bash
php artisan migrate:fresh --seed
```

### 5. Menjalankan Server Pengembangan
Jalankan Vite (kompilasi aset Tailwind CSS):
```bash
npm run dev
```

Di terminal terpisah, jalankan server Laravel:
```bash
php artisan serve
```
Akses aplikasi melalui peramban di: [http://localhost:8000](http://localhost:8000).

---

## 👥 Akun Pengguna Bawaan (Seeder)

Semua akun dev di bawah ini menggunakan kata sandi: `Password123!`

| Peran | Nama | Email | Halaman Awal |
|---|---|---|---|
| **Admin** | Admin Hutan | `admin@gmail.com` | Dasbor (`/admin/dashboard`) |
| **Ranger** | Ranger Utama / Satu | `ranger@gmail.com` / `ranger1@gmail.com` | Beranda Ranger (`/ranger`) |
| **Ranger** | Ranger Dua | `ranger2@gmail.com` | Beranda Ranger (`/ranger`) |
| **Citizen (Warga)** | Warga Utama / Satu | `user@gmail.com` / `citizen@gmail.com` | Laporan Saya (`/citizen/laporan`) |
| **Citizen (Warga)** | Warga Dua | `citizen2@gmail.com` | Laporan Saya (`/citizen/laporan`) |

---

## 🧪 Menjalankan Pengujian Otomatis

### 1. PHPUnit (Feature & Unit Tests)
Feature tests menggunakan basis data PostgreSQL terpisah `forestguard_test` (dikonfigurasi pada `phpunit.xml`):
```bash
php artisan test
```
Atau jalankan spesifik suite:
```bash
php artisan test --compact tests/Feature/Pbi01SetupAndDatabaseTest.php
php artisan test --compact tests/Feature/Pbi02AuthAndRbacTest.php
```

### 2. Laravel Dusk (End-to-End Browser Tests)
Dusk menggunakan basis data terpisah `forestguard_dusk` (dikonfigurasi pada `.env.dusk.local`) dan Chrome headless.

Langkah menjalankan Dusk:
1. Migrasi basis data Dusk terlebih dahulu:
   ```bash
   php artisan migrate:fresh --seed --env=dusk.local
   ```
2. Jalankan aplikasi pada port 8000 dengan lingkungan `dusk.local`:
   ```bash
   php artisan serve --env=dusk.local --port=8000
   ```
3. Di terminal terpisah, jalankan Dusk:
   ```bash
   php artisan dusk
   ```

---

## 🧹 Pemeriksaan Gaya Kode (Laravel Pint)

Proyek ini menggunakan Laravel Pint untuk memastikan keselarasan format kode:
```bash
vendor/bin/pint --format agent
```

---

## 🌿 Alur Kerja Git & Percabangan

- `main`: Branch produksi, kode stabil siap rilis.
- `develop`: Branch integrasi utama sprint.
- `feature/pbi-xx-nama`: Branch pengerjaan fitur individual (contoh: `feature/pbi-01-setup`, `feature/pbi-02-auth`).
- Format commit: **Conventional Commits** (`feat:`, `fix:`, `chore:`, `test:`, `docs:`).

---

## 📖 Dokumentasi Arsitektur

Keputusan teknis, batasan skema, dan catatan penting bagi pengembang (termasuk aturan timestamps, CHECK constraint `evidences`, dan penanganan tabel kustom `notifications` untuk PBI-17) dapat dibaca di [docs/DECISIONS.md](docs/DECISIONS.md).
