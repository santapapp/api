# Phase 10: Hardening dan Release MVP

[Roadmap](../../roadmap.md)

---

## Tujuan

Mengunci kualitas MVP sebelum dipakai oleh Flutter app, customer web, dan admin internal Santap.

## Referensi

- [Rencana Implementasi dan Batas MVP](../../santap-api/10-roadmap-and-mvp.md)
- [Keputusan Final dan Catatan Implementasi](../../santap-api/11-decisions-and-notes.md)
- [Struktur Laravel, Enum, Validasi, dan Security](../../santap-api/08-laravel-implementation-rules.md)

## Scope

- Test coverage untuk alur utama.
- Seed data development/demo.
- API docs final untuk Flutter/customer web.
- Error response standard.
- Rate limit endpoint sensitif.
- Security review multi-organisasi.
- Deployment checklist.
- Backup/restore database plan.
- Observability dasar.

## Urutan Pengerjaan

1. Rapikan response format API.
2. Rapikan validation error dan domain error.
3. Pastikan semua endpoint user memakai `auth:sanctum`.
4. Pastikan semua endpoint bisnis memakai organization context.
5. Audit query model bisnis dari risiko data lintas organisasi.
6. Tambahkan rate limit:
   - login
   - invite
   - customer session start
   - customer order
   - QRIS check bila diperlukan.
7. Tambahkan feature test end-to-end:
   - login owner
   - create menu/table
   - customer scan QR
   - customer order
   - kitchen update
   - cashier payment
   - close bill
8. Tambahkan seed demo organization lengkap.
9. Perbarui dokumentasi endpoint.
10. Jalankan lint, test, migration fresh, dan smoke test.
11. Siapkan env production/staging.
12. Buat release checklist.

## Deliverables

- MVP siap dipakai integrasi Flutter dan customer web.
- Test utama tersedia.
- Demo data tersedia.
- Dokumentasi endpoint sesuai implementasi.
- Checklist deploy tersedia.

## Acceptance Criteria

- Tidak ada endpoint bisnis tanpa organization scope.
- Tidak ada customer session yang bisa akses bill orang lain.
- Migration fresh berjalan dari nol.
- Test alur utama pass.
- Error API konsisten dan mudah dipahami client.
- Secret tidak bocor di repo.

## Out of Scope

- Optimasi skala besar.
- Multi-region deployment.
- Payment gateway kompleks.
- Fitur non-MVP.

---

[Roadmap](../../roadmap.md)
