# Struktur Laravel, Enum, Validasi, dan Security

[Indeks Santap API](../santap-api.md)

---

## 16. Struktur Folder Laravel

Rekomendasi sederhana tetapi rapi:

```txt
app/
├── Actions/
│   ├── Auth/
│   ├── Organizations/
│   ├── Menus/
│   ├── Orders/
│   ├── Bills/
│   └── Payments/
├── Enums/
├── Events/
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Models/
├── Notifications/
├── Policies/
├── Services/
│   ├── OrganizationContext.php
│   ├── OrderService.php
│   ├── BillService.php
│   └── PaymentService.php
└── Support/
```

Untuk MVP, jangan terlalu over-engineer. Gunakan:

```txt
Controller → FormRequest → Action/Service → Model → API Resource
```

---

## 17. Enum Awal

Gunakan PHP Enum untuk status penting.

```txt
OrganizationStatus
MemberStatus
InvitationStatus
MenuStatus
TableStatus
CustomerSessionStatus
BillStatus
OrderStatus
OrderItemStatus
PaymentStatus
PaymentMethod
OrderSource
```

Contoh:

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cooking = 'cooking';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
```

---

## 18. Validasi Bisnis Penting

### 18.1 Order

- Customer hanya bisa order jika session aktif.
- Customer hanya bisa order jika open bill aktif.
- Menu harus aktif dan tersedia.
- Harga order item memakai snapshot saat order dibuat.
- Order tidak boleh diedit sembarangan setelah masuk kitchen.

### 18.2 Bill

- Satu meja idealnya hanya punya satu open bill aktif.
- Bill tidak bisa ditutup jika tidak ada order valid, kecuali manual close diizinkan.
- Bill closed tidak boleh menerima order baru.
- Bill closed harus menutup semua customer session terkait.

### 18.3 Payment

- Payment tidak boleh melebihi total bill kecuali ada aturan change/cash.
- Payment paid harus mencatat `paid_at` dan `paid_by`.
- Void/refund wajib memiliki alasan.
- Refund setelah closed butuh permission khusus.

### 18.4 Role

- User hanya bisa mengelola organisasi jika member aktif.
- Owner tidak boleh menghapus dirinya sendiri jika dia owner terakhir.
- Kitchen tidak boleh mengakses payment/report.
- Cashier tidak boleh mengelola role member.

### 18.5 Customer Session

- Session token harus unik dan random.
- Session expired/closed tidak boleh dipakai order.
- Session harus terikat ke organization, table, dan open bill.
- Setelah bill closed, semua session terkait harus closed.

---

## 19. Security Rule

Aturan keamanan wajib:

```txt
1. Semua endpoint user memakai auth:sanctum.
2. Semua endpoint organisasi memakai organization context.
3. Semua query bisnis wajib filter organization_id.
4. Jangan percaya organization_id dari payload tanpa validasi membership.
5. Customer token tidak boleh memberi akses ke data user.
6. Customer hanya boleh akses data session/open bill miliknya.
7. Admin action sensitif wajib audit log.
8. Invite token harus random, signed, dan memiliki expiry.
9. QR token bisa diregenerate.
10. Jangan expose internal ID jika tidak perlu; boleh gunakan UUID/ULID.
```

Rekomendasi ID:

```txt
Gunakan UUID/ULID untuk public-facing resource.
```

---

---

[Indeks Santap API](../santap-api.md)
