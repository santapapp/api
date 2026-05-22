# Phase 9: Reporting, Audit, dan Monitoring

[Roadmap](../../roadmap.md)

---

## Tujuan

Menyediakan laporan dasar untuk owner/cashier, audit untuk aksi sensitif, dan monitoring operasional Laravel.

## Referensi

- [API Design](../../santap-api/05-api-design.md)
- [Realtime, Queue, dan Admin Panel](../../santap-api/07-realtime-queue-and-admin.md)
- [Struktur Laravel, Enum, Validasi, dan Security](../../santap-api/08-laravel-implementation-rules.md)

## Scope

- Report API:
  - `GET /api/v1/reports/sales-summary`
  - `GET /api/v1/reports/daily-sales`
  - `GET /api/v1/reports/menu-sales`
  - `GET /api/v1/reports/payment-methods`
- Activity log dashboard/resource.
- Audit log untuk action sensitif:
  - update role member
  - cancel order
  - void/refund payment
  - suspend organization
  - update harga menu
  - close bill
- Laravel Pulse.
- Laravel Horizon.
- Failed job visibility.

## Urutan Pengerjaan

1. Definisikan query report berdasarkan transaksi closed/paid.
2. Pastikan report selalu discoped berdasarkan organization context.
3. Tambahkan filter tanggal dan timezone organisasi.
4. Implement sales summary.
5. Implement daily sales.
6. Implement menu sales.
7. Implement payment method report.
8. Tambahkan caching ringan jika query mulai berat.
9. Buat ActivityLogResource di Filament.
10. Pastikan Pulse dan Horizon bisa diakses sesuai role/internal access.
11. Tambahkan test report lintas organisasi.

## Deliverables

- Owner bisa melihat ringkasan penjualan.
- Cashier bisa melihat laporan yang diizinkan jika permission aktif.
- Admin Santap bisa melihat audit dan health sistem.
- Aksi sensitif tercatat konsisten.

## Acceptance Criteria

- Report organisasi A tidak memasukkan data organisasi B.
- Report memakai timezone organisasi.
- Cancel/void/refund selalu memiliki reason dan actor.
- Update harga menu masuk audit log.
- Failed jobs bisa terlihat dari monitoring.

## Out of Scope

- Export Excel/PDF production.
- Data warehouse.
- Advanced BI dashboard.
- Forecasting penjualan.

---

[Roadmap](../../roadmap.md)
