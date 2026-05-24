# 05 · Later Roadmap — Santap POS

> **Status:** Backlog Fitur — Jangan Diimplementasi Sekarang  
> **Dibuat:** 2026-05-24  
> **Prinsip:** Tambahkan hanya ketika ada kebutuhan nyata dari pengguna.

---

## Filosofi "Later"

> *"You aren't gonna need it" — YAGNI Principle*

Semua fitur di dokumen ini sengaja **tidak dimasukkan ke Phase 1–6** karena:
1. Belum ada kebutuhan nyata dari pengguna
2. Menambah kompleksitas tanpa nilai langsung
3. Bisa ditambahkan secara bertahap tanpa breaking change

---

## Kategori Fitur Later

### 🔴 NANTI — Realtime & Infrastruktur

#### Laravel Reverb / WebSocket

**Kapan dibutuhkan:** Ketika polling 10-15 detik terasa terlalu lambat dan user complaint tentang delay.

**Yang akan diubah:**
- Tambah `laravel/reverb` ke composer
- Buat event `OrderStatusUpdated`, `NewOrderPlaced`, `PaymentReceived`
- Kitchen, cashier, customer web subscribe ke channel masing-masing
- Hapus polling dari client Flutter & web

**Estimasi Effort:** 3–5 hari

---

#### Laravel Horizon

**Kapan dibutuhkan:** Ketika queue job database > 1000/hari dan butuh monitoring visual (failed job, retry, throughput).

**Yang akan diubah:**
- Ganti `QUEUE_CONNECTION=database` ke `redis`
- Install Redis (atau gunakan Upstash untuk serverless)
- Install `laravel/horizon`
- Konfigurasi worker pools per queue

**Estimasi Effort:** 1–2 hari

---

#### Laravel Pulse

**Kapan dibutuhkan:** Ketika ada masalah performa di production dan butuh insight (slow queries, memory, exceptions).

**Yang akan diubah:**
- Install `laravel/pulse`
- Tambahkan route `/pulse` di Filament atau standalone
- Konfigurasi ingest storage (database atau Redis)

**Estimasi Effort:** 1 hari

---

### 🟠 NANTI — Fitur Bisnis Penting

#### Multi-Outlet (Cabang)

**Kapan dibutuhkan:** Ketika satu organization memiliki lebih dari satu lokasi fisik.

**Schema perubahan:**
```sql
-- Tambah tabel outlets
CREATE TABLE outlets (
    id          BIGINT PRIMARY KEY,
    organization_id BIGINT FK,
    name        VARCHAR(255),
    address     TEXT,
    phone       VARCHAR(20),
    is_active   BOOLEAN DEFAULT true,
    ...
);

-- Tambah outlet_id ke:
ALTER TABLE dining_tables ADD COLUMN outlet_id BIGINT;
ALTER TABLE menus ADD COLUMN outlet_id BIGINT NULLABLE; -- null = semua outlet
ALTER TABLE orders ADD COLUMN outlet_id BIGINT;
ALTER TABLE open_bills ADD COLUMN outlet_id BIGINT;
```

**Estimasi Effort:** 3–5 hari

---

#### Menu Variants & Add-ons

**Kapan dibutuhkan:** Menu memiliki opsi (ukuran, level pedas, topping tambahan).

**Schema perubahan:**
```sql
CREATE TABLE menu_variants (
    id      BIGINT PRIMARY KEY,
    menu_id BIGINT FK,
    name    VARCHAR(100),  -- "Ukuran", "Level Pedas"
    is_required BOOLEAN DEFAULT false
);

CREATE TABLE menu_variant_options (
    id          BIGINT PRIMARY KEY,
    variant_id  BIGINT FK,
    name        VARCHAR(100),  -- "Besar", "Sangat Pedas"
    price_add   NUMERIC(12,2) DEFAULT 0
);

-- order_items.selected_variants JSONB
```

**Estimasi Effort:** 3–4 hari

---

