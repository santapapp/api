# 03 · Implementation Plan — Santap POS Backend

> **Status:** Rencana Teknis · Belum Dieksekusi  
> **Dibuat:** 2026-05-24  
> **Approach:** Incremental, clean code, junior-friendly

---

## Struktur Folder Laravel yang Direkomendasikan

```
app/
├── Actions/                    # Business logic yang dipanggil dari Controller/Job
│   ├── Auth/
│   │   └── LoginUser.php
│   ├── Order/
│   │   ├── CreateOrder.php
│   │   ├── ConfirmOrder.php
│   │   └── CancelOrder.php
│   ├── OpenBill/
│   │   ├── CreateOpenBill.php
│   │   └── CloseOpenBill.php
│   └── Payment/
│       └── ProcessPayment.php
│
├── Enums/                      # PHP 8.1+ backed enums
│   ├── BillStatus.php
│   ├── CustomerSessionStatus.php
│   ├── MenuStatus.php
│   ├── OrderItemStatus.php
│   ├── OrderSource.php
│   ├── OrderStatus.php
│   ├── PaymentMethod.php
│   └── PaymentStatus.php
│
├── Filament/                   # Admin panel Filament
│   ├── Pages/
│   ├── Resources/
│   │   ├── UserResource.php
│   │   ├── OrganizationResource.php
│   │   └── ...
│   └── Widgets/
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── AuthController.php
│   │           ├── ContextController.php
│   │           ├── OrganizationController.php
│   │           ├── InvitationController.php
│   │           ├── MenuCategoryController.php
│   │           ├── MenuController.php
│   │           ├── DiningTableController.php
│   │           ├── OpenBillController.php     # rename dari CashierBillController
│   │           ├── OrderController.php
│   │           ├── PaymentController.php
│   │           ├── KitchenOrderController.php
│   │           ├── ReportController.php
│   │           └── Customer/                  # namespace tersendiri untuk customer
│   │               ├── SessionController.php
│   │               ├── MenuController.php
│   │               ├── OrderController.php
│   │               ├── BillController.php
│   │               └── PaymentController.php
│   │
│   ├── Middleware/
│   │   ├── ResolveOrganization.php
│   │   ├── EnsureOrganizationMember.php
│   │   ├── EnsureOrganizationPermission.php
│   │   └── EnsureCustomerSession.php
│   │
│   └── Resources/              # API Resources (transformers)
│       ├── UserResource.php
│       ├── OrganizationResource.php
│       ├── MenuResource.php
│       ├── OrderResource.php
│       ├── OrderItemResource.php
│       ├── OpenBillResource.php
│       ├── PaymentResource.php
│       └── Customer/
│           ├── MenuResource.php
│           ├── OrderResource.php
│           └── BillResource.php
│
├── Models/
│   ├── User.php
│   ├── Organization.php
│   ├── OrganizationMember.php
│   ├── OrganizationInvitation.php
│   ├── DiningTable.php
│   ├── TableQrCode.php
│   ├── MenuCategory.php
│   ├── Menu.php
│   ├── OpenBill.php
│   ├── CustomerSession.php
│   ├── Order.php
│   ├── OrderItem.php
│   └── Payment.php
│
├── Policies/                   # Authorization policies
│   ├── MenuPolicy.php
│   ├── OrderPolicy.php
│   └── ...
│
├── Providers/
│   ├── AppServiceProvider.php
│   └── FilamentServiceProvider.php   # jika ada custom setup Filament
│
├── Services/                   # External service integrations
│   └── QrisService.php         # Integrasi QRIS gateway (opsional)
│
└── Traits/
    └── BelongsToOrganization.php  # Scope query per organization_id
```

---

## Daftar Migration (Urutan Eksekusi)

### Existing (Pertahankan, Tidak Perlu Diubah)

```
0001_01_01_000000_create_users_table.php
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2026_05_21_143153_create_permission_tables.php
2026_05_21_143742_create_personal_access_tokens_table.php
```

