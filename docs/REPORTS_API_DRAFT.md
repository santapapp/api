# API Laporan - Request untuk Backend (Laravel)

## Ringkasan

Butuh 7 endpoint laporan baru. Semua endpoint:
- Prefix: `/v1/reports/`
- Auth: Bearer token (existing)
- Org isolation: pakai `organization_id` dari user yang login
- Filter wajib: `start_date` & `end_date` (format: `YYYY-MM-DD`)

---

## Endpoints

### 1. `GET /v1/reports/financial/summary`

Ringkasan keuangan: total pendapatan, subtotal, service charge, jumlah transaksi, breakdown per tipe order, breakdown metode bayar, dan jumlah transaksi batal.

**Params**: `start_date`, `end_date`, `group_by` (daily|weekly|monthly, default: daily)

**Response**:
```json
{
  "summary": {
    "total_revenue": 25500000,
    "total_subtotal": 20000000,
    "service_charge_total": 2000000,
    "total_transactions": 150,
    "transaction_count_by_type": {
      "cashier_order": 100,
      "open_bill": 30,
      "table_order": 20
    },
    "payment_method_breakdown": {
      "cash": { "count": 80, "amount": 15000000 },
      "qris": { "count": 70, "amount": 10500000 }
    },
    "cancelled_transactions": { "count": 5, "total_amount": 500000 }
  },
  "breakdown": [
    { "date": "2024-06-01", "revenue": 850000, "transactions": 5 }
  ]
}
```

**Logika**: Agregasi dari tabel `orders` WHERE `payment_status` IN (paid, cancelled), filter by date range pada `paid_at`.

---

### 2. `GET /v1/reports/products/bestsellers`

Top produk berdasarkan jumlah terjual.

**Params**: `start_date`, `end_date`, `limit` (default: 10, max: 50)

**Response**:
```json
{
  "products": [
    { "id": 1, "name": "Nasi Goreng", "total_qty": 245, "total_revenue": 2450000 }
  ]
}
```

**Logika**: JOIN `order_items` → `orders` (paid), GROUP BY product, ORDER BY SUM(quantity) DESC.

---

### 3. `GET /v1/reports/products/no-sales`

Produk yang tidak terjual dalam periode.

**Params**: `start_date`, `end_date`

**Response**:
```json
{
  "products": [
    { "id": 10, "name": "Sup Buntut", "price": 45000, "last_sold_date": "2024-05-28" }
  ]
}
```

**Logika**: LEFT JOIN products ke order_items (filtered by date). HAVING SUM(qty) IS NULL.

---

### 4. `GET /v1/reports/products/by-category`

Penjualan per kategori.

**Params**: `start_date`, `end_date`

**Response**:
```json
{
  "categories": [
    { "name": "Main Course", "total_qty": 450, "total_revenue": 6750000, "percentage": 45.5 }
  ]
}
```

**Logika**: Sama seperti bestsellers tapi GROUP BY category (dari metadata produk).

---

### 5. `GET /v1/reports/products/trend`

Trend penjualan 1 produk per hari.

**Params**: `product_id`, `start_date`, `end_date`

**Response**:
```json
{
  "product": { "id": 1, "name": "Nasi Goreng" },
  "trend": [
    { "date": "2024-06-01", "qty": 10, "revenue": 100000 }
  ]
}
```

---

### 6. `GET /v1/reports/operational/by-cashier`

Performa per kasir/staff.

**Params**: `start_date`, `end_date`

**Response**:
```json
{
  "cashiers": [
    {
      "id": 5,
      "name": "Budi",
      "total_transactions": 75,
      "total_revenue": 12500000,
      "cash_amount": 8000000,
      "qris_amount": 4500000
    }
  ]
}
```

**Logika**: GROUP BY `created_by_id`, JOIN ke `users`.

---

### 7. `GET /v1/reports/operational/peak-hours`

Jam-jam ramai.

**Params**: `start_date`, `end_date`

**Response**:
```json
{
  "hours": [
    { "hour": 12, "transactions": 22, "revenue": 2500000 },
    { "hour": 13, "transactions": 18, "revenue": 2100000 }
  ]
}
```

**Logika**: GROUP BY HOUR(paid_at), ORDER BY hour.

---

## Notes untuk Backend

- Semua amount dalam **integer** (Rupiah, tanpa desimal)
- Filter orders yang `payment_status = 'paid'` (kecuali cancelled count)
- Pakai `paid_at` sebagai reference tanggal, bukan `created_at`
- Tambahkan index di kolom `(organization_id, payment_status, paid_at)` kalau belum ada
- Validasi: max date range 365 hari, return 400 kalau invalid
