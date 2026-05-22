# Phase 7: Payment dan Close Bill

[Roadmap](../../roadmap.md)

---

## Tujuan

Menyelesaikan siklus transaksi POS: cashier review bill, mencatat pembayaran, close bill, menutup session customer, dan mengembalikan meja menjadi available.

## Referensi

- [Alur Sistem Utama](../../santap-api/03-core-workflows.md)
- [Skema Database Awal](../../santap-api/04-database-schema.md)
- [API Design](../../santap-api/05-api-design.md)
- [payments.md](../../payments.md)

## Scope

- Model dan migration:
  - `Payment`
  - `CashierShift` bila diputuskan masuk MVP.
- Enum:
  - `PaymentStatus`
  - `PaymentMethod`
- Endpoint:
  - `GET /api/v1/open-bills`
  - `GET /api/v1/open-bills/{bill}`
  - `POST /api/v1/open-bills/{bill}/close`
  - `POST /api/v1/open-bills/{bill}/cancel`
  - `GET /api/v1/payments`
  - `POST /api/v1/payments`
  - `GET /api/v1/payments/{payment}`
  - `POST /api/v1/payments/{payment}/void`
  - `POST /api/v1/payments/{payment}/refund`
- Integrasi QRIS sederhana:
  - create QRIS
  - check QRIS
  - cancel QRIS
- Receipt data.

## Urutan Pengerjaan

1. Buat migration payments.
2. Buat `PaymentService` dan `BillService`.
3. Hitung subtotal bill dari order valid.
4. Terapkan tax/service/discount dari organization settings bila sudah aktif.
5. Implement create payment.
6. Implement payment paid untuk cash dan metode manual.
7. Implement QRIS sederhana memakai endpoint yang ada di `docs/payments.md`.
8. Implement close bill:
   - validasi permission cashier/owner.
   - validasi total payment cukup.
   - ubah bill menjadi closed.
   - tutup semua customer session.
   - ubah table menjadi available.
   - catat `closed_by` dan `closed_at`.
9. Implement void/refund dengan reason dan activity log.
10. Buat receipt response.
11. Tambahkan feature test end-to-end dari open bill sampai close.

## Deliverables

- Cashier bisa melihat open bill.
- Cashier bisa mencatat payment.
- Payment cash/manual bisa menjadi paid.
- QRIS sederhana bisa create/check/cancel jika dipakai di MVP.
- Bill bisa ditutup dengan benar.
- Customer session tidak valid setelah bill closed.

## Acceptance Criteria

- Bill tidak bisa closed jika tidak memenuhi aturan payment.
- Payment paid mencatat `paid_at` dan `paid_by`.
- Void/refund wajib reason.
- Setelah close bill, customer tidak bisa submit order lagi.
- Setelah close bill, table menjadi available.
- Receipt memakai snapshot order, bukan harga menu terbaru.

## Out of Scope

- Payment gateway penuh.
- Settlement otomatis.
- Split bill kompleks.
- Loyalty point.

---

[Roadmap](../../roadmap.md)