#### Diskon & Kupon

**Kapan dibutuhkan:** Business butuh program loyalty atau promo.

**Schema perubahan:**
```sql
CREATE TABLE discounts (
    id              BIGINT PRIMARY KEY,
    organization_id BIGINT FK,
    code            VARCHAR(50) UNIQUE NULLABLE,
    name            VARCHAR(255),
    type            VARCHAR(20),  -- percentage, fixed
    value           NUMERIC(10,2),
    min_order       NUMERIC(12,2) DEFAULT 0,
    max_usage       INT NULLABLE,
    usage_count     INT DEFAULT 0,
    is_active       BOOLEAN DEFAULT true,
    starts_at       TIMESTAMP NULLABLE,
    expires_at      TIMESTAMP NULLABLE
);
```

**Estimasi Effort:** 2–3 hari

---

#### Audit Trail / Activity Log

**Kapan dibutuhkan:** Business butuh jejak siapa yang ubah apa (compliance, dispute).

**Opsi A — Custom Table (Rekomendasi):**
```sql
CREATE TABLE activity_logs (
    id              BIGINT PRIMARY KEY,
    organization_id BIGINT FK NULLABLE,
    causer_type     VARCHAR(255),  -- 'App\Models\User'
    causer_id       BIGINT,
    subject_type    VARCHAR(255),  -- 'App\Models\Order'
    subject_id      VARCHAR(255),  -- bisa UUID atau BIGINT
    event           VARCHAR(100),  -- 'created', 'updated', 'status_changed'
    description     TEXT,
    properties      JSONB NULLABLE,  -- before/after values
    created_at      TIMESTAMP
);
```

**Opsi B:** Reinstall `spatie/laravel-activitylog`

**Estimasi Effort:** 1–2 hari (Opsi A), 0.5 hari (Opsi B)

---

#### Inventory & Stok Bahan

**Kapan dibutuhkan:** Restoran butuh track bahan baku, auto-nonaktif menu saat habis.

**Schema perubahan:**
```sql
CREATE TABLE ingredients (
    id              BIGINT PRIMARY KEY,
    organization_id BIGINT FK,
    name            VARCHAR(255),
    unit            VARCHAR(50),   -- gram, ml, pcs
    stock           NUMERIC(10,2) DEFAULT 0,
    low_stock_threshold NUMERIC(10,2) DEFAULT 0
);

CREATE TABLE menu_ingredients (
    menu_id         BIGINT FK,
    ingredient_id   BIGINT FK,
    quantity_per_portion NUMERIC(10,2)
);
```

**Estimasi Effort:** 5–7 hari

---

#### Kitchen Stations (Routing Dapur)

**Kapan dibutuhkan:** Restoran besar dengan dapur terpisah (grill, cold kitchen, bar).

**Schema perubahan:**
```sql
CREATE TABLE kitchen_stations (
    id              BIGINT PRIMARY KEY,
    organization_id BIGINT FK,
    name            VARCHAR(100)  -- "Dapur Panas", "Bar", "Cold Kitchen"
);

-- Tambah ke menus:
ALTER TABLE menus ADD COLUMN kitchen_station_id BIGINT NULLABLE;

-- Tambah ke order_items:
ALTER TABLE order_items ADD COLUMN kitchen_station_id BIGINT NULLABLE;
```

**Estimasi Effort:** 2–3 hari

---

### 🟡 NANTI — Integrasi Eksternal

#### Email Transactional

**Kapan dibutuhkan:** Notifikasi email untuk invitation, struk, laporan mingguan.

**Yang akan diubah:**
- Konfigurasi mail driver (SMTP/Mailgun/SES) di `.env`
- Buat Mailable class: `InvitationMail`, `ReceiptMail`
- Tambah queue job: `SendInvitationEmail`

**Estimasi Effort:** 1–2 hari

---

#### Payment Gateway yang Lebih Lengkap

**Kapan dibutuhkan:** Ketika butuh integrasi payment lebih dari QRIS manual.

