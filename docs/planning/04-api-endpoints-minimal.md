# 04 · API Endpoints Minimal — Santap POS

> **Status:** Rencana Teknis · Belum Dieksekusi  
> **Base URL:** `https://api.santap.id/v1`  
> **Auth:** Bearer token (Sanctum) kecuali yang ditandai [PUBLIC]  
> **Org Context:** Header `X-Organization-ID: {org_id}` untuk route dalam org

---

## Konvensi Response

### Success Response

```json
{
    "data": { ... },
    "message": "Success"
}
```

### Collection Response

```json
{
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 20,
        "total": 60
    }
}
```

### Error Response

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["Email sudah digunakan."]
    }
}
```

### HTTP Status Codes

| Code | Meaning |
|---|---|
| `200` | OK |
| `201` | Created |
| `204` | No Content (delete/close) |
| `400` | Bad Request |
| `401` | Unauthorized (token tidak valid) |
| `403` | Forbidden (permission kurang) |
| `404` | Not Found |
| `422` | Validation Error |
| `429` | Too Many Requests |
| `500` | Server Error |

---

## Group 1 — Public Endpoints

### Health Check

```
GET  /v1/health
```

Response: `{ "status": "ok", "timestamp": "..." }`

---

## Group 2 — Auth

### Login

```
POST /v1/auth/login
```

Body:
```json
{
    "email": "user@example.com",
    "password": "password",
    "device_name": "Flutter App - iPhone 14"
}
```

Response `200`:
```json
{
    "data": {
        "token": "1|abc...",
        "user": {
            "id": 1,
            "name": "Budi",
            "email": "budi@example.com",
            "avatar": null
        }
    }
}
```

---

### Logout

```
POST /v1/auth/logout                    [AUTH]
```

Response `204`

---

### Get Current User

```
GET  /v1/me                             [AUTH]
```

Response `200`:
```json
{
    "data": {
        "id": 1,
        "name": "Budi",
        "email": "budi@example.com",
        "phone": "+6281234567890",
        "avatar": "http://api.santap.id/storage/avatars/budi.jpg",
        "status": "active"
    }
}
```

---

### Get User's Organizations

```
GET  /v1/me/organizations               [AUTH]
```

Response `200`:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Warung Makan Enak",
            "slug": "warung-makan-enak",
            "logo": null,
            "role": "owner",
            "status": "active"
        }
    ]
}
```

---

## Group 3 — Organization

### Create Organization

```
POST /v1/organizations                  [AUTH]
```

Body:
```json
{
    "name": "Warung Makan Enak",
    "phone": "0211234567",
    "email": "warung@example.com",
    "address": "Jl. Merdeka No. 1",
    "city": "Jakarta",
    "timezone": "Asia/Jakarta",
    "currency": "IDR"
}
```

Response `201`: `{ "data": { Organization } }`

---

### Switch Organization Context

```
POST /v1/context/switch-organization    [AUTH]
```

Body: `{ "organization_id": 1 }`  
Response `200`: `{ "data": { "organization": { ... }, "role": "cashier", "permissions": [...] } }`

---

## Group 4 — Invitations

### Invite Member

```
POST /v1/invitations                    [AUTH + ORG + PERM: organization.invite_user]
```

Body: `{ "email": "kasir@example.com", "role_name": "cashier" }`  
Response `201`: `{ "data": { "id": 1, "email": "...", "status": "pending" } }`

---

### Accept Invitation

```
POST /v1/invitations/accept             [AUTH]
```

Body: `{ "token": "abc123..." }`  
Response `200`: `{ "data": { "organization": { ... } } }`

---

## Group 5 — Menu Categories

> Header wajib: `X-Organization-ID`  
> Semua endpoint butuh `[AUTH + ORG]`

```
GET    /v1/menu-categories              PERM: category.view
POST   /v1/menu-categories              PERM: category.create
GET    /v1/menu-categories/{id}         PERM: category.view
PUT    /v1/menu-categories/{id}         PERM: category.update
DELETE /v1/menu-categories/{id}         PERM: category.delete
```

