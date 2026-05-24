# 02 · Database Design Minimal — Santap POS

> **Status:** Rencana Teknis · Belum Dieksekusi  
> **Dibuat:** 2026-05-24  
> **Database:** PostgreSQL (Neon)

---

## Prinsip Desain Database

1. **Satu database, banyak organisation** — semua data ter-scope ke `organization_id`
2. **Minimal kolom** — hanya apa yang benar-benar dibutuhkan sekarang
3. **UUID untuk entitas publik** — yang diakses via QR pakai UUID, yang internal pakai BIGINT
4. **Soft delete** — hanya pada entitas penting (menu, tabel meja)
5. **Enum via PHP** — status menggunakan PHP Enum, bukan tipe ENUM PostgreSQL

---

## Diagram Relasi Antar Tabel

```
users
 └──< organization_members >──┐
                               organizations
                               └──< outlets (later)
                               └──< dining_tables
                                    └──< table_qr_codes
                               └──< menu_categories
                                    └──< menus
                               └──< open_bills
                                    └──< customer_sessions
                                    └──< orders
                                         └──< order_items >── menus
                                    └──< payments

spatie_roles/permissions (team-aware, team = organization_id)
```

---

## Daftar Tabel

### 1. `users`

```sql
id              BIGINT PK
name            VARCHAR(255)
email           VARCHAR(255) UNIQUE
password        VARCHAR(255)
phone           VARCHAR(20) NULLABLE
avatar          VARCHAR(500) NULLABLE  -- path relatif storage
status          VARCHAR(20) DEFAULT 'active'  -- active, suspended
last_login_at   TIMESTAMP NULLABLE
email_verified_at TIMESTAMP NULLABLE
remember_token  VARCHAR(100) NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `email`

---

### 2. `organizations`

```sql
id              BIGINT PK
uuid            UUID UNIQUE NOT NULL     -- digunakan di URL publik
name            VARCHAR(255)
slug            VARCHAR(255) UNIQUE      -- slug untuk URL customer web
code            VARCHAR(50) NULLABLE     -- kode internal opsional
logo            VARCHAR(500) NULLABLE    -- path relatif storage
phone           VARCHAR(20) NULLABLE
email           VARCHAR(255) NULLABLE
address         TEXT NULLABLE
city            VARCHAR(100) NULLABLE
timezone        VARCHAR(100) DEFAULT 'Asia/Jakarta'
currency        VARCHAR(10) DEFAULT 'IDR'
status          VARCHAR(20) DEFAULT 'active'  -- active, suspended, inactive
settings        JSONB NULLABLE           -- konfigurasi tambahan (tax rate, service charge, dll)
created_by      BIGINT FK -> users.id
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `slug`, `uuid`, `created_by`

---

### 3. `organization_members`

Pivot tabel antara users dan organizations. Menyimpan role yang dipakai di scope organisasi ini.

```sql
id              BIGINT PK
organization_id BIGINT FK -> organizations.id
user_id         BIGINT FK -> users.id
role_name       VARCHAR(50)    -- 'owner', 'cashier', 'kitchen'
status          VARCHAR(20) DEFAULT 'active'  -- active, suspended, invited
joined_at       TIMESTAMP NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP

UNIQUE(organization_id, user_id)
```

**Index:** `organization_id`, `user_id`

---

### 4. `organization_invitations`

```sql
id              BIGINT PK
organization_id BIGINT FK -> organizations.id
email           VARCHAR(255)
role_name       VARCHAR(50)
token           VARCHAR(255) UNIQUE   -- token yang dikirim via email/link
status          VARCHAR(20) DEFAULT 'pending'  -- pending, accepted, expired
invited_by      BIGINT FK -> users.id
accepted_by     BIGINT FK -> users.id NULLABLE
expires_at      TIMESTAMP
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `token`, `email`, `organization_id`

---

### 5. `dining_tables` (meja makan)

```sql
id              BIGINT PK
organization_id BIGINT FK -> organizations.id
name            VARCHAR(100)     -- "Meja 1", "VIP 1", "Bar"
capacity        SMALLINT DEFAULT 4
status          VARCHAR(20) DEFAULT 'available'  -- available, occupied, reserved
is_active       BOOLEAN DEFAULT true
deleted_at      TIMESTAMP NULLABLE    -- soft delete
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `organization_id`, `status`

---

### 6. `table_qr_codes`

QR code diregenerasi setiap saat cashier request. Token disimpan, bukan URL penuh.

```sql
id              BIGINT PK
dining_table_id BIGINT FK -> dining_tables.id
token           VARCHAR(255) UNIQUE    -- UUID token yang di-encode ke QR
status          VARCHAR(20) DEFAULT 'active'  -- active, revoked
generated_by    BIGINT FK -> users.id NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `token`, `dining_table_id`, `status`

---

### 7. `menu_categories`

```sql
id              BIGINT PK
organization_id BIGINT FK -> organizations.id
name            VARCHAR(100)
description     TEXT NULLABLE
sort_order      SMALLINT DEFAULT 0
is_active       BOOLEAN DEFAULT true
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `organization_id`, `sort_order`

