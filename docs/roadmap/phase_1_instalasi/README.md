# Phase 1: Instalasi dan Fondasi Project

[Roadmap](../../roadmap.md)

---

## Tujuan

Menyiapkan fondasi teknis Laravel agar fase berikutnya bisa dibangun di atas struktur yang stabil: environment, package inti, database, queue baseline, dan aturan quality check.

## Referensi

- [Overview Produk dan Konteks](../../santap-api/00-overview.md)
- [Neon PostgreSQL dan Instalasi Awal](../../santap-api/09-neon-and-installation.md)
- [Struktur Laravel, Enum, Validasi, dan Security](../../santap-api/08-laravel-implementation-rules.md)

## Scope

- Validasi versi PHP dan Laravel.
- Konfigurasi `.env` lokal.
- Koneksi Neon PostgreSQL atau database lokal setara untuk development.
- Instalasi/publish package inti:
  - Laravel Sanctum
  - Filament
  - Spatie Laravel Permission
  - Laravel Reverb
  - Laravel Horizon
  - Laravel Pulse
  - Spatie Laravel Activitylog
  - Spatie Media Library
- Baseline struktur folder aplikasi.
- Baseline route `api.php`, `channels.php`, dan admin panel provider.
- Baseline command development, linting, dan testing.

## Catatan Status Saat Ini

- Sanctum, Filament, Reverb, Pulse, Spatie Permission, Activitylog, dan Media Library sudah tercatat di `composer.json`.
- Migration Sanctum, permission, dan activity log sudah ada.
- Horizon belum terlihat di `composer.json`; perlu dipasang jika Redis queue monitoring tetap dipakai.
- Struktur domain Santap belum dibuat.

## Urutan Pengerjaan

1. Pastikan project bisa menjalankan `composer install`.
2. Pastikan `.env` memiliki konfigurasi app key, database, queue, cache, mail, broadcasting, dan storage.
3. Konfigurasi PostgreSQL:
   - development boleh memakai local PostgreSQL atau Neon direct connection.
   - migration/seeding ke Neon wajib memakai direct connection.
   - runtime production boleh memakai pooled connection.
4. Install package yang belum ada, terutama Horizon bila tetap dipakai.
5. Publish config dan migration package yang dibutuhkan.
6. Jalankan migration baseline.
7. Siapkan struktur folder:
   - `app/Actions`
   - `app/Enums`
   - `app/Http/Controllers/Api/V1`
   - `app/Http/Middleware`
   - `app/Http/Requests`
   - `app/Http/Resources`
   - `app/Policies`
   - `app/Services`
   - `app/Support`
8. Tambahkan health endpoint sederhana untuk memastikan API hidup.
9. Jalankan baseline test dan formatter.

## Deliverables

- Project Laravel bisa boot tanpa error.
- Database connection valid.
- Migration baseline berhasil.
- Package inti siap dikonfigurasi lanjut.
- Struktur folder Santap tersedia.
- Dokumentasi env awal jelas.

## Acceptance Criteria

- `php artisan about` berjalan.
- `php artisan migrate:status` bisa membaca status migration.
- `php artisan route:list` tidak error.
- `php artisan test` berjalan minimal untuk test bawaan.
- Tidak ada konfigurasi rahasia yang masuk ke git.

## Out of Scope

- Model organisasi.
- Login API.
- Filament resource.
- Endpoint bisnis restoran.

---

[Roadmap](../../roadmap.md)
