## Deskripsi Perubahan
<!-- Jelaskan secara singkat fitur atau perbaikan yang dikerjakan -->

## Referensi PBI / Task
- PBI: <!-- Contoh: PBI-01, PBI-02 -->
- Tipe: [ ] Feature [ ] Bugfix [ ] Chore [ ] Docs

## Checklist Definition of Done (DoD)
- [ ] `composer install && npm install && php artisan migrate:fresh --seed` berjalan bersih
- [ ] Semua pengujian PHPUnit (`php artisan test`) lulus
- [ ] Semua pengujian Dusk (`php artisan dusk`) lulus
- [ ] Kode bersih dari pelanggaran Pint (`vendor/bin/pint --format agent`)
- [ ] Build aset Vite (`npm run build`) berhasil tanpa error
- [ ] Log aplikasi bersih dari pesan error/exception
- [ ] Alur use case terpenuhi sesuai spesifikasi
- [ ] Responsif pada tampilan mobile (360px) dan desktop
- [ ] Dokumentasi dan keputusan arsitektur diperbarui (README / DECISIONS.md jika ada perubahan)