### Existing (Hapus jika fresh, atau buat drop migration jika sudah production)

```
2026_05_21_143201_create_activity_log_table.php       -- HAPUS
2026_05_21_143202_add_event_column_to_activity_log.php -- HAPUS
2026_05_21_143203_add_batch_uuid_column_to_activity_log.php -- HAPUS
2026_05_21_171227_create_pulse_tables.php             -- HAPUS
2026_05_21_171232_create_media_table.php              -- HAPUS
2026_05_23_052700_alter_activity_log_subject_id.php   -- HAPUS
```

### Existing (Pertahankan)

```
2026_05_23_000001_add_fields_to_users_table.php
2026_05_23_000002_create_organizations_table.php
2026_05_23_000003_create_organization_members_table.php
2026_05_23_000004_create_organization_invitations_table.php
2026_05_23_000005_add_organization_id_to_permission_tables.php
2026_05_23_000006_create_menu_categories_table.php
2026_05_23_000007_create_menus_table.php
2026_05_23_000008_create_dining_tables_table.php
2026_05_23_000009_create_table_qr_codes_table.php
2026_05_23_000010_create_open_bills_table.php
2026_05_23_000011_create_customer_sessions_table.php
2026_05_23_042024_create_orders_table.php
2026_05_23_042025_create_order_items_table.php
2026_05_23_042026_create_payments_table.php
```

---

## Model dan Relasi Eloquent

### `User`

```php
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    // Relasi
    public function organizations(): BelongsToMany
    public function organizationMembers(): HasMany
    public function createdOrganizations(): HasMany  // organizations.created_by
}
```

### `Organization`

```php
class Organization extends Model
{
    use HasFactory;

    // Casts: status (OrganizationStatus), settings (array)

    public function creator(): BelongsTo            // -> User
    public function members(): HasMany              // -> OrganizationMember
    public function users(): BelongsToMany          // -> User via organization_members
    public function invitations(): HasMany          // -> OrganizationInvitation
    public function diningTables(): HasMany         // -> DiningTable
    public function menuCategories(): HasMany       // -> MenuCategory
    public function openBills(): HasMany            // -> OpenBill
}
```

### `OrganizationMember`

```php
class OrganizationMember extends Model
{
    public function organization(): BelongsTo       // -> Organization
    public function user(): BelongsTo               // -> User
}
```

### `DiningTable`

```php
class DiningTable extends Model
{
    use SoftDeletes;

    // Casts: status (TableStatus)

    public function organization(): BelongsTo       // -> Organization
    public function qrCodes(): HasMany              // -> TableQrCode
    public function activeQr(): HasOne              // -> TableQrCode (status=active)
    public function openBills(): HasMany            // -> OpenBill
    public function activeOpenBill(): HasOne        // -> OpenBill (status=open)
}
```

### `TableQrCode`

```php
class TableQrCode extends Model
{
    public function table(): BelongsTo              // -> DiningTable
    public function generatedBy(): BelongsTo        // -> User
}
```

### `MenuCategory`

```php
class MenuCategory extends Model
{
    public function organization(): BelongsTo       // -> Organization
    public function menus(): HasMany                // -> Menu
    public function activeMenus(): HasMany          // -> Menu (status=available)
}
```

### `Menu`

```php
class Menu extends Model
{
    use SoftDeletes;

    // Casts: status (MenuStatus), price (decimal:2)

    public function organization(): BelongsTo       // -> Organization
    public function category(): BelongsTo           // -> MenuCategory
}
```

### `OpenBill`

```php
class OpenBill extends Model
{
    use HasUuids;

    // Casts: status (BillStatus), semua *_amount (decimal:2)

    public function organization(): BelongsTo       // -> Organization
    public function table(): BelongsTo              // -> DiningTable
    public function openedBy(): BelongsTo           // -> User
    public function closedBy(): BelongsTo           // -> User
    public function sessions(): HasMany             // -> CustomerSession
    public function orders(): HasMany               // -> Order
    public function payments(): HasMany             // -> Payment
}
```

