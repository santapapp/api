# Phase 5: Customer Session dan Open Bill

[Roadmap](../../roadmap.md)

---

## Tujuan

Membuat customer web bisa memulai sesi tanpa login setelah scan QR, lalu membuka atau bergabung ke open bill meja.

## Referensi

- [Autentikasi dan Session](../../santap-api/02-authentication-and-sessions.md)
- [Alur Sistem Utama](../../santap-api/03-core-workflows.md)
- [Skema Database Awal](../../santap-api/04-database-schema.md)
- [API Design](../../santap-api/05-api-design.md)

## Scope

- Model dan migration:
  - `CustomerSession`
  - `OpenBill`
- Enum:
  - `CustomerSessionStatus`
  - `BillStatus`
- Endpoint customer:
  - `POST /api/v1/customer/sessions/start`
  - `GET /api/v1/customer/sessions/current`
  - `GET /api/v1/customer/menu`
  - `GET /api/v1/customer/open-bill`
- Middleware:
  - `ensure.customer.session`
  - `ensure.open.bill.active`
- Service lifecycle customer session dan open bill.

## Urutan Pengerjaan

1. Buat migration customer sessions dan open bills.
2. Buat unique/partial constraint agar satu meja tidak memiliki lebih dari satu open bill aktif.
3. Buat token customer session yang random dan aman.
4. Pertimbangkan menyimpan hash token di database.
5. Implement validasi QR:
   - organization slug valid.
   - table code valid.
   - QR token aktif.
   - organization dan table aktif.
6. Implement start session:
   - buat open bill jika belum ada.
   - join open bill aktif jika kebijakan restoran mengizinkan.
   - buat customer session.
7. Implement current session dan open bill.
8. Implement customer menu API hanya menampilkan menu active.
9. Update table status menjadi occupied saat bill dibuka.
10. Tambahkan test untuk token, QR invalid, open bill existing, dan bill closed.

## Deliverables

- Customer bisa start session dari QR.
- Customer menerima `session_token`.
- Open bill aktif tersedia untuk meja.
- Customer hanya bisa melihat data session dan bill miliknya.
- Table status berubah sesuai lifecycle bill.

## Acceptance Criteria

- Customer tidak bisa memalsukan `organization_id`.
- QR revoked tidak bisa dipakai.
- Session closed/expired tidak bisa mengakses open bill.
- Bill closed tidak bisa menerima session/order baru.
- Satu meja tidak membuat dua open bill aktif bersamaan.

## Out of Scope

- Submit order.
- Kitchen workflow.
- Payment.
- Receipt token.

---

[Roadmap](../../roadmap.md)
