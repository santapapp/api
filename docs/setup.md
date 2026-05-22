# Setup Development Santap API

[Indeks Santap API](santap-api.md)

---

## Local Default

Setup lokal boleh memakai SQLite/database lokal agar development cepat. Untuk production dan staging, Santap ditargetkan memakai Neon PostgreSQL.

```bash
php artisan key:generate
php artisan migrate
php artisan test
```

## Composer di Windows

Project memakai Laravel Horizon. Di Windows, Composer bisa menolak install karena ekstensi Unix `ext-pcntl` dan `ext-posix` tidak tersedia. Jika itu terjadi, jalankan install dengan:

```bash
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

Horizon tetap sebaiknya dijalankan di environment Linux/production yang memiliki ekstensi tersebut.

## Neon PostgreSQL

Gunakan direct connection untuk migration/seeding dan pooled connection untuk runtime production jika diperlukan.

```env
DB_CONNECTION=pgsql
DATABASE_URL=postgresql://user:password@host/dbname?sslmode=require
DB_SSLMODE=require
```

## Queue dan Realtime

Default lokal boleh memakai database queue. Saat memakai Horizon, gunakan Redis:

```env
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb
```

---

[Roadmap Phase 1](roadmap/phase_1_instalasi/)