### `CustomerSession`

```php
class CustomerSession extends Model
{
    use HasUuids;

    // Casts: status (CustomerSessionStatus), timestamps

    public function organization(): BelongsTo       // -> Organization
    public function table(): BelongsTo              // -> DiningTable
    public function openBill(): BelongsTo           // -> OpenBill (nullable)
    public function orders(): HasMany               // -> Order
}
```

### `Order`

```php
class Order extends Model
{
    // Casts: status (OrderStatus), source (OrderSource), *_amount (decimal:2)

    public function organization(): BelongsTo       // -> Organization
    public function openBill(): BelongsTo           // -> OpenBill (nullable)
    public function customerSession(): BelongsTo    // -> CustomerSession (nullable)
    public function diningTable(): BelongsTo        // -> DiningTable (nullable)
    public function createdBy(): BelongsTo          // -> User (nullable)
    public function acceptedBy(): BelongsTo         // -> User (nullable)
    public function items(): HasMany                // -> OrderItem
    public function payment(): HasOne               // -> Payment (untuk order langsung)
}
```

### `OrderItem`

```php
class OrderItem extends Model
{
    // Casts: status (OrderItemStatus), price/subtotal (decimal:2)

    public function order(): BelongsTo              // -> Order
    public function menu(): BelongsTo               // -> Menu (untuk referensi, bukan dependensi)
}
```

### `Payment`

```php
class Payment extends Model
{
    use HasUuids;

    // Casts: method (PaymentMethod), status (PaymentStatus), *_amount (decimal:2)

    public function organization(): BelongsTo       // -> Organization
    public function order(): BelongsTo              // -> Order (nullable)
    public function openBill(): BelongsTo           // -> OpenBill (nullable)
    public function createdBy(): BelongsTo          // -> User (nullable)
}
```

---

## Trait: `BelongsToOrganization`

```php
namespace App\Traits;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // Global scope untuk keamanan multi-tenant
    // (Aktifkan jika diperlukan, tapi bisa membingungkan untuk query admin)
}
```

---

## Flow Utama (Business Logic)

### Flow 1 — Owner Setup Organization & Outlet

```
POST /v1/auth/login         → Mendapatkan token
POST /v1/organizations      → Buat org baru (user otomatis jadi owner)
POST /v1/invitations        → Invite cashier/kitchen via email
GET  /v1/dining-tables      → Lihat meja
POST /v1/dining-tables      → Tambah meja
POST /v1/menu-categories    → Tambah kategori menu
POST /v1/menus              → Tambah menu item
```

### Flow 2 — Cashier Buat Order Langsung (Bayar di Tempat)

```
[Cashier buka app Flutter]
GET  /v1/menus             → Pilih menu
POST /v1/orders            → Buat order (source=cashier, open_bill_id=null)
POST /v1/payments          → Proses pembayaran (method=cash/qris)
GET  /v1/payments/{id}/status → Polling status QRIS (jika method=qris)
```

### Flow 3 — Cashier Buat Open Bill

```
[Cashier pilih meja]
POST /v1/open-bills        → Buat open bill untuk meja X
                            → generate QR dengan UUID open bill
                            → dining_table.status = occupied

[Cashier buat order pertama untuk bill ini]
POST /v1/orders            → Buat order (open_bill_id = <id_bill>)

[Saat customer mau bayar]
POST /v1/open-bills/{id}/close → Tutup bill
                                → Hitung total semua order
                                → Proses payment terakhir
                                → dining_table.status = available
                                → Semua customer_sessions terkait → status=closed
```

### Flow 4 — Customer Scan QR Meja (Order Langsung)