**Opsi:**
- Midtrans (recommended untuk Indonesia)
- Xendit
- Doku

**Yang akan diubah:**
- Install package gateway (e.g., `midtrans/midtrans-php`)
- Buat `PaymentGatewayService` dengan interface yang bisa di-swap
- Tambah webhook handler

**Estimasi Effort:** 3–5 hari

---

#### Push Notification (Mobile)

**Kapan dibutuhkan:** Notifikasi ke Flutter app (order baru, pesanan siap) tanpa polling.

**Opsi:**
- Firebase Cloud Messaging (FCM) via `kutia-com/firebase-php`
- OneSignal

**Yang akan diubah:**
- Simpan `fcm_token` di `users` tabel
- Buat `SendPushNotification` job
- Trigger dari event order/payment

**Estimasi Effort:** 2–3 hari

---

#### Printer Termal / Struk

**Kapan dibutuhkan:** Cashier butuh cetak struk fisik.

**Opsi:**
- Generate PDF struk (via `barryvdh/laravel-dompdf`)
- Kirim raw print command ke printer via Flutter (lebih umum untuk mobile POS)
- API endpoint yang return struk HTML/PDF

**Estimasi Effort:** 2–3 hari

---

### 🟢 NANTI — Fitur UX

#### Loyalty Program / Points

**Kapan dibutuhkan:** Business ingin retensi customer.

---

#### Customer Feedback / Rating

**Kapan dibutuhkan:** Restoran ingin tahu kepuasan customer.

---

#### Reservation / Booking Meja

**Kapan dibutuhkan:** Restoran fine dining dengan sistem reservasi.

---

#### Multi-Currency

**Kapan dibutuhkan:** Restoran melayani tourist internasional.

---

#### Laporan Advanced (PDF, Excel Export)

**Kapan dibutuhkan:** Owner butuh laporan bulanan yang bisa di-download.

**Package:** `maatwebsite/excel` atau `barryvdh/laravel-dompdf`

---

#### Media Library Lengkap

**Kapan dibutuhkan:** Ketika gambar menu butuh resize otomatis, multiple conversions, CDN.

**Package:** `spatie/laravel-medialibrary`

**Migrasi dari storage bawaan:**
- Semua file sudah di `storage/public/menus/`
- Buat media collection untuk setiap model yang butuh
- Jalankan migration command untuk import file existing

---

## Prioritas Backlog

| Prioritas | Fitur | Trigger |
|---|---|---|
| 🔴 Tinggi | Multi-Outlet | User request + 2+ org punya cabang |
| 🔴 Tinggi | Email Transactional | Invitation flow butuh email |
| 🟠 Sedang | Kitchen Stations | Restoran besar |
| 🟠 Sedang | Diskon & Kupon | Program promo pertama |
| 🟡 Rendah | Realtime WebSocket | Polling terasa lambat |
| 🟡 Rendah | Laravel Horizon | Queue job > 1000/hari |
| 🟢 Nanti | Inventory & Stok | Permintaan eksplisit dari user |
| 🟢 Nanti | Loyalty Program | Business siap dengan CRM |

---

## Cara Menambahkan Fitur "Later" ke Codebase

Saat ada fitur dari backlog ini yang sudah siap diimplementasi:

1. **Buat branch baru:** `feat/nama-fitur`
2. **Update dokumen ini:** Pindahkan fitur dari "Later" ke "In Progress"
3. **Update `03-implementation-plan.md`:** Tambahkan Phase baru
4. **Buat migration baru:** Jangan ubah migration yang sudah ada
5. **Pastikan backward compatible:** Kolom baru harus nullable atau punya default value
6. **Update API docs:** Tambahkan endpoint baru ke `04-api-endpoints-minimal.md`
7. **Test:** Endpoint baru tidak breaking existing endpoint

---

*Dokumen ini adalah living document. Update sesuai perkembangan kebutuhan bisnis.*