**GET /v1/menu-categories Response:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Makanan",
            "sort_order": 0,
            "is_active": true,
            "menus_count": 5
        }
    ]
}
```

**POST Body:**
```json
{
    "name": "Makanan",
    "description": null,
    "sort_order": 0,
    "is_active": true
}
```

---

## Group 6 — Menus

```
GET    /v1/menus                        [AUTH + ORG] PERM: menu.view
POST   /v1/menus                        [AUTH + ORG] PERM: menu.create
GET    /v1/menus/{id}                   [AUTH + ORG] PERM: menu.view
PUT    /v1/menus/{id}                   [AUTH + ORG] PERM: menu.update
DELETE /v1/menus/{id}                   [AUTH + ORG] PERM: menu.delete
POST   /v1/menus/{id}/image             [AUTH + ORG] PERM: menu.update
```

**POST Body:**
```json
{
    "menu_category_id": 1,
    "name": "Nasi Goreng Spesial",
    "description": "Nasi goreng dengan telur dan ayam",
    "price": 25000,
    "status": "available",
    "sort_order": 0
}
```

**POST /v1/menus/{id}/image:**  
- Content-Type: `multipart/form-data`  
- Field: `image` (file jpg/png, max 2MB)  
- Response: `{ "data": { "url": "..." } }`

---

## Group 7 — Dining Tables

```
GET    /v1/dining-tables                [AUTH + ORG] PERM: table.view
POST   /v1/dining-tables                [AUTH + ORG] PERM: table.create
GET    /v1/dining-tables/{id}           [AUTH + ORG] PERM: table.view
PUT    /v1/dining-tables/{id}           [AUTH + ORG] PERM: table.update
DELETE /v1/dining-tables/{id}           [AUTH + ORG] PERM: table.delete
POST   /v1/dining-tables/{id}/regenerate-qr  [AUTH + ORG] PERM: table.generate_qr
```

**POST Body:**
```json
{
    "name": "Meja 1",
    "capacity": 4
}
```

**POST /regenerate-qr Response:**
```json
{
    "data": {
        "token": "abc123-uuid-token",
        "qr_url": "https://app.santap.id/scan?token=abc123-uuid-token",
        "qr_image_url": "https://api.santap.id/v1/dining-tables/1/qr.png"
    }
}
```

---

## Group 8 — Open Bills (Cashier)

```
GET    /v1/open-bills                   [AUTH + ORG] PERM: bill.view
POST   /v1/open-bills                   [AUTH + ORG] PERM: bill.create
GET    /v1/open-bills/{id}              [AUTH + ORG] PERM: bill.view
POST   /v1/open-bills/{id}/close        [AUTH + ORG] PERM: bill.close
```

**GET /v1/open-bills Query Params:**
- `?status=open` — filter by status (default: `open`)
- `?dining_table_id=1`

**POST /v1/open-bills Body:**
```json
{
    "dining_table_id": 1
}
```

**GET /v1/open-bills/{id} Response:**
```json
{
    "data": {
        "id": 1,
        "uuid": "550e8400-...",
        "bill_number": "BILL-20260524-0001",
        "status": "open",
        "dining_table": { "id": 1, "name": "Meja 1" },
        "subtotal_amount": "75000.00",
        "tax_amount": "8250.00",
        "total_amount": "83250.00",
        "orders": [ ... ],
        "opened_at": "2026-05-24T10:00:00Z",
        "qr_url": "https://app.santap.id/bill?token=550e8400-..."
    }
}
```

**POST /v1/open-bills/{id}/close Body:**
```json
{
    "payment_method": "cash",
    "cash_received": 90000
}
```

---

## Group 9 — Orders (Cashier)

```
GET    /v1/orders                       [AUTH + ORG] PERM: bill.view
POST   /v1/orders                       [AUTH + ORG] PERM: bill.create
GET    /v1/orders/{id}                  [AUTH + ORG] PERM: bill.view
```

**GET /v1/orders Query Params:**
- `?status=pending,confirmed,preparing,ready`
- `?open_bill_id=1`
- `?date=2026-05-24`

**POST /v1/orders Body:**
```json
{
    "open_bill_id": 1,
    "dining_table_id": 1,
    "note": "Tolong pisahkan",
    "items": [
        { "menu_id": 5, "quantity": 2, "note": "Tanpa cabai" },
        { "menu_id": 12, "quantity": 1, "note": null }
    ]
}
```

Response `201`:
```json
{
    "data": {
        "id": 1,
        "order_number": "ORD-20260524-0001",
        "status": "pending",
        "source": "cashier",
        "subtotal_amount": "50000.00",
        "total_amount": "50000.00",
        "items": [
            {
                "id": 1,
                "menu_id": 5,
                "name": "Nasi Goreng Spesial",
                "price": "25000.00",
                "quantity": 2,
                "subtotal": "50000.00",
                "status": "pending",
                "note": "Tanpa cabai"
            }
        ]
    }
}
```

---

## Group 10 — Payments (Cashier)

```
POST   /v1/payments                     [AUTH + ORG] PERM: payment.create
POST   /v1/payments/{id}/check          [AUTH + ORG] PERM: payment.create  [throttle]
POST   /v1/payments/{id}/cancel         [AUTH + ORG] PERM: payment.create
```

**POST /v1/payments Body (Order Langsung):**
```json
{
    "order_id": 1,
    "method": "cash",
    "amount": 50000,
    "cash_received": 60000
}
```

**POST /v1/payments Body (QRIS):**
```json
{
    "order_id": 1,
    "method": "qris",
    "amount": 50000
}
```

**POST /v1/payments Response (QRIS):**
```json
{
    "data": {
        "id": 1,
        "uuid": "...",
        "payment_number": "PAY-20260524-0001",
        "status": "pending",
        "method": "qris",
        "amount": "50000.00",
        "qris_url": "https://qris.example.com/...",
        "qris_image_base64": "data:image/png;base64,..."
    }
}
```

**POST /v1/payments/{id}/check Response:**
```json
{
    "data": {
        "id": 1,
        "status": "paid",
        "paid_at": "2026-05-24T10:15:00Z"
    }
}
```

---

## Group 11 — Kitchen

```
GET    /v1/kitchen/orders               [AUTH + ORG] PERM: kitchen.view
PATCH  /v1/kitchen/order-items/{id}/status [AUTH + ORG] PERM: kitchen.update_order_status
```

**GET /v1/kitchen/orders Response:**
```json
{
    "data": [
        {
            "id": 1,
            "order_number": "ORD-20260524-0001",
            "status": "confirmed",
            "source": "customer",
            "dining_table": { "id": 1, "name": "Meja 3" },
            "note": null,
            "created_at": "2026-05-24T10:05:00Z",
            "items": [
                {
                    "id": 1,
                    "name": "Nasi Goreng Spesial",
                    "quantity": 2,
                    "note": "Tanpa cabai",
                    "status": "pending"
                }
            ]
        }
    ]
}
```

**PATCH /v1/kitchen/order-items/{id}/status Body:**
```json
{
    "status": "preparing"
}
```

---

## Group 12 — Reports (Owner)

```
GET    /v1/reports/sales-summary        [AUTH + ORG] PERM: report.view
GET    /v1/reports/daily-sales          [AUTH + ORG] PERM: report.view
GET    /v1/reports/menu-sales           [AUTH + ORG] PERM: report.view
GET    /v1/reports/payment-methods      [AUTH + ORG] PERM: report.view
```

**Query Params (umum):**
- `?from=2026-05-01&to=2026-05-31`

**GET /v1/reports/sales-summary Response:**
```json
{
    "data": {
        "period": "2026-05-01 to 2026-05-31",
        "total_orders": 150,
        "total_revenue": "7500000.00",
        "average_order_value": "50000.00",
        "total_bills": 80
    }
}
```

---

## Group 13 — Customer Endpoints [PUBLIC + SESSION]

> Tidak butuh Sanctum token.  
> Auth dilakukan via `X-Session-Token: {uuid}` header.

### Start Session (Scan QR)

```
POST /v1/customer/sessions/start        [PUBLIC] [throttle: 10/min]
```

Body (scan QR meja):
```json
{
    "token": "<table_qr_token>",
    "type": "table"
}
```

Body (scan QR open bill):
```json
{
    "token": "<open_bill_uuid>",
    "type": "open_bill"
}
```

Response `201`:
```json
{
    "data": {
        "session_token": "550e8400-uuid-...",
        "type": "open_bill",
        "organization": {
            "name": "Warung Makan Enak",
            "slug": "warung-makan-enak",
            "currency": "IDR"
        },
        "dining_table": { "name": "Meja 3" },
        "open_bill_id": 1
    }
}
```

---

### Get Current Session

```
GET  /v1/customer/sessions/current      [SESSION]
```

Header: `X-Session-Token: {uuid}`

Response: `{ "data": { CustomerSession dengan relasi } }`

---

### Get Menu (Customer)

```
GET  /v1/customer/menu                  [SESSION]
```

Response:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Makanan",
            "menus": [
                {
                    "id": 5,
                    "name": "Nasi Goreng Spesial",
                    "description": "...",
                    "price": "25000.00",
                    "image": "http://api.santap.id/storage/menus/abc.jpg",
                    "status": "available"
                }
            ]
        }
    ]
}
```