```
[Customer scan QR meja di smartphone]
→ URL: https://app.santap.id/scan?token=<table_qr_token>

POST /v1/customer/sessions/start   { token: "<table_qr_token>" }
→ Validasi token di table_qr_codes
→ Buat customer_session (open_bill_id = null)
→ Return: { session_uuid, menu_url }

GET  /v1/customer/menu             → Ambil menu org ini
POST /v1/customer/orders           → Buat order (source=customer)
POST /v1/customer/payments         → Bayar (QRIS)
GET  /v1/customer/payments/{id}/check → Polling status QRIS
```

### Flow 5 — Customer Scan QR Open Bill

```
[Customer scan QR open bill yang ditempel cashier di meja]
→ URL: https://app.santap.id/bill?token=<open_bill_uuid>

POST /v1/customer/sessions/start   { token: "<open_bill_uuid>", type: "open_bill" }
→ Validasi open_bill.uuid
→ Buat customer_session (open_bill_id = <bill_id>)
→ Return: { session_uuid }

GET  /v1/customer/open-bill         → Lihat tagihan (semua order, total)
POST /v1/customer/orders            → Tambah order ke bill yang ada
                                    → open bill total di-recalculate

[Bayar dilakukan oleh cashier, bukan customer langsung]
```

### Flow 6 — Kitchen Memproses Order Item

```
[Kitchen buka app Flutter]
GET  /v1/kitchen/orders              → Polling setiap N detik
                                     → Return: semua order dengan status pending/confirmed/preparing
                                     → Filter per organization

PATCH /v1/kitchen/order-items/{id}/status { status: "preparing" }
→ order_item.status = preparing
→ Jika semua item = ready → order.status otomatis = ready

PATCH /v1/kitchen/order-items/{id}/status { status: "ready" }
→ Kasir di-notify (via polling) bahwa pesanan siap diambil
```

### Flow 7 — Cashier Tutup Open Bill

```
POST /v1/open-bills/{id}/close
→ Validasi: semua order sudah completed (tidak ada yg masih pending/preparing)
→ Hitung ulang total:
   subtotal = SUM(order_items.subtotal)
   tax = subtotal * settings.tax_rate / 100
   service = subtotal * settings.service_charge_rate / 100
   total = subtotal + tax + service - discount
→ open_bill.status = closed
→ open_bill.closed_at = now()
→ open_bill.closed_by = user_id
→ dining_table.status = available (jika linked ke meja)
→ customer_sessions terkait → status = closed
```

---

## Prioritas Implementasi per Fase

### Phase 1 — Auth, Organization, Role ✅ (Sudah Sebagian Ada)

**Tujuan:** User bisa login, buat organization, invite member.

**Migration:**
- ✅ `create_users_table` + `add_fields_to_users_table`
- ✅ `create_permission_tables` + `add_organization_id_to_permission_tables`
- ✅ `create_organizations_table`
- ✅ `create_organization_members_table`
- ✅ `create_organization_invitations_table`
- ✅ `create_personal_access_tokens_table`

**Model:** `User`, `Organization`, `OrganizationMember`, `OrganizationInvitation`

**Seeders:**
- `RolePermissionSeeder` — seed roles: `administrator`, `owner`, `cashier`, `kitchen`
- `AdminUserSeeder` — seed 1 user admin
- `OrganizationSeeder` — seed 1 org demo

**Controller:**
- `AuthController` — login, logout, me, organizations
- `ContextController` — switch-organization
- `OrganizationController` — store
- `InvitationController` — invite, accept

**Middleware:**
- `ResolveOrganization` — baca `X-Organization-ID` dari header
- `EnsureOrganizationMember` — validasi user adalah member org
- `EnsureOrganizationPermission` — cek permission Spatie

**Checklist:**
- [ ] Hapus package yang tidak diperlukan
- [ ] Bersihkan model dari LogsActivity dan InteractsWithMedia
- [ ] Jalankan `php artisan migrate:fresh --seed`
- [ ] Test login endpoint
- [ ] Test buat organization
- [ ] Test invite member

