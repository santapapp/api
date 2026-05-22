# Skema Database Awal

[Indeks Santap API](../santap-api.md)

---

## 9. Skema Database Awal

> Ini adalah skema konseptual awal. Detail migration bisa disesuaikan saat implementasi.

## 9.1 users

```txt
id
name
email
email_verified_at
password
phone
avatar
status: active/suspended
last_login_at
created_at
updated_at
```

## 9.2 organizations

```txt
id
name
slug
code
logo
phone
email
address
city
province
country
timezone
currency
status: active/suspended/inactive
settings jsonb
created_by
created_at
updated_at
```

Catatan:

- `slug` digunakan untuk URL customer/admin context.
- `settings` dapat menyimpan konfigurasi restoran.

Contoh `settings`:

```json
{
  "service_charge_enabled": false,
  "service_charge_percent": 0,
  "tax_enabled": false,
  "tax_percent": 0,
  "allow_customer_join_existing_bill": true,
  "require_cashier_accept_order": false
}
```

## 9.3 organization_members

```txt
id
organization_id
user_id
role_name
status: active/invited/suspended/left
joined_at
created_at
updated_at
```

Catatan:

- Bisa tetap menggunakan Spatie Permission untuk role final.
- `role_name` dapat dipakai sebagai denormalized helper untuk query cepat.

Unique constraint:

```txt
unique(organization_id, user_id)
```

## 9.4 organization_invitations

```txt
id
organization_id
email
role_name
invited_by
invite_token
status: pending/accepted/expired/cancelled
expires_at
accepted_at
created_at
updated_at
```

## 9.5 menu_categories

```txt
id
organization_id
name
slug
description
sort_order
status: active/inactive
created_at
updated_at
```

## 9.6 menus

```txt
id
organization_id
menu_category_id
name
slug
description
price
image
sku
status: active/inactive/out_of_stock
sort_order
metadata jsonb
created_at
updated_at
```

## 9.7 menu_variants

Opsional untuk fase lanjutan.

```txt
id
organization_id
menu_id
name
price_delta
status: active/inactive
created_at
updated_at
```

## 9.8 menu_addons

Opsional untuk fase lanjutan.

```txt
id
organization_id
name
price
status: active/inactive
created_at
updated_at
```

## 9.9 dining_tables

```txt
id
organization_id
name
code
capacity
status: available/occupied/reserved/inactive
location_label
created_at
updated_at
```

Catatan: gunakan nama `dining_tables`, bukan `tables`, agar tidak rancu dengan istilah database table.

## 9.10 table_qr_codes

```txt
id
organization_id
dining_table_id
qr_token
qr_url
status: active/revoked
last_scanned_at
created_at
updated_at
```

## 9.11 customer_sessions

```txt
id
organization_id
dining_table_id
open_bill_id nullable
session_token
client_label nullable
status: active/closed/expired
started_at
closed_at
expires_at
last_seen_at
metadata jsonb
created_at
updated_at
```

Ketentuan:

- `session_token` harus random dan aman.
- Token sebaiknya disimpan hashed di database jika ingin lebih aman.
- `open_bill_id` bisa null saat session baru dibuat sebelum bill final.

## 9.12 open_bills

```txt
id
organization_id
dining_table_id
bill_number
status: open/closed/cancelled
subtotal_amount
discount_amount
service_amount
tax_amount
total_amount
opened_by nullable
closed_by nullable
opened_at
closed_at
created_at
updated_at
```

## 9.13 orders

```txt
id
organization_id
open_bill_id
customer_session_id nullable
dining_table_id
order_number
source: customer/cashier/owner
status: pending/accepted/cooking/ready/served/cancelled
note
subtotal_amount
total_amount
created_by nullable
accepted_by nullable
cancelled_by nullable
cancel_reason nullable
created_at
updated_at
```

## 9.14 order_items

```txt
id
organization_id
order_id
menu_id
menu_name_snapshot
menu_price_snapshot
quantity
note
status: pending/cooking/ready/served/cancelled
subtotal_amount
created_at
updated_at
```

Kenapa perlu snapshot?

Harga/nama menu bisa berubah. Order lama harus tetap menyimpan harga dan nama saat transaksi terjadi.

## 9.15 order_item_addons

Opsional jika ada addon.

```txt
id
organization_id
order_item_id
addon_id nullable
addon_name_snapshot
addon_price_snapshot
quantity
subtotal_amount
created_at
updated_at
```

## 9.16 payments

```txt
id
organization_id
open_bill_id
payment_number
method: cash/qris/bank_transfer/card/other
status: pending/paid/failed/refunded/void
amount
paid_amount
change_amount
reference_number nullable
paid_by nullable
paid_at nullable
voided_by nullable
void_reason nullable
metadata jsonb
created_at
updated_at
```

## 9.17 cashier_shifts

Opsional tetapi bagus untuk POS.

```txt
id
organization_id
user_id
shift_code
opening_cash
closing_cash
expected_cash
actual_cash
status: open/closed
opened_at
closed_at
created_at
updated_at
```

## 9.18 activity_logs

Dari Spatie Activitylog.

Dipakai untuk:

- Update role member.
- Cancel order.
- Void/refund payment.
- Suspend organisasi.
- Update harga menu.
- Close bill.

## 9.19 notifications

Laravel database notifications.

Dipakai untuk:

- Invite user.
- Order masuk.
- Payment event.
- System warning.

---

---

[Indeks Santap API](../santap-api.md)