---

### View Open Bill (Customer)

```
GET  /v1/customer/open-bill             [SESSION]
```

Response: Info tagihan saat ini, termasuk semua order yang sudah dibuat.

---

### Place Order (Customer)

```
POST /v1/customer/orders                [SESSION] [throttle: 5/min]
```

Body:
```json
{
    "note": "Meja butuh tambahan sendok",
    "items": [
        { "menu_id": 5, "quantity": 1, "note": null }
    ]
}
```

Response `201`: `{ "data": { Order } }`

---

### Get Organization Info (Public)

```
GET  /v1/customer/organizations/{slug}  [PUBLIC]
```

Response: Info org publik (nama, logo, dll) untuk landing page customer.

---

### Customer Payment (Order Langsung via QR Meja)

```
POST /v1/customer/payments              [SESSION]
POST /v1/customer/payments/{id}/check   [SESSION] [throttle: 12/min]
POST /v1/customer/payments/{id}/cancel  [SESSION]
```

Body sama seperti cashier payment.

---

### Call Cashier

```
POST /v1/customer/call-cashier          [SESSION]
```

Body: `{ "note": "Tolong datang ke meja kami" }` (opsional)  
Response: `{ "message": "Permintaan dikirim ke kasir" }`

> **Note:** Ini hanya menyimpan record "call request" di database. Cashier melihatnya via polling.

