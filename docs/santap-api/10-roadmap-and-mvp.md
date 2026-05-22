# Rencana Implementasi dan Batas MVP

[Indeks Santap API](../santap-api.md)

---

## 22. Rencana Implementasi Bertahap

### Phase 1 — Foundation

```txt
Laravel project setup
Neon PostgreSQL connection
Sanctum auth
User model
Organization model
Organization member model
Role/permission setup
Organization context middleware
Filament admin login
```

### Phase 2 — Core Restaurant Data

```txt
Menu category
Menu
Dining table
QR code table
Organization settings
Media upload untuk menu/logo
```

### Phase 3 — Customer Session dan Open Bill

```txt
QR validation
Customer session start
Open bill creation
Customer menu API
Customer order API
```

### Phase 4 — Order Management

```txt
Order
Order item
Kitchen order list
Order status update
Cashier open bill list
```

### Phase 5 — Payment dan Closing

```txt
Payment create
Payment paid
Close bill
Close customer sessions
Table available again
Receipt data
```

### Phase 6 — Realtime

```txt
Laravel Reverb setup
Broadcast order created
Broadcast order status update
Broadcast bill closed
Flutter/web subscribe channel
```

### Phase 7 — Reporting dan Audit

```txt
Sales summary
Daily sales
Menu sales
Payment method report
Activity log dashboard
Laravel Pulse monitoring
Horizon monitoring
```

---

## 23. Hal yang Belum Perlu Masuk MVP

Fitur berikut bisa ditunda:

```txt
Subscription billing otomatis
Inventory/stok bahan baku detail
Multi-branch kompleks
Printer thermal production integration
Advanced discount engine
Loyalty/customer account login
Payment gateway penuh
Marketplace integration
Database per tenant
```

MVP sebaiknya fokus pada:

```txt
Login user
Multi organisasi
Role owner/cashier/kitchen
Menu
Meja QR
Customer order tanpa login
Open bill
Kitchen status
Cashier payment
Close bill
Realtime order
Admin panel Santap
```

---

---

[Indeks Santap API](../santap-api.md)