---

### Phase 2 — Menu & Dining Table

**Tujuan:** Owner bisa setup menu dan meja.

**Migration:** ✅ Semua sudah ada

**Model:** `MenuCategory`, `Menu`, `DiningTable`, `TableQrCode`

**Controller:**
- `MenuCategoryController` — CRUD
- `MenuController` — CRUD + upload image
- `DiningTableController` — CRUD + regenerate QR

**Fitur Upload Gambar Menu:**
```php
// MenuController@uploadImage
$path = $request->file('image')->store('menus', 'public');
$menu->update(['image' => $path]);
return response()->json(['url' => Storage::url($path)]);
```

**Checklist:**
- [ ] Test CRUD menu category
- [ ] Test CRUD menu dengan upload gambar
- [ ] Test CRUD dining table
- [ ] Test regenerate QR (return token baru, revoke token lama)
- [ ] Validasi permission per endpoint

---

### Phase 3 — Order & Payment (Direct, Tanpa Open Bill)

**Tujuan:** Cashier bisa buat order langsung dan proses pembayaran.

**Migration:** ✅ Sudah ada

**Model:** `Order`, `OrderItem`, `Payment`

**Actions:**
```php
// App\Actions\Order\CreateOrder
class CreateOrder
{
    public function execute(Organization $org, array $data, ?User $cashier): Order
    {
        // Validasi menu tersedia
        // Hitung subtotal & total
        // Buat order + order_items
        // Return order
    }
}

// App\Actions\Payment\ProcessPayment
class ProcessPayment
{
    public function execute(Order|OpenBill $payable, array $data): Payment
    {
        // Buat payment record
        // Jika cash: langsung paid
        // Jika QRIS: generate QR code, status = pending
    }
}
```

**Controller:**
- `OrderController` — store (cashier buat order)
- `PaymentController` — store, checkStatus, cancelPayment

**Checklist:**
- [ ] Cashier bisa buat order (items, subtotal, total)
- [ ] Cashier bisa proses pembayaran cash (langsung selesai)
- [ ] Cashier bisa proses pembayaran QRIS (pending → polling)
- [ ] Order status auto-update setelah payment sukses

---

### Phase 4 — Open Bill & Customer Session

**Tujuan:** Customer bisa scan QR dan order lewat web.

**Migration:** ✅ Sudah ada

**Model:** ✅ `OpenBill`, `CustomerSession`

**Controller:**
- `OpenBillController` — index, store, close
- `Customer/SessionController` — start, current
- `Customer/MenuController` — index (public menu)
- `Customer/OrderController` — store
- `Customer/BillController` — show (lihat tagihan)

**Middleware:** `EnsureCustomerSession` — validasi session_token di header

**Checklist:**
- [ ] Cashier bisa buat open bill (linked ke meja)
- [ ] Customer scan QR meja → buat session → order langsung
- [ ] Customer scan QR open bill → join session → tambah order
- [ ] Customer bisa lihat tagihan (open bill) secara real-time via polling
- [ ] Cashier bisa tutup open bill → session customer expired

---

### Phase 5 — Kitchen Workflow

**Tujuan:** Kitchen bisa lihat dan update status pesanan.

**Controller:** `KitchenOrderController` — index, updateItemStatus

**Logic:**
- `GET /kitchen/orders` → return semua order status `pending`/`confirmed`/`preparing`
- `PATCH /kitchen/order-items/{id}/status` → update status item
- Auto-detect: jika semua item order sudah `ready`, update `order.status = ready`

**Polling Strategy:**
- Kitchen app polling setiap 10-15 detik
- Endpoint return full list, bukan delta — simple dan predictable
- Response di-cache ringan (60 detik) di sisi client