---

### 8. `menus`

```sql
id              BIGINT PK
organization_id BIGINT FK -> organizations.id
menu_category_id BIGINT FK -> menu_categories.id
name            VARCHAR(255)
description     TEXT NULLABLE
price           NUMERIC(12,2) NOT NULL
image           VARCHAR(500) NULLABLE    -- path relatif storage
status          VARCHAR(20) DEFAULT 'available'  -- available, unavailable, hidden
sort_order      SMALLINT DEFAULT 0
deleted_at      TIMESTAMP NULLABLE    -- soft delete
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `organization_id`, `menu_category_id`, `status`

---

### 9. `open_bills`

Open bill adalah sesi makan yang bisa berisi banyak order. Dibuat oleh cashier.

```sql
id              BIGINT PK
uuid            UUID UNIQUE NOT NULL       -- digunakan di QR open bill
organization_id BIGINT FK -> organizations.id
dining_table_id BIGINT FK -> dining_tables.id NULLABLE
bill_number     VARCHAR(50) UNIQUE         -- format: BILL-20260524-0001
status          VARCHAR(20) DEFAULT 'open'  -- open, closed
subtotal_amount NUMERIC(12,2) DEFAULT 0
discount_amount NUMERIC(12,2) DEFAULT 0
service_amount  NUMERIC(12,2) DEFAULT 0
tax_amount      NUMERIC(12,2) DEFAULT 0
total_amount    NUMERIC(12,2) DEFAULT 0
opened_by       BIGINT FK -> users.id
closed_by       BIGINT FK -> users.id NULLABLE
opened_at       TIMESTAMP NOT NULL
closed_at       TIMESTAMP NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `organization_id`, `uuid`, `status`, `dining_table_id`

---

### 10. `customer_sessions`

Session sementara customer yang scan QR. Hilang saat open bill ditutup.

```sql
id              BIGINT PK (atau UUID sebagai PK jika mau)
uuid            UUID UNIQUE NOT NULL       -- digunakan sebagai session token di header
organization_id BIGINT FK -> organizations.id
dining_table_id BIGINT FK -> dining_tables.id NULLABLE
open_bill_id    BIGINT FK -> open_bills.id NULLABLE  -- null jika order langsung (QR meja)
session_token   VARCHAR(255) UNIQUE        -- sama dengan uuid, shorthand
client_label    VARCHAR(100) NULLABLE      -- "Meja 3 - Device iOS"
status          VARCHAR(20) DEFAULT 'active'  -- active, closed
started_at      TIMESTAMP NOT NULL
closed_at       TIMESTAMP NULLABLE
expires_at      TIMESTAMP NULLABLE         -- TTL otomatis jika tidak ada aktivitas
last_seen_at    TIMESTAMP NULLABLE
metadata        JSONB NULLABLE             -- info device, user-agent, dll
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `uuid`, `session_token`, `open_bill_id`, `status`

---

### 11. `orders`

```sql
id              BIGINT PK
organization_id BIGINT FK -> organizations.id
open_bill_id    BIGINT FK -> open_bills.id NULLABLE    -- null = order langsung (bayar di tempat)
customer_session_id BIGINT FK -> customer_sessions.id NULLABLE
dining_table_id BIGINT FK -> dining_tables.id NULLABLE
order_number    VARCHAR(50) UNIQUE         -- format: ORD-20260524-0001
source          VARCHAR(20) DEFAULT 'cashier'  -- cashier, customer
status          VARCHAR(20) DEFAULT 'pending'  -- pending, confirmed, preparing, ready, completed, cancelled
note            TEXT NULLABLE
subtotal_amount NUMERIC(12,2) DEFAULT 0
total_amount    NUMERIC(12,2) DEFAULT 0
created_by      BIGINT FK -> users.id NULLABLE     -- null jika dari customer
accepted_by     BIGINT FK -> users.id NULLABLE     -- siapa yang konfirmasi order
cancelled_by    BIGINT FK -> users.id NULLABLE
cancel_reason   TEXT NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `organization_id`, `open_bill_id`, `status`, `order_number`

---

### 12. `order_items`