---

## Throttle Rate Limits

| Route Name | Limit |
|---|---|
| `auth.login` | 5 requests/minute |
| `invitations.invite` | 10 requests/minute |
| `customer-session-start` | 10 requests/minute |
| `customer-order` | 5 requests/minute |
| `qris-check` | 12 requests/minute |

---

## Header Conventions

| Header | Keterangan |
|---|---|
| `Authorization: Bearer {token}` | Sanctum token (untuk user auth) |
| `X-Organization-ID: {id}` | Org context (untuk route dalam organisasi) |
| `X-Session-Token: {uuid}` | Customer session token |
| `Content-Type: application/json` | Semua request body |
| `Accept: application/json` | Wajib agar Laravel return JSON error |

---

## Endpoint yang Tidak Ada (Ditunda ke "Later")

- `GET /v1/notifications` — realtime notification (butuh Reverb/Pusher)
- `POST /v1/menus/{id}/variants` — menu variants (size, topping)
- `GET /v1/reports/inventory` — stok bahan baku
- `POST /v1/discounts` — kupon dan diskon
- `GET /v1/kitchen/stations` — routing ke dapur berbeda
- `POST /v1/outlets` — multi-cabang
- `PATCH /v1/orders/{id}/status` — edit status order langsung (dilakukan via kitchen endpoint)
