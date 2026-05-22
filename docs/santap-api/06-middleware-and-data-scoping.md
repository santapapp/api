# Middleware dan Data Scoping

[Indeks Santap API](../santap-api.md)

---

## 11. Middleware Laravel

Middleware penting:

```txt
auth:sanctum
resolve.organization
ensure.organization.member
ensure.organization.permission
ensure.customer.session
ensure.open.bill.active
```

### 11.1 resolve.organization

Tugas:

- Membaca `X-Organization-Id`.
- Validasi organisasi ada dan aktif.
- Simpan organization context ke request/container.

### 11.2 ensure.organization.member

Tugas:

- Validasi user adalah member organisasi.
- Validasi member status `active`.
- Tolak akses jika bukan member.

### 11.3 ensure.organization.permission

Tugas:

- Mengecek role/permission user dalam organisasi aktif.
- Cocok untuk endpoint sensitif seperti menu update, close bill, invite member.

### 11.4 ensure.customer.session

Tugas:

- Membaca session token customer.
- Validasi token aktif.
- Validasi session belum closed/expired.
- Resolve organization/table/open_bill dari session.

---

## 12. Data Scoping Rule

Semua model bisnis wajib discoped berdasarkan organization.

Contoh model yang wajib punya `organization_id`:

```txt
Menu
MenuCategory
DiningTable
TableQrCode
CustomerSession
OpenBill
Order
OrderItem
Payment
CashierShift
```

Aturan service/repository:

```txt
Jangan pernah query data bisnis tanpa organization scope.
```

Contoh buruk:

```php
Order::find($id);
```

Contoh benar:

```php
Order::where('organization_id', $organizationId)->findOrFail($id);
```

Bisa dibuat trait:

```txt
BelongsToOrganization
```

Fungsi trait:

- Relationship `organization()`.
- Scope `forOrganization($organizationId)`.
- Auto-fill `organization_id` saat create jika context tersedia.

---

---

[Indeks Santap API](../santap-api.md)