```sql
id              BIGINT PK
order_id        BIGINT FK -> orders.id
menu_id         BIGINT FK -> menus.id
name            VARCHAR(255)          -- snapshot nama menu saat order dibuat
price           NUMERIC(12,2)         -- snapshot harga saat order
quantity        SMALLINT NOT NULL
subtotal        NUMERIC(12,2)
note            TEXT NULLABLE          -- catatan khusus item (misal: tanpa cabai)
status          VARCHAR(20) DEFAULT 'pending'  -- pending, preparing, ready, served, cancelled
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `order_id`, `menu_id`, `status`

---

### 13. `payments`

```sql
id              BIGINT PK
uuid            UUID UNIQUE NOT NULL
organization_id BIGINT FK -> organizations.id
order_id        BIGINT FK -> orders.id NULLABLE
open_bill_id    BIGINT FK -> open_bills.id NULLABLE
payment_number  VARCHAR(50) UNIQUE         -- format: PAY-20260524-0001
method          VARCHAR(30)                -- cash, qris, transfer, card
status          VARCHAR(20) DEFAULT 'pending'  -- pending, paid, failed, cancelled, refunded
amount          NUMERIC(12,2) NOT NULL
change_amount   NUMERIC(12,2) DEFAULT 0    -- kembalian (untuk cash)
reference       VARCHAR(255) NULLABLE      -- nomor referensi eksternal (QRIS, dll)
paid_at         TIMESTAMP NULLABLE
note            TEXT NULLABLE
created_by      BIGINT FK -> users.id NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

**Index:** `organization_id`, `uuid`, `order_id`, `open_bill_id`, `status`

---

## Relasi Antar Tabel (Summary)

| Dari | Ke | Tipe | Keterangan |
|---|---|---|---|
| `users` | `organizations` | M:M via `organization_members` | User bisa di banyak org |
| `organizations` | `dining_tables` | 1:M | Setiap org punya banyak meja |
| `organizations` | `menu_categories` | 1:M | Menu ter-scope per org |
| `menu_categories` | `menus` | 1:M | Menu ada di dalam kategori |
| `dining_tables` | `table_qr_codes` | 1:M | QR bisa diregenerasi |
| `organizations` | `open_bills` | 1:M | Bill per org |
| `dining_tables` | `open_bills` | 1:M | Meja bisa punya 1 bill aktif |
| `open_bills` | `customer_sessions` | 1:M | 1 bill bisa banyak device customer |
| `open_bills` | `orders` | 1:M | 1 bill bisa banyak order |
| `open_bills` | `payments` | 1:M | 1 bill bisa banyak pembayaran (partial) |
| `orders` | `order_items` | 1:M | 1 order banyak item |
| `order_items` | `menus` | M:1 | Snapshot dari menu |
| `orders` | `payments` | 1:1 atau 1:M | Payment untuk 1 order langsung |

---

## Aturan Bisnis yang Tercermin di Database

1. **Order tanpa open_bill** (`open_bill_id = null`) → order langsung, wajib bayar di tempat.
2. **Order dengan open_bill** → bagian dari sesi makan yang bisa ditambah berkali-kali.
3. **customer_session tanpa open_bill** → customer scan QR meja, order langsung bayar.
4. **customer_session dengan open_bill** → customer masuk ke sesi yang sudah dibuka cashier.
5. **Ketika open_bill ditutup** → semua `customer_sessions` terkait di-update ke `status = closed`.
6. **Snapshot harga di order_items** → harga di `order_items.price` tidak berubah meski menu diedit.
7. **QR token di `table_qr_codes`** → selalu diregenerasi, token lama di-revoke otomatis.

---

## Kolom Status (Enum Values)

### `users.status`
- `active`, `suspended`

### `organizations.status`
- `active`, `suspended`, `inactive`

### `organization_members.status`
- `active`, `suspended`, `invited`

### `organization_invitations.status`
- `pending`, `accepted`, `expired`, `revoked`

### `dining_tables.status`
- `available`, `occupied`, `reserved`

### `menus.status`
- `available`, `unavailable`, `hidden`

### `open_bills.status`
- `open`, `closed`

### `customer_sessions.status`
- `active`, `closed`

### `orders.status`
- `pending` → `confirmed` → `preparing` → `ready` → `completed`
- `cancelled` (dari state manapun)

### `order_items.status`
- `pending` → `preparing` → `ready` → `served`
- `cancelled`

### `orders.source`
- `cashier` (dibuat dari aplikasi Flutter oleh cashier)
- `customer` (dibuat dari web oleh customer)

### `payments.method`
- `cash`, `qris`, `transfer`, `card`

### `payments.status`
- `pending`, `paid`, `failed`, `cancelled`, `refunded`

---

## Settings JSONB di `organizations`

Field `settings` menyimpan konfigurasi per-org yang fleksibel:

```json
{
    "tax_rate": 11,
    "service_charge_rate": 5,
    "currency_symbol": "Rp",
    "allow_customer_order": true,
    "require_table_for_order": false
}
```

---

## Later — Yang Bisa Ditambah Nanti

- Tabel `outlets` — jika 1 organization punya banyak cabang fisik
- Kolom `outlet_id` di `dining_tables`, `orders`, `menus`
- Tabel `discounts` / `coupons`
- Tabel `inventory` / `stock`
- Tabel `activity_logs` (custom, tanpa Spatie)
- Kolom `kitchen_station` di `order_items` untuk routing ke dapur berbeda
