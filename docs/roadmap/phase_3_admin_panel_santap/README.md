# Phase 3: Admin Panel Santap

[Roadmap](../../roadmap.md)

---

## Tujuan

Menyiapkan Filament Admin Panel untuk administrator global Santap agar platform bisa dikelola tanpa masuk ke aplikasi operasional restoran.

## Referensi

- [Overview Produk dan Konteks](../../santap-api/00-overview.md)
- [Autentikasi dan Session](../../santap-api/02-authentication-and-sessions.md)
- [Realtime, Queue, dan Admin Panel](../../santap-api/07-realtime-queue-and-admin.md)

## Scope

- Akses `/admin` hanya untuk role global `administrator`.
- Filament panel provider dan auth gate.
- Resource awal:
  - UserResource
  - OrganizationResource
  - OrganizationMemberResource
  - ActivityLogResource
  - SystemSettingResource bila diperlukan.
- Dashboard widget dasar.
- Audit log untuk action sensitif.
- Readonly/support view untuk order dan payment bila modelnya sudah tersedia di fase berikutnya.

## Urutan Pengerjaan

1. Pastikan role global `administrator` tersedia dari seeder.
2. Tambahkan gate/policy agar hanya administrator bisa masuk panel.
3. Buat admin user seeder untuk development.
4. Buat resource user dan organisasi.
5. Tambahkan action sensitif:
   - suspend organization
   - activate organization
   - force remove member
6. Hubungkan action sensitif ke activity log.
7. Buat dashboard widget awal:
   - total organizations
   - active organizations
   - suspended organizations
   - total users
   - recent activity logs
8. Tambahkan test/pemeriksaan akses panel.

## Deliverables

- Administrator bisa login ke `/admin`.
- User biasa tidak bisa masuk Filament admin.
- Administrator bisa melihat dan mengelola organisasi.
- Action sensitif tercatat di audit log.

## Acceptance Criteria

- User owner/cashier/kitchen ditolak dari `/admin`.
- Suspend organization membuat organisasi tidak bisa dipakai di API context.
- Activate organization mengembalikan akses organisasi.
- Semua action sensitif memiliki actor, target, waktu, dan metadata cukup.

## Out of Scope

- Resource order/payment lengkap jika model transaksi belum tersedia.
- Subscription plan otomatis.
- Billing platform.

---

[Roadmap](../../roadmap.md)
