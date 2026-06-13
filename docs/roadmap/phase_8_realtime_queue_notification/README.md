# Phase 8: Realtime, Queue, dan Notification

[Roadmap](../../roadmap.md)

---

## Tujuan

Menghubungkan workflow transaksi dengan update realtime, background jobs, dan notifikasi agar Flutter dan customer web tidak perlu polling terus-menerus.

## Referensi

- [Realtime, Queue, dan Admin Panel](../../santap-api/07-realtime-queue-and-admin.md)
- [Middleware dan Data Scoping](../../santap-api/06-middleware-and-data-scoping.md)
- [Struktur Laravel, Enum, Validasi, dan Security](../../santap-api/08-laravel-implementation-rules.md)

## Scope

- Laravel Reverb setup.
- Broadcasting private channel auth.
- Event utama:
  - `OrderCreated`
  - `OrderAccepted`
  - `OrderStatusUpdated`
  - `OrderItemStatusUpdated`
  - `OpenBillCreated`
  - `OpenBillRepeatOrderCreated`
  - `OpenBillClosed`
  - `PaymentCreated`
  - `PaymentPaid`
  - `CashierCalled`
  - `TableStatusChanged`
- Queue:
  - `default`
  - `notifications`
  - `broadcasts`
  - `reports`
- Jobs:
  - `SendOrganizationInvitationEmail`
  - `GenerateQrCodeImage`
  - `GenerateDailySalesReport`
  - `SendPaymentReceipt`
  - `CloseExpiredCustomerSessions`
  - `ExpirePendingInvitations`
- Laravel Notifications untuk invite dan event penting.

## Urutan Pengerjaan

1. Konfigurasi Reverb untuk lokal dan production.
2. Definisikan channel:
   - `private-org.{organizationId}`
   - `private-kitchen.{organizationId}`
   - `private-cashier.{organizationId}`
   - `private-table.{organizationId}.{tableId}`
   - `private-customer-session.{sessionId}`
3. Implement auth channel dengan membership dan customer session validation.
4. Hubungkan domain event dari fase order/payment ke broadcast event.
5. Pastikan payload realtime tidak membocorkan data lintas organisasi.
6. Install dan konfigurasi Horizon jika belum ada.
7. Pindahkan proses non-blocking ke job.
8. Tambahkan scheduler untuk expired session dan invitation.
9. Tambahkan test channel authorization.
10. Uji manual dengan client subscriber sederhana atau Flutter/customer web saat tersedia.

## Deliverables

- Kitchen menerima order baru realtime.
- Cashier menerima order/payment/bill update realtime.
- Cashier/kitchen menerima notifikasi repeat order Open Bill per batch item terbaru.
- Customer menerima update status order atau bill closed.
- Job invitation, QR generation, receipt, dan expiry berjalan di queue.
- Horizon bisa memonitor queue.

## Acceptance Criteria

- User organisasi A tidak bisa subscribe channel organisasi B.
- Customer hanya bisa subscribe session/table miliknya.
- Broadcast order tidak berisi data rahasia.
- Job gagal tercatat dan bisa di-retry.
- API request tidak menunggu proses email/report/receipt.

## Out of Scope

- Offline sync penuh.
- Push notification mobile production.
- Advanced presence channel.

---

[Roadmap](../../roadmap.md)
