# Phase 6: Order dan Kitchen

[Roadmap](../../roadmap.md)

---

## Tujuan

Membangun alur order dari customer/cashier sampai kitchen bisa memproses status order dan order item.

## Referensi

- [Alur Sistem Utama](../../santap-api/03-core-workflows.md)
- [Skema Database Awal](../../santap-api/04-database-schema.md)
- [API Design](../../santap-api/05-api-design.md)
- [Struktur Laravel, Enum, Validasi, dan Security](../../santap-api/08-laravel-implementation-rules.md)

## Scope

- Model dan migration:
  - `Order`
  - `OrderItem`
  - `OrderItemAddon` bila addon sudah masuk.
- Enum:
  - `OrderStatus`
  - `OrderItemStatus`
  - `OrderSource`
- Endpoint customer:
  - `POST /api/v1/customer/orders`
  - `GET /api/v1/customer/orders`
- Endpoint Flutter:
  - `GET /api/v1/orders`
  - `GET /api/v1/orders/{order}`
  - `POST /api/v1/orders`
  - `PATCH /api/v1/orders/{order}/status`
  - `POST /api/v1/orders/{order}/cancel`
- Endpoint kitchen:
  - `GET /api/v1/kitchen/orders`
  - `PATCH /api/v1/kitchen/order-items/{orderItem}/status`
  - `PATCH /api/v1/kitchen/orders/{order}/status`

## Urutan Pengerjaan

1. Buat migration orders dan order items.
2. Tambahkan snapshot harga dan nama menu di order item.
3. Buat service `OrderService` untuk pembuatan order.
4. Validasi order customer:
   - session aktif.
   - open bill aktif.
   - menu aktif dan tersedia.
   - quantity valid.
   - harga memakai snapshot.
5. Validasi order internal cashier/owner.
6. Implement list order untuk cashier/owner.
7. Implement list kitchen yang fokus pada order pending/cooking/ready.
8. Implement status transition order item.
9. Implement status transition order aggregate bila diperlukan.
10. Implement cancel/void order dengan reason dan activity log.
11. Tambahkan event domain, walau broadcast realtime detail masuk fase 8.
12. Tambahkan feature test untuk pembuatan order, snapshot harga, permission kitchen, dan cancel.

## Deliverables

- Customer bisa membuat order dari session aktif.
- Order item menyimpan snapshot nama dan harga menu.
- Kitchen bisa update status item.
- Cashier/owner bisa melihat order aktif.
- Cancel order/item tercatat alasan dan actor.

## Acceptance Criteria

- Order gagal jika bill sudah closed.
- Order gagal jika menu inactive/out_of_stock.
- Perubahan harga menu setelah order tidak mengubah order lama.
- Kitchen tidak bisa melihat payment/report.
- Kitchen tidak bisa mengakses organisasi lain.
- Cancel order membutuhkan permission dan reason.

## Out of Scope

- Broadcast realtime production.
- Payment.
- Close bill.
- Advanced modifier/addon jika belum masuk MVP.

---

[Roadmap](../../roadmap.md)
