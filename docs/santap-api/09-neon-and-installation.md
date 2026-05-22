# Neon PostgreSQL dan Instalasi Awal

[Indeks Santap API](../santap-api.md)

---

## 20. Neon PostgreSQL Setup

Environment dasar:

```env
DB_CONNECTION=pgsql
DB_HOST=your-neon-host
DB_PORT=5432
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password
DB_SSLMODE=require
```

Jika menggunakan `DATABASE_URL`:

```env
DATABASE_URL=postgresql://user:password@host/dbname?sslmode=require
```

Ketentuan Neon:

```txt
Migration/seeding: gunakan direct connection.
Runtime aplikasi: boleh gunakan pooled connection.
```

Untuk production, siapkan env terpisah:

```env
DATABASE_URL_DIRECT=...
DATABASE_URL_POOLED=...
```

---

## 21. Command Instalasi Awal

```bash
composer require laravel/sanctum
composer require filament/filament
composer require spatie/laravel-permission
composer require laravel/reverb
composer require laravel/horizon
composer require laravel/pulse
composer require spatie/laravel-activitylog
composer require spatie/laravel-medialibrary
```

Publish/install umum:

```bash
php artisan sanctum:install
php artisan install:broadcasting
php artisan horizon:install
php artisan pulse:install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
php artisan migrate
```

Catatan:

- Perintah bisa berubah mengikuti versi Laravel/package.
- Jalankan migration ke Neon menggunakan direct connection.

---

---

[Indeks Santap API](../santap-api.md)
