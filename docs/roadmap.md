# Roadmap Santap API

[Indeks Santap API](santap-api.md)

---

Dokumen ini adalah peta kerja implementasi Santap API. Detail setiap fase disimpan di folder `docs/roadmap/phase_*` agar pekerjaan bisa dikerjakan bertahap, dicek, dan ditutup dengan acceptance criteria yang jelas.

## Prinsip Urutan

Roadmap ini mengikuti keputusan utama dari spesifikasi Santap API:

1. Fondasi multi-organisasi harus selesai sebelum fitur restoran.
2. Semua data bisnis wajib aman dengan `organization_id` scoping.
3. Role owner/cashier/kitchen berlaku per organisasi.
4. Customer web tidak memakai tabel `users`, tetapi memakai guest session.
5. Open bill menjadi pusat lifecycle order customer.
6. Realtime, queue, report, dan monitoring dikerjakan setelah alur transaksi dasar stabil.

## Status Repo Saat Roadmap Dibuat

Berdasarkan repo saat ini:

- Laravel sudah ada dengan PHP `^8.3`.
- Package yang sudah tercatat di `composer.json`: Sanctum, Filament, Reverb, Pulse, Spatie Permission, Spatie Activitylog, dan Spatie Media Library.
- Migration awal yang sudah ada: users, cache, jobs, Sanctum personal access token, permission, dan activity log.
- Package Horizon belum terlihat di `composer.json`, walaupun sudah masuk rencana arsitektur.
- Model/domain utama seperti Organization, Menu, DiningTable, CustomerSession, OpenBill, Order, Payment, middleware context, dan API V1 masih perlu dibuat.

## Ringkasan Phase

| Phase | Fokus | Detail | Hasil Akhir |
|---:|---|---|---|
| 1 | Instalasi dan fondasi project | [phase_1_instalasi](roadmap/phase_1_instalasi/) | Environment Laravel siap, package inti terpasang, koneksi database dan baseline quality check jelas. |
| 2 | Auth, organisasi, role, dan context | [phase_2_auth_organisasi_permission](roadmap/phase_2_auth_organisasi_permission/) | Login Sanctum, membership organisasi, role per organisasi, dan middleware organization context berjalan. |
| 3 | Admin panel Santap | [phase_3_admin_panel_santap](roadmap/phase_3_admin_panel_santap/) | Filament admin hanya untuk administrator global, resource platform dasar tersedia. |
| 4 | Master data restoran | [phase_4_master_data_restoran](roadmap/phase_4_master_data_restoran/) | Owner bisa mengelola profil organisasi, kategori menu, menu, meja, QR, dan media. |
| 5 | Customer session dan open bill | [phase_5_customer_session_open_bill](roadmap/phase_5_customer_session_open_bill/) | Customer bisa scan QR, start session, join/open bill, dan melihat menu aktif. |
| 6 | Order dan kitchen | [phase_6_order_kitchen](roadmap/phase_6_order_kitchen/) | Customer/cashier bisa membuat order, kitchen bisa melihat dan update status order/item. |
| 7 | Payment dan close bill | [phase_7_payment_close_bill](roadmap/phase_7_payment_close_bill/) | Cashier bisa mencatat pembayaran, menutup bill, menutup customer session, dan mengembalikan meja available. |
| 8 | Realtime, queue, dan notification | [phase_8_realtime_queue_notification](roadmap/phase_8_realtime_queue_notification/) | Event order/bill/payment dibroadcast, job non-blocking berjalan, notifikasi dasar tersedia. |
| 9 | Reporting, audit, dan monitoring | [phase_9_report_audit_monitoring](roadmap/phase_9_report_audit_monitoring/) | Laporan penjualan dasar, audit log, Pulse, dan Horizon siap dipakai operasional. |
| 10 | Hardening dan release MVP | [phase_10_hardening_release_mvp](roadmap/phase_10_hardening_release_mvp/) | API siap dipakai Flutter/customer web untuk MVP dengan test, seed, docs, dan checklist deploy. |
| 11 | Pasca MVP dan ekspansi produk | [phase_11_pasca_mvp_ekspansi](roadmap/phase_11_pasca_mvp_ekspansi/) | Fitur lanjutan diprioritaskan tanpa mengganggu stabilitas MVP. |

## Milestone MVP

MVP dianggap siap ketika fitur berikut sudah berjalan end-to-end:

- User login dengan Sanctum.
- User bisa memiliki beberapa organisasi.
- Role owner, cashier, dan kitchen berjalan per organisasi.
- Semua endpoint bisnis discoped ke organisasi aktif.
- Owner bisa mengelola menu dan meja QR.
- Customer bisa scan QR tanpa login.
- Customer session dan open bill aktif dibuat dengan benar.
- Customer bisa membuat order.
- Kitchen bisa mengubah status order/item.
- Cashier bisa menerima pembayaran dan close bill.
- Close bill menutup customer session dan mengosongkan meja.
- Event realtime utama berjalan untuk order, kitchen status, dan bill closed.
- Admin Santap bisa mengelola organisasi dan melihat audit dasar.

## Backlog Bukan MVP

Fitur berikut tidak menghalangi rilis MVP:

- Subscription billing otomatis.
- Inventory/stok bahan baku detail.
- Multi-branch kompleks.
- Printer thermal production integration.
- Advanced discount engine.
- Loyalty/customer account login.
- Payment gateway penuh selain integrasi QRIS sederhana.
- Marketplace integration.
- Database per tenant.

---

[Indeks Santap API](santap-api.md)
