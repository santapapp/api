# API Design

[Indeks Santap API](../santap-api.md)

---

## 10. API Design

Base URL:

```txt
/api/v1
```

Header umum untuk user login:

```http
Authorization: Bearer {sanctum_token}
Accept: application/json
X-Organization-Id: {organization_id}
```

Header umum untuk customer session:

```http
Accept: application/json
X-Customer-Session: {session_token}
```

## 10.1 Auth API

```txt
POST /auth/login
POST /auth/logout
GET  /me
GET  /me/organizations
POST /context/switch-organization
```

## 10.2 Organization API

```txt
GET    /organizations
GET    /organizations/{organization}
PATCH  /organizations/{organization}
GET    /organizations/{organization}/members
POST   /organizations/{organization}/invitations
DELETE /organizations/{organization}/members/{member}
PATCH  /organizations/{organization}/members/{member}/role
```

Catatan:

- Untuk Flutter, user hanya boleh melihat organisasi miliknya.
- Untuk admin panel, administrator bisa melihat semua organisasi melalui Filament.

## 10.3 Menu API

```txt
GET    /menus
POST   /menus
GET    /menus/{menu}
PATCH  /menus/{menu}
DELETE /menus/{menu}

GET    /menu-categories
POST   /menu-categories
PATCH  /menu-categories/{category}
DELETE /menu-categories/{category}
```

## 10.4 Table API

```txt
GET    /dining-tables
POST   /dining-tables
GET    /dining-tables/{table}
PATCH  /dining-tables/{table}
DELETE /dining-tables/{table}
POST   /dining-tables/{table}/regenerate-qr
```

## 10.5 Customer Web API

```txt
POST /customer/sessions/start
GET  /customer/sessions/current
GET  /customer/menu
GET  /customer/open-bill
POST /customer/orders
GET  /customer/orders
POST /customer/call-cashier
```

Contoh start session:

```txt
POST /api/v1/customer/sessions/start
```

Payload:

```json
{
  "organization_slug": "kopi-santap",
  "table_code": "A01",
  "qr_token": "random-token"
}
```

Response:

```json
{
  "session_token": "guest-session-token",
  "organization": {
    "id": "uuid",
    "name": "Kopi Santap"
  },
  "table": {
    "id": "uuid",
    "name": "Meja A01"
  },
  "open_bill": {
    "id": "uuid",
    "status": "open"
  }
}
```

## 10.6 Order API

Untuk Flutter user:

```txt
GET   /orders
GET   /orders/{order}
POST  /orders
PATCH /orders/{order}/status
POST  /orders/{order}/cancel
```

Untuk kitchen:

```txt
GET   /kitchen/orders
PATCH /kitchen/order-items/{orderItem}/status
PATCH /kitchen/orders/{order}/status
```

## 10.7 Bill API

```txt
GET  /open-bills
GET  /open-bills/{bill}
POST /open-bills/{bill}/close
POST /open-bills/{bill}/cancel
```

## 10.8 Payment API

```txt
GET  /payments
POST /payments
GET  /payments/{payment}
POST /payments/{payment}/void
POST /payments/{payment}/refund
```

## 10.9 Report API

```txt
GET /reports/sales-summary
GET /reports/daily-sales
GET /reports/menu-sales
GET /reports/payment-methods
```

---

---

[Indeks Santap API](../santap-api.md)
