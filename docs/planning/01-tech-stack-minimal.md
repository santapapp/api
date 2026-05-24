# 01 · Tech Stack Minimal — Santap POS Backend

> **Status:** Rencana Teknis · Belum Dieksekusi  
> **Dibuat:** 2026-05-24  
> **Target:** Laravel 13 + Filament 5 + Stack Minimal

---

## Ringkasan Keputusan

Santap pada tahap awal adalah aplikasi POS multi-organisasi dengan kebutuhan:
- REST API untuk Flutter (owner, cashier, kitchen)
- Public web untuk customer (tanpa login, via session)
- Admin panel internal (Filament)

**Prinsip Utama:** Start minimal, scale ketika benar-benar butuh.

---

## 1. Analisa Package

### ✅ WAJIB — Harus Ada

| Package | Alasan |
|---|---|
| `laravel/framework ^13.x` | Core framework |
| `laravel/sanctum ^4.x` | Auth token untuk Flutter + customer session |
| `filament/filament ^5.x` | Admin panel internal |
| `spatie/laravel-permission ^7.x` | RBAC multi-team (roles per organization) |

### ⚠️ OPSIONAL — Bisa Ditambah Nanti

| Package | Kapan Dibutuhkan |
|---|---|
| `laravel/horizon` | Ketika queue job sangat banyak & perlu monitoring visual |
| `spatie/laravel-activitylog` | Ketika audit trail bisnis menjadi kebutuhan compliance |
| `spatie/laravel-medialibrary` | Ketika upload gambar butuh konversi/resize/CDN |
| `laravel/pulse` | Ketika perlu monitoring performa aplikasi production |
| `laravel/reverb` | Ketika realtime push notification benar-benar diperlukan |

### ❌ HARUS DIHAPUS SEKARANG

| Package | Alasan Dihapus |
|---|---|
| `laravel/horizon` | Queue sync/database sudah cukup untuk fase awal |
| `laravel/pulse` | Monitoring belum dibutuhkan, overhead setup tinggi |
| `laravel/reverb` | Cashier/kitchen pakai polling sederhana dulu |
| `spatie/laravel-activitylog` | Simple log ke tabel sendiri jika butuh audit trail |
| `spatie/laravel-medialibrary` | Upload gambar menu pakai Laravel Storage bawaan |

---

## 2. `composer.json` Rekomendasi (Minimal)

```json
{
    "$schema": "https://getcomposer.org/schema.json",
    "name": "santap/api",
    "type": "project",
    "description": "Santap POS — Backend API & Admin Panel",
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "filament/filament": "^5.6",
        "laravel/framework": "^13.8",
        "laravel/sanctum": "^4.0",
        "laravel/tinker": "^3.0",
        "spatie/laravel-permission": "^7.4"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pail": "^1.2",
        "laravel/pint": "^1.27",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^12.5"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185\" \"php artisan serve\" \"php artisan queue:work --sleep=3 --tries=3\" \"php artisan pail\" --names=server,queue,logs --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi",
            "@php artisan filament:upgrade"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ]
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

---

## 3. Rencana Migrasi dari Stack Lama ke Stack Minimal

### Langkah 1 — Backup & Branch Baru

```bash
git checkout -b feat/minimal-stack
```

### Langkah 2 — Hapus Package dari composer.json

```bash
composer remove laravel/horizon laravel/pulse laravel/reverb \
    spatie/laravel-activitylog spatie/laravel-medialibrary --no-update

composer update
```

### Langkah 3 — Bersihkan Kode yang Bergantung pada Package

1. **Activitylog** — Hapus `use LogsActivity` dan `getActivitylogOptions()` dari semua model:
   - `app/Models/Order.php`
   - `app/Models/OpenBill.php`
   - `app/Models/Organization.php`
   - *(cari semua: `grep -r "LogsActivity" app/`)*

2. **MediaLibrary** — Hapus dari `Organization.php`:
   - Hapus `implements HasMedia`
   - Hapus `use InteractsWithMedia`
   - Ganti upload gambar dengan `Storage::disk('public')->put(...)`

3. **Horizon** — Hapus referensi dari routes (`/horizon`) dan config

4. **Pulse** — Hapus referensi dari routes (`/pulse`) dan config

5. **Reverb** — Hapus `channels.php` jika tidak dipakai, bersihkan broadcast config

### Langkah 4 — Hapus Migration Package Lama

Migration berikut **JANGAN dijalankan di database baru**, dan bisa dihapus dari folder:
- `2026_05_21_143201_create_activity_log_table.php`
- `2026_05_21_143202_add_event_column_to_activity_log_table.php`
- `2026_05_21_143203_add_batch_uuid_column_to_activity_log_table.php`
- `2026_05_21_171227_create_pulse_tables.php`
- `2026_05_21_171232_create_media_table.php`
- `2026_05_23_052700_alter_activity_log_subject_id_to_string.php`

> ⚠️ Jika database sudah berjalan di production, jangan hapus migration. Buat migration baru untuk `drop table` yang tidak dipakai.

### Langkah 5 — Konfigurasi Queue

Di `.env`:

```env
QUEUE_CONNECTION=database
```

Di `config/queue.php` pastikan driver `database` aktif. Jalankan queue worker:

```bash
php artisan queue:work --sleep=3 --tries=3
```

Untuk development/testing, bisa pakai `QUEUE_CONNECTION=sync` agar job langsung dieksekusi.

### Langkah 6 — Upload Gambar via Storage Bawaan

```php
// Di MenuController (contoh):
$path = $request->file('image')->store('menus', 'public');
$menu->update(['image' => $path]);

// URL:
Storage::disk('public')->url($menu->image);
```

Pastikan symlink public sudah dibuat:

```bash
php artisan storage:link
```

---

## 4. Environment Variables yang Dibutuhkan

```env
# App
APP_NAME=Santap
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=<neon-host>
DB_PORT=5432
DB_DATABASE=santap
DB_USERNAME=<user>
DB_PASSWORD=<password>
DB_SSLMODE=require

# Queue (database driver)
QUEUE_CONNECTION=database

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000

# Filament
FILAMENT_FILESYSTEM_DISK=public

# Storage
FILESYSTEM_DISK=local
```

---

## 5. Catatan Penting

- **Tidak ada realtime** — cashier/kitchen/customer menggunakan polling HTTP sederhana (pull, bukan push).
- **Tidak ada activity log otomatis** — jika butuh audit trail sederhana, tambahkan kolom `created_by`, `updated_by` langsung di tabel relevan.
- **Upload gambar** — gunakan `Storage::disk('public')`, simpan path relatif di database.
- **Queue** — gunakan `database` driver untuk background job (email notifikasi, dll). Jangan pakai Redis/Horizon di fase awal.
- **Neon PostgreSQL** — pastikan `DB_SSLMODE=require` dan connection pooling menggunakan Neon Pooler endpoint jika traffic tinggi.

---

## 6. Later — Yang Bisa Ditambah Saat Butuh

| Fitur | Package | Kapan |
|---|---|---|
| Queue monitoring visual | `laravel/horizon` | Ketika queue job > 1000/hari |
| App performance monitoring | `laravel/pulse` | Ketika ada masalah performa |
| Realtime notification | `laravel/reverb` atau Pusher | Ketika polling tidak cukup cepat |
| Audit trail bisnis | `spatie/laravel-activitylog` | Ketika compliance/audit dibutuhkan |
| Image processing & CDN | `spatie/laravel-medialibrary` | Ketika ada kebutuhan thumbnail/resize |
| Email transactional | `laravel/mail` + Mailgun/SES | Ketika notifikasi email dibutuhkan |