**Checklist:**
- [ ] Kitchen bisa lihat semua pesanan aktif
- [ ] Kitchen bisa update status item satu per satu
- [ ] Order status auto-complete saat semua item ready
- [ ] Cashier bisa lihat order mana yang sudah siap (via polling juga)

---

### Phase 6 — Admin Panel Filament

**Tujuan:** Administrator bisa kelola platform lewat Filament.

**Filament Resources:**
- `UserResource` — CRUD user + reset password
- `OrganizationResource` — CRUD org + suspend
- `MenuResource` — read-only (view semua menu lintas org)
- `OrderResource` — read-only (view semua order)
- `PaymentResource` — read-only (rekap pembayaran)

**Filament Widgets:**
- `StatsOverviewWidget` — total org, total order hari ini, total revenue

**Checklist:**
- [ ] User dengan role `administrator` bisa akses `/admin`
- [ ] CRUD User berjalan
- [ ] CRUD Organization berjalan
- [ ] Widget statistik menampilkan data benar

---

## Permission Map per Role

| Permission | administrator | owner | cashier | kitchen |
|---|:---:|:---:|:---:|:---:|
| `organization.invite_user` | ✅ | ✅ | ❌ | ❌ |
| `category.view` | ✅ | ✅ | ✅ | ✅ |
| `category.create/update/delete` | ✅ | ✅ | ❌ | ❌ |
| `menu.view` | ✅ | ✅ | ✅ | ✅ |
| `menu.create/update/delete` | ✅ | ✅ | ❌ | ❌ |
| `table.view` | ✅ | ✅ | ✅ | ❌ |
| `table.create/update/delete` | ✅ | ✅ | ❌ | ❌ |
| `table.generate_qr` | ✅ | ✅ | ✅ | ❌ |
| `bill.view` | ✅ | ✅ | ✅ | ❌ |
| `bill.create` | ✅ | ✅ | ✅ | ❌ |
| `bill.close` | ✅ | ✅ | ✅ | ❌ |
| `payment.create` | ✅ | ✅ | ✅ | ❌ |
| `kitchen.view` | ✅ | ✅ | ✅ | ✅ |
| `kitchen.update_order_status` | ✅ | ✅ | ✅ | ✅ |
| `report.view` | ✅ | ✅ | ❌ | ❌ |

---

## Strategi Polling (Pengganti Realtime)

Karena tidak menggunakan WebSocket, semua state update menggunakan HTTP polling.

| Client | Endpoint | Interval |
|---|---|---|
| Kitchen app | `GET /kitchen/orders` | 10–15 detik |
| Cashier app (order status) | `GET /orders?status=ready` | 10 detik |
| Customer web (bill status) | `GET /customer/open-bill` | 15 detik |
| Customer web (payment) | `GET /customer/payments/{id}/check` | 5 detik (saat menunggu QRIS) |
| Cashier app (payment status) | `GET /payments/{id}/check` | 5 detik |

**Tips Polling Efisien:**
- Tambahkan `ETag` / `Last-Modified` header di response agar client bisa skip update jika tidak ada perubahan.
- Atau tambahkan field `updated_at` di response, client compare sebelum render ulang.
- Response payload dibuat ringan (hanya field yang dibutuhkan client).

---

## Checklist Master Sebelum Production

- [ ] `php artisan migrate` berjalan bersih tanpa error
- [ ] `php artisan db:seed` menghasilkan data yang benar
- [ ] Semua endpoint API mengembalikan format JSON yang konsisten
- [ ] Semua endpoint yang butuh auth mengembalikan 401 tanpa token
- [ ] Semua endpoint org-scoped mengembalikan 403 jika beda org
- [ ] Upload gambar berjalan dan file tersimpan di storage/public
- [ ] `php artisan storage:link` dijalankan
- [ ] Queue worker berjalan (`php artisan queue:work`)
- [ ] `.env` production terisi lengkap
- [ ] `DB_SSLMODE=require` untuk Neon PostgreSQL
- [ ] `APP_DEBUG=false` di production
