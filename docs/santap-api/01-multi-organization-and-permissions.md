# Multi-Organisasi, Role, dan Permission

[Indeks Santap API](../santap-api.md)

---

## 5. Model Multi-Organisasi

### 5.1 Ketentuan Utama

Santap wajib mendukung:

```txt
1 user bisa memiliki beberapa organisasi
1 organisasi bisa memiliki beberapa user
1 user bisa memiliki role berbeda di organisasi berbeda
```

Contoh:

```txt
User Ilham
├── Owner di Org A
├── Cashier di Org B
└── Kitchen di Org C
```

Karena itu, role tidak boleh hanya ditempel global ke user.

### 5.2 Struktur Relasi

```txt
users
organizations
organization_members
roles
permissions
model_has_roles / role assignments dengan organization/team scope
```

Relasi utama:

```txt
users many-to-many organizations via organization_members
organizations many-to-many users via organization_members
organization_members menyimpan role/status membership
```

### 5.3 Batas Data Tenant

Semua tabel bisnis restoran wajib memiliki `organization_id`, misalnya:

```txt
menus
menu_categories
tables
qr_codes
customer_sessions
open_bills
orders
order_items
payments
cashier_shifts
reports
```

Aturan:

- API tidak boleh mengambil data lintas organisasi.
- Query wajib discoped berdasarkan `organization_id` aktif.
- User hanya boleh mengakses organisasi tempat dia menjadi member aktif.
- Administrator Santap boleh mengakses lintas organisasi melalui admin panel, dengan audit log.

---

## 6. Role dan Permission

### 6.1 Role Global

Role global hanya untuk internal platform Santap.

```txt
administrator
```

Digunakan untuk:

- Filament Admin Panel.
- Platform management.
- Support/debugging.
- Suspend/activate organisasi.

### 6.2 Role Organisasi

Role organisasi berlaku hanya dalam konteks satu organisasi.

```txt
owner
cashier
kitchen
```

### 6.3 Permission Awal

Contoh permission:

```txt
organization.view
organization.update
organization.invite_user
organization.manage_member

menu.view
menu.create
menu.update
menu.delete

category.view
category.create
category.update
category.delete

table.view
table.create
table.update
table.delete
table.generate_qr

order.view
order.create
order.update_status
order.cancel

kitchen.view
kitchen.update_order_status

bill.view
bill.open
bill.close
bill.cancel

payment.view
payment.create
payment.refund

report.view
report.export

audit.view
```

### 6.4 Permission Matrix Awal

| Permission | Administrator | Owner | Cashier | Kitchen |
|---|---:|---:|---:|---:|
| Kelola semua organisasi | Yes | No | No | No |
| Kelola organisasi sendiri | Yes | Yes | No | No |
| Undang user | Yes | Yes | No | No |
| Kelola role member | Yes | Yes | No | No |
| Kelola menu | Yes | Yes | Optional | No |
| Kelola meja | Yes | Yes | Optional | No |
| Lihat order | Yes | Yes | Yes | Yes |
| Buat order internal | Yes | Yes | Yes | No |
| Update status kitchen | Yes | Yes | No | Yes |
| Close bill | Yes | Yes | Yes | No |
| Konfirmasi pembayaran | Yes | Yes | Yes | No |
| Lihat laporan | Yes | Yes | Optional | No |
| Lihat audit log | Yes | Yes | No | No |

Catatan:

- Permission cashier untuk menu/meja bisa dibuat opsional.
- Kitchen sebaiknya tidak punya akses data finansial.
- Administrator global tidak otomatis menjadi member organisasi, tetapi dapat mengakses data melalui mode support/admin panel.

---

---

[Indeks Santap API](../santap-api.md)
